<?php

declare(strict_types=1);

namespace SolrImport;

/**
 * Deliberately minimal .env loader - no external dependency, just enough
 * to keep connection secrets (DB credentials, Solr host) out of the
 * config file and out of version control. Existing environment variables
 * (e.g. set by your process manager or CI) always take precedence over
 * whatever is in the .env file.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_file($path)) {
            self::$loaded = true;
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip matching surrounding quotes, e.g. FOO="bar baz"
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[-1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Don't clobber a value already set in the real environment.
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }
}
