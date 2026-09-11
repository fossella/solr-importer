<?php

declare(strict_types=1);

namespace SolrImport\Connection;

use PDO;

/**
 * Builds the PDO connection described by the 'datasource' block of the
 * data config - the rough equivalent of DIH's <dataSource> element.
 */
final class ConnectionFactory
{
    /**
     * @param array{dsn:string,user?:string,pass?:string,options?:array} $config
     */
    public static function create(array $config): PDO
    {
        $options = $config['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        // Belt-and-suspenders alongside Importer's keyset chunking: also
        // ask MySQL not to buffer entire result sets client-side, so even
        // a single unchunked query (e.g. a child-entity query) doesn't
        // pull more into memory than necessary.
        if (str_starts_with($config['dsn'], 'mysql:') && !array_key_exists(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $options)) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }

        $pdo = new PDO(
            $config['dsn'],
            $config['user'] ?? null,
            $config['pass'] ?? null,
            $options
        );
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
