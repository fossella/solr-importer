<?php

declare(strict_types=1);

/**
 * Example config - the PHP equivalent of a DIH data-config.xml.
 * Return an array shaped like this from your own config file and
 * point bin/import at it.
 *
 * Connection info (DB and Solr credentials/host) intentionally does NOT
 * live here - it's read from environment variables (populated from
 * .env by bin/import) so this file is safe to commit. Only the shape of
 * the data - entities, fields, transformers - stays here.
 */
use SolrImport\Env;

return [
    'datasource' => [
        'dsn' => Env::get('DB_DSN'),
        'user' => Env::get('DB_USER'),
        'pass' => Env::get('DB_PASSWORD'),
    ],

    'document' => [
        'entities' => [
            [
                'name' => 'product',
                'pk' => 'id',
                // Main query used for full imports, and re-run (filtered by pk)
                // for delta imports.
                'query' => 'SELECT id, name, description, sku, updated_at
                             FROM products',
                // Used only for delta-import: finds which rows changed.
                'deltaQuery' => 'SELECT id FROM products
                                  WHERE updated_at > :last_index_time',
                'fields' => [
                    ['column' => 'id', 'name' => 'id'],
                    ['column' => 'name', 'name' => 'name_s'],
                    [
                        'column' => 'description',
                        'name' => 'description_t',
                        'transformer' => 'regex',
                        'options' => ['pattern' => '/<[^>]+>/', 'replace' => ''],
                    ],
                    [
                        'column' => 'name',
                        'name' => 'display_label_s',
                        'transformer' => 'template',
                        'options' => ['template' => '${name} (${sku})'],
                    ],
                    [
                        'column' => 'updated_at',
                        'name' => 'updated_at_dt',
                        'transformer' => 'date',
                        'options' => ['from' => 'Y-m-d H:i:s'],
                    ],
                ],
                // Equivalent of a nested DIH <entity> - merged as a
                // multivalued field on the parent document.
                'children' => [
                    [
                        'name' => 'category',
                        'pk' => 'product_id',
                        'query' => 'SELECT c.name AS category_name
                                     FROM product_categories pc
                                     JOIN categories c ON c.id = pc.category_id
                                     WHERE pc.product_id = :parent_pk',
                        'fields' => [
                            ['column' => 'category_name', 'name' => 'category_ss'],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
