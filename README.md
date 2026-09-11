# solr-import-replacement

A small, standalone PHP package that replaces the functionality of Solr's
**Data Import Handler (DIH)**, which was deprecated in Solr 8 and removed
entirely in Solr 9. It is not a drop-in binary/config replacement (there
is no XML parity), but it reproduces the parts of DIH people actually
depend on:

| DIH concept                  | This package                                  |
|-------------------------------|------------------------------------------------|
| `data-config.xml`              | A PHP array returned from a config file (`config/example-data-config.php`) |
| `<entity>`                     | `SolrImport\Entity`                             |
| nested `<entity>` (sub-entity) | `children` key on an entity, merged as multivalued fields |
| `<field column= name=>`        | `fields` array on an entity                     |
| `<transformer>`                | `SolrImport\Transformers\*` (regex, date, template, script) |
| `deltaQuery` / `deltaImportQuery` | `deltaQuery` key + `Importer::deltaImport()` |
| `dataimport.properties`        | `SolrImport\StateStore` (JSON file by default)  |
| `command=full-import`          | `bin/import full-import`                        |
| `command=delta-import`         | `bin/import delta-import`                       |
| connection strings in `solrconfig.xml` / DIH config | `.env` (gitignored, see below) |

Indexing itself is done through [Solarium](https://solarium.readthedocs.io),
the standard PHP Solr client, so it works against any Solr 8/9+ instance
via the normal `/update` handler.

## Install

```bash
composer install
```

## Configure

Connection info and data-shape config are split on purpose, so credentials
never end up in version control:

- **`.env`** — DB and Solr connection details. Copy `.env.example` to `.env`
  and fill in real values; `.env` is gitignored.
- **`config/*.php`** — entities, queries, field mappings, transformers. This
  is safe to commit since it contains no secrets, only reads them via
  `Env::get(...)`.

```bash
cp .env.example .env
# then edit .env with your real DB_DSN / DB_USER / DB_PASSWORD / SOLR_HOST / SOLR_PORT / SOLR_CORE
```

`bin/import` loads `.env` automatically (via `SolrImport\Env::load()`)
before building the datasource connection or the Solarium client. Real
environment variables (e.g. set by your process manager, Docker, or CI)
always take precedence over whatever is in `.env`.

Then copy `config/example-data-config.php` and edit the entity queries
and field mappings for your schema. Each entity needs:

- `query` — main SELECT used for full imports (and re-run, filtered by
  primary key, during delta imports)
- `deltaQuery` — SELECT returning just the primary keys that changed since
  `:last_index_time` (omit this to always do a full re-import of that entity)
- `pk` — primary key column name
- `fields` — column → Solr field mappings, each optionally passed through
  a named transformer
- `children` — optional nested entities, filtered by `:parent_pk`, merged
  into the parent doc as multivalued fields

## Run

```bash
# Full re-index, like /dataimport?command=full-import
bin/import full-import --config=config/my-config.php --core=products

# Incremental, like /dataimport?command=delta-import
bin/import delta-import --config=config/my-config.php --core=products
```

`SOLR_HOST` / `SOLR_PORT` / `SOLR_CORE` come from `.env` (defaults:
`localhost` / `8983` / `default`); `--core=` on the CLI overrides
`SOLR_CORE` for a one-off run. State (last successful import time per
entity) is stored in `var/state.json` by default — pass `--state=` to
change that, or swap `StateStore` for a DB-backed implementation if you
run imports from multiple hosts.

## Transformers

Register your own by implementing `TransformerInterface` and calling
`TransformerRegistry::register('name', $instance)` before constructing
`Importer`. Built-in ones: `regex`, `date`, `template`, `script` (the
`script` transformer takes a plain PHP callable instead of DIH's
embedded JavaScript — much easier to test).

## Notes / limitations

- No XML config compatibility — you re-express your existing
  `data-config.xml` as the PHP array shown in the example.
- Delta detection here is timestamp-based (`updated_at > :last_index_time`),
  the same pattern most DIH configs used. If your source doesn't have a
  reliable "last modified" column, look at CDC or a queue-based approach
  instead of polling deltas.
- The delta re-query approach (`SELECT * FROM (<query>) WHERE pk IN (...)`)
  assumes your database supports subqueries in FROM; adjust if not.
- This is intentionally minimal — no dependency injection container, no
  YAML support, no built-in scheduler. Wire `bin/import delta-import` into
  cron/systemd-timer/Kubernetes CronJob for scheduling, exactly as people
  used to do with DIH's own polling or an external cron hitting `/dataimport`.
