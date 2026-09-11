<?php

declare(strict_types=1);

namespace SolrImport;

use PDO;
use Solarium\Client;
use SolrImport\Connection\ConnectionFactory;
use SolrImport\Transformers\TransformerRegistry;

/**
 * Drives full and delta imports against Solr via Solarium, driven by a
 * DataConfig. This replaces the functionality Solr's built-in
 * DataImportHandler used to provide (removed in Solr 9):
 *
 *   - full-import  -> Importer::fullImport()
 *   - delta-import -> Importer::deltaImport()
 *
 * Nested/child entities are merged into the parent document as
 * multivalued fields, mirroring DIH's sub-entity behavior.
 *
 * READS ARE CHUNKED. A single unbounded query against a huge table is
 * risky for two reasons: most PDO drivers (notably MySQL by default)
 * buffer the *entire* result set into client memory before your code
 * sees the first row, and a long-lived cursor is fragile - one dropped
 * connection and you start over. To avoid both, full imports page
 * through the table using keyset pagination:
 *
 *   SELECT * FROM (<entity query>) t WHERE <pk> > :last_pk
 *   ORDER BY <pk> LIMIT :chunk_size
 *
 * tracking the max pk seen and looping until a page comes back short.
 * This requires the entity's pk to be sortable and (effectively) unique
 * - a plain auto-increment/integer/UUID primary key works; a composite
 * or non-orderable key does not, see README.
 */
final class Importer
{
    private PDO $pdo;
    private TransformerRegistry $transformers;

    public function __construct(
        private readonly DataConfig $config,
        private readonly Client $solr,
        private readonly StateStore $state,
        private readonly int $batchSize = 500,
        private readonly int $chunkSize = 2000,
        ?TransformerRegistry $transformers = null,
    ) {
        $this->pdo = ConnectionFactory::create($this->config->datasource);
        $this->transformers = $transformers ?? new TransformerRegistry();
    }

    /**
     * Re-indexes every row from every entity's main query, ignoring any
     * delta/last-index-time state. Equivalent to DIH's full-import command.
     * Reads are paged through in chunkSize-row pages via keyset pagination.
     *
     * @return int number of documents sent to Solr
     */
    public function fullImport(): int
    {
        $total = 0;
        foreach ($this->config->entities as $entity) {
            $total += $this->importEntityPaginated($entity);
            $this->state->setLastIndexTime($entity->name, $this->nowUtc());
        }

        return $total;
    }

    /**
     * Re-indexes only rows changed since the last successful import for
     * each entity, using that entity's deltaQuery. Equivalent to DIH's
     * delta-import command. Falls back to a full (paginated) import for
     * any entity that doesn't define a deltaQuery.
     *
     * The changed-pk list itself is also chunked, so a delta covering a
     * huge number of rows doesn't turn into one giant `IN (...)` query.
     *
     * @return int number of documents sent to Solr
     */
    public function deltaImport(): int
    {
        $total = 0;
        foreach ($this->config->entities as $entity) {
            $lastIndexTime = $this->state->getLastIndexTime($entity->name)
                ?? '1970-01-01T00:00:00Z';

            if ($entity->deltaQuery === null) {
                $total += $this->importEntityPaginated($entity);
                $this->state->setLastIndexTime($entity->name, $this->nowUtc());
                continue;
            }

            $changedPks = $this->fetchChangedPrimaryKeys($entity, $lastIndexTime);
            foreach (array_chunk($changedPks, $this->chunkSize) as $pkChunk) {
                $total += $this->importEntityByPks($entity, $pkChunk);
            }
            $this->state->setLastIndexTime($entity->name, $this->nowUtc());
        }

        return $total;
    }

    /**
     * @return array<int|string>
     */
    private function fetchChangedPrimaryKeys(Entity $entity, string $lastIndexTime): array
    {
        $stmt = $this->pdo->prepare($entity->deltaQuery);
        $stmt->execute(['last_index_time' => $lastIndexTime]);

        return array_column($stmt->fetchAll(), $entity->pk);
    }

    /**
     * Pages through an entity's full query in chunkSize-row pages using
     * keyset pagination on entity->pk, so memory use stays bounded no
     * matter how large the table is.
     */
    private function importEntityPaginated(Entity $entity): int
    {
        $total = 0;
        $lastPk = null;

        do {
            $sql = "SELECT * FROM ({$entity->query}) AS page_subquery"
                . ($lastPk !== null ? " WHERE {$entity->pk} > :last_pk" : '')
                . " ORDER BY {$entity->pk} ASC LIMIT :chunk_size";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':chunk_size', $this->chunkSize, PDO::PARAM_INT);
            if ($lastPk !== null) {
                $stmt->bindValue(':last_pk', $lastPk);
            }
            $stmt->execute();

            $rows = $stmt->fetchAll();
            if ($rows === []) {
                break;
            }

            $total += $this->indexRows($entity, $rows);
            $lastPk = end($rows)[$entity->pk];
        } while (count($rows) === $this->chunkSize);

        return $total;
    }

    /**
     * Re-fetches full rows for a bounded (already-chunked) list of
     * primary keys - used by delta imports, where the pk list has
     * already been split into manageable chunks by the caller.
     *
     * @param array<int|string> $pks
     */
    private function importEntityByPks(Entity $entity, array $pks): int
    {
        if ($pks === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($pks), '?'));
        $sql = "SELECT * FROM ({$entity->query}) AS delta_subquery WHERE {$entity->pk} IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($pks));

        return $this->indexRows($entity, $stmt->fetchAll());
    }

    /**
     * Builds Solr documents from an already-fetched (bounded-size) row
     * set and flushes them to Solr in batchSize-document batches. Batch
     * size (writes to Solr) is intentionally decoupled from chunk size
     * (reads from the DB) - they're tuned for different bottlenecks.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    private function indexRows(Entity $entity, array $rows): int
    {
        // A throwaway update object purely for its createDocument()
        // factory method - never passed to flush(), so it never
        // accumulates documents across batches.
        $documentFactory = $this->solr->createUpdate();
        $documents = [];
        $count = 0;

        foreach ($rows as $row) {
            $doc = $documentFactory->createDocument();
            foreach ($entity->fields as $field) {
                $doc->{$field['name']} = $this->applyField($field, $row);
            }

            foreach ($entity->children as $child) {
                $this->mergeChildEntity($child, $row, $doc);
            }

            $documents[] = $doc;
            $count++;

            if (count($documents) >= $this->batchSize) {
                $this->flush($documents);
                $documents = [];
            }
        }

        if ($documents !== []) {
            $this->flush($documents);
        }

        return $count;
    }

    /**
     * Runs a child entity's query filtered by the parent row's pk value
     * and attaches the results as a multivalued field on the parent doc,
     * e.g. product -> categories (one-to-many). Child result sets are
     * expected to be small per-parent (categories, tags, etc.) so they
     * are not chunked - if a child can itself be huge per parent, model
     * it as its own top-level entity instead.
     */
    private function mergeChildEntity(Entity $child, array $parentRow, $doc): void
    {
        $stmt = $this->pdo->prepare($child->query);
        $stmt->execute(['parent_pk' => $parentRow[$child->pk] ?? null]);

        $valuesByField = [];
        while ($childRow = $stmt->fetch()) {
            foreach ($child->fields as $field) {
                $valuesByField[$field['name']][] = $this->applyField($field, $childRow);
            }
        }

        foreach ($valuesByField as $fieldName => $values) {
            $doc->{$fieldName} = $values;
        }
    }

    private function applyField(array $field, array $row): mixed
    {
        $value = $row[$field['column']] ?? null;

        if (isset($field['transformer'])) {
            $value = $this->transformers
                ->get($field['transformer'])
                ->transform($value, $row, $field['options'] ?? []);
        }

        return $value;
    }

    /**
     * Sends exactly one batch of documents in a fresh update request.
     * Deliberately creates a new update object per call - reusing one
     * across batches would make addDocuments() accumulate documents
     * from earlier batches and re-send them on every subsequent commit.
     */
    private function flush(array $documents): void
    {
        $update = $this->solr->createUpdate();
        $update->addDocuments($documents);
        $update->addCommit(softCommit: true);
        $this->solr->update($update);
    }

    private function nowUtc(): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
