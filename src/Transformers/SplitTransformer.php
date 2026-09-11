<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

/**
 * Equivalent of DIH's splitBy="..." attribute, which is implemented as
 * part of RegexTransformer in Solr but is really just "split this
 * delimited string into a multivalued field." Kept as its own
 * transformer here since it returns an array rather than a scalar.
 *
 * Options: pattern (regex delimiter, including slashes), trim (bool,
 * default true - trims whitespace off each piece), dropEmpty (bool,
 * default true - drops empty strings after trimming).
 */
final class SplitTransformer implements TransformerInterface
{
    public function transform(mixed $value, array $row, array $options): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        $pattern = $options['pattern'] ?? null;
        if ($pattern === null) {
            return $value;
        }

        $trim = $options['trim'] ?? true;
        $dropEmpty = $options['dropEmpty'] ?? true;

        $parts = preg_split($pattern, (string) $value) ?: [];

        if ($trim) {
            $parts = array_map('trim', $parts);
        }

        if ($dropEmpty) {
            $parts = array_values(array_filter($parts, static fn (string $p) => $p !== ''));
        }

        return $parts;
    }
}
