<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

/**
 * Mirrors the role of DIH's <transformer> elements: takes a raw column
 * value (plus the full row, for context) and returns a transformed value.
 */
interface TransformerInterface
{
    /**
     * @param mixed $value The raw value pulled from the row for this field.
     * @param array<string,mixed> $row The full source row, in case the
     *   transformer needs other columns (e.g. templating).
     * @param array<string,mixed> $options Per-field options from the config,
     *   e.g. ['pattern' => '/foo/', 'replace' => 'bar'].
     */
    public function transform(mixed $value, array $row, array $options): mixed;
}
