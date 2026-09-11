<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

/**
 * Equivalent of DIH's RegexTransformer.
 * Options: pattern (PCRE, including delimiters), replace (optional).
 * If 'replace' is omitted, the first capture group match is returned.
 */
final class RegexTransformer implements TransformerInterface
{
    public function transform(mixed $value, array $row, array $options): mixed
    {
        if ($value === null) {
            return null;
        }

        $pattern = $options['pattern'] ?? null;
        if ($pattern === null) {
            return $value;
        }

        if (array_key_exists('replace', $options)) {
            return preg_replace($pattern, $options['replace'], (string) $value);
        }

        if (preg_match($pattern, (string) $value, $matches)) {
            return $matches[1] ?? $matches[0];
        }

        return $value;
    }
}
