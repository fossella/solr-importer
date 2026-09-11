<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

/**
 * Equivalent of DIH's TemplateTransformer.
 * Options: template, e.g. '${name} (${sku})' - placeholders reference
 * other columns in the same row.
 */
final class TemplateTransformer implements TransformerInterface
{
    public function transform(mixed $value, array $row, array $options): mixed
    {
        $template = $options['template'] ?? null;
        if ($template === null) {
            return $value;
        }

        return preg_replace_callback(
            '/\$\{(\w+)\}/',
            static fn (array $m) => (string) ($row[$m[1]] ?? ''),
            $template
        );
    }
}
