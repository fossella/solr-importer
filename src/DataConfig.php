<?php

declare(strict_types=1);

namespace SolrImport;

/**
 * Loads the data config - a plain PHP file returning an array - which is
 * this package's equivalent of DIH's data-config.xml. Using a PHP array
 * instead of XML means you can embed closures directly (see
 * Transformers\ScriptTransformer) without any templating hacks.
 */
final class DataConfig
{
    /** @var Entity[] */
    public readonly array $entities;

    /**
     * @param array{dsn:string,user?:string,pass?:string} $datasource
     */
    public function __construct(
        public readonly array $datasource,
        array $entityConfigs,
    ) {
        $this->entities = array_map(
            static fn (array $c) => Entity::fromArray($c),
            $entityConfigs
        );
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Data config not found: {$path}");
        }

        /** @var array{datasource:array,document:array{entities:array}} $config */
        $config = require $path;

        if (!isset($config['datasource'], $config['document']['entities'])) {
            throw new \RuntimeException(
                "Data config must return ['datasource' => ..., 'document' => ['entities' => ...]]"
            );
        }

        return new self($config['datasource'], $config['document']['entities']);
    }

    public function getEntity(string $name): ?Entity
    {
        foreach ($this->entities as $entity) {
            if ($entity->name === $name) {
                return $entity;
            }
        }

        return null;
    }
}
