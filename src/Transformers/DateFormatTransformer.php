<?php

declare(strict_types=1);

namespace SolrImport\Transformers;

/**
 * Equivalent of DIH's DateFormatTransformer.
 * Options: from (input format, e.g. 'Y-m-d H:i:s'), to (defaults to Solr's
 * expected UTC 'Z' format).
 */
final class DateFormatTransformer implements TransformerInterface
{
    public function transform(mixed $value, array $row, array $options): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $from = $options['from'] ?? null;
        $to = $options['to'] ?? 'Y-m-d\TH:i:s\Z';

        $date = $from
            ? \DateTime::createFromFormat($from, (string) $value, new \DateTimeZone('UTC'))
            : new \DateTime((string) $value, new \DateTimeZone('UTC'));

        if ($date === false) {
            return $value; // leave unparsable values untouched rather than throwing
        }

        return $date->format($to);
    }
}
