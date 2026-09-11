<?php

declare(strict_types=1);

namespace SolrImport;

/**
 * Persists the "last successful import" timestamp per entity, the same
 * job DIH's dataimport.properties file does. Backed by a plain JSON file
 * so it has zero external dependencies; swap in a DB-backed implementation
 * if you run imports from multiple hosts.
 */
final class StateStore
{
    /** @var array<string,string> */
    private array $state = [];

    public function __construct(private readonly string $path)
    {
        if (is_file($this->path)) {
            $raw = file_get_contents($this->path);
            $this->state = $raw ? (json_decode($raw, true) ?: []) : [];
        }
    }

    public function getLastIndexTime(string $entityName): ?string
    {
        return $this->state[$entityName] ?? null;
    }

    public function setLastIndexTime(string $entityName, string $timestamp): void
    {
        $this->state[$entityName] = $timestamp;
        $this->save();
    }

    private function save(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->path, json_encode($this->state, JSON_PRETTY_PRINT));
    }
}
