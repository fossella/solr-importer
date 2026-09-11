<?php

declare(strict_types=1);

namespace SolrImport;

/**
 * Represents one entity block - the PHP equivalent of a DIH <entity>.
 * An entity has a main query, an optional delta query (for incremental
 * imports), a field map, and optional child entities that get merged
 * into the parent document (multivalued, like DIH sub-entities).
 */
final class Entity
{
    /** @var Entity[] */
    public readonly array $children;

    /**
     * @param array<int,array{column:string,name:string,transformer?:string,options?:array}> $fields
     * @param array<int,array<string,mixed>> $childConfigs raw child entity configs
     */
    public function __construct(
        public readonly string $name,
        public readonly string $query,
        public readonly ?string $deltaQuery,
        public readonly string $pk,
        public readonly array $fields,
        array $childConfigs = [],
    ) {
        $this->children = array_map(
            static fn (array $c) => Entity::fromArray($c),
            $childConfigs
        );
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            name: $config['name'],
            query: $config['query'],
            deltaQuery: $config['deltaQuery'] ?? null,
            pk: $config['pk'] ?? 'id',
            fields: $config['fields'] ?? [],
            childConfigs: $config['children'] ?? [],
        );
    }
}
