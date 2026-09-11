<?php

declare(strict_types=1);

/**
 * Converted from data-config.xml.
 *
 * Notes on the conversion:
 *  - The <dataSource> block (JDBC URL, user, password) is NOT reproduced
 *    here - it now lives in .env (see .env.example). This file only
 *    describes the shape of the data, so it's safe to commit.
 *  - No <field name="..."> attributes were present in the original XML,
 *    so DIH defaulted every Solr field name to the column name - that's
 *    reproduced 1:1 below.
 *  - The entity declared transformer="RegexTransformer" only to enable
 *    splitBy on two fields (ad_keywords, ad_attributes). No field used
 *    regex/replaceWith, so no actual regex substitution was happening -
 *    only the two splits below carry any transformation.
 *  - splitBy=" ?; ?" -> split on optional-space + ';' + optional-space.
 *  - splitBy="\|\|" -> split on literal "||".
 *  - The original XML had no <field column="..."/> marked as a unique
 *    key, and no deltaQuery, so this reproduces the same all-or-nothing
 *    full-import-only behavior. I've set 'pk' => 'item_id' as a best
 *    guess based on the column name - confirm this is really your
 *    primary/unique key before relying on it (it's only used internally
 *    for delta re-querying and child-entity joins, both unused here).
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
                'name' => 'items',
                'pk' => 'item_id', // TODO: confirm this is the real unique key
                'query' => 'SELECT * FROM item_solr_search_infor',
                'fields' => [
                    ['column' => 'item_id', 'name' => 'item_id'],
                    ['column' => 'item_key', 'name' => 'item_key'],
                    ['column' => 'item_key_prefix', 'name' => 'item_key_prefix'],
                    ['column' => 'item_key_postfix', 'name' => 'item_key_postfix'],
                    ['column' => 'usage', 'name' => 'usage'],
                    ['column' => 'photo', 'name' => 'photo'],
                    ['column' => 'ad_product_group', 'name' => 'ad_product_group'],
                    ['column' => 'ad_vendor', 'name' => 'ad_vendor'],
                    ['column' => 'ad_brand', 'name' => 'ad_brand'],
                    ['column' => 'ad_part_number', 'name' => 'ad_part_number'],
                    ['column' => 'desc1', 'name' => 'desc1'],
                    ['column' => 'ad_category_name', 'name' => 'ad_category_name'],
                    ['column' => 'ad_category_name_1', 'name' => 'ad_category_name_1'],
                    ['column' => 'ad_category_name_2', 'name' => 'ad_category_name_2'],
                    ['column' => 'ad_category_name_3', 'name' => 'ad_category_name_3'],
                    ['column' => 'ad_category_name_4', 'name' => 'ad_category_name_4'],
                    ['column' => 'ad_category_name_5', 'name' => 'ad_category_name_5'],
                    ['column' => 'ad_short_desc', 'name' => 'ad_short_desc'],
                    ['column' => 'ad_long_desc', 'name' => 'ad_long_desc'],

                    // splitBy=" ?; ?" -> multivalued, split on "; " (with
                    // either side of the semicolon optionally spaced).
                    [
                        'column' => 'ad_keywords',
                        'name' => 'ad_keywords',
                        'transformer' => 'split',
                        'options' => ['pattern' => '/ ?; ?/'],
                    ],

                    // splitBy="\|\|" -> multivalued, split on literal "||".
                    [
                        'column' => 'ad_attributes',
                        'name' => 'ad_attributes',
                        'transformer' => 'split',
                        'options' => ['pattern' => '/\|\|/'],
                    ],

                    ['column' => 'ad_application', 'name' => 'ad_application'],
                    ['column' => 'search_category', 'name' => 'search_category'],
                    ['column' => 'ad_unspsc', 'name' => 'ad_unspsc'],
                    ['column' => 'del', 'name' => 'del'],
                    ['column' => 'price', 'name' => 'price'],
                    ['column' => 'in_stock', 'name' => 'in_stock'],
                    ['column' => 'netavail', 'name' => 'netavail'],
                    ['column' => 'part_number_keywords', 'name' => 'part_number_keywords'],
                    ['column' => 'video_links', 'name' => 'video_links'],
                    ['column' => 'image_filename_1', 'name' => 'image_filename_1'],
                    ['column' => 'image_filename_2', 'name' => 'image_filename_2'],
                    ['column' => 'image_filename_3', 'name' => 'image_filename_3'],
                    ['column' => 'image_filename_4', 'name' => 'image_filename_4'],
                    ['column' => 'image_filename_5', 'name' => 'image_filename_5'],
                    ['column' => 'product_description', 'name' => 'product_description'],
                    ['column' => 'terminal_category_code', 'name' => 'terminal_category_code'],
                    ['column' => 'terminal_category_name', 'name' => 'terminal_category_name'],
                    ['column' => 'custom_category_ids', 'name' => 'custom_category_ids'],
                    ['column' => 'leadtime', 'name' => 'leadtime'],
                ],
            ],
        ],
    ],
];
