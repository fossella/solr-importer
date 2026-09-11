<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

/**
 * Equivalent of DIH's ScriptTransformer, but using native PHP callables
 * instead of embedded JavaScript - much easier to debug and test.
 * Options: fn => callable(mixed $value, array $row): mixed
 */
final class ScriptTransformer implements TransformerInterface
{
    public function transform(mixed $value, array $row, array $options): mixed
    {
        $fn = $options['fn'] ?? null;
        if (!is_callable($fn)) {
            return $value;
        }

        return $fn($value, $row);
    }
}
