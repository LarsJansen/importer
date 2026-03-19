# Curlie Importer Lab

A standalone local-only PHP app for importing Curlie TSV data into a separate `importer` database, reviewing the staged data, and preparing exports for the live directory project.

## Local target

- URL: `http://importer`
- Database: `importer`

## Project layout

- `app/` lightweight MVC-style app
- `public/` web root
- `database/schema.sql` schema for the importer DB
- `scripts/import_curlie_tsv.php` CLI TSV importer
- `scripts/export_ready_sql.php` starter export generator
- `storage/exports/` generated exports

## Setup

1. Extract this project to a separate folder, for example:
   `C:\wamp64\www\importer`
2. Import `database/schema.sql` into MariaDB/MySQL.
3. Confirm DB credentials in `config/config.php`.
4. Point your local vhost or alias so `http://importer` serves the `public` folder.

## CLI import examples

Import a whole folder containing matching `*-s.tsv` and `*-c.tsv` files:

```bash
C:\wamp64\bin\php\php8.3.0\php.exe scripts\import_curlie_tsv.php --dir="C:\curlie-data"
```

Import one explicit category file and one explicit site file:

```bash
C:\wamp64\bin\php\php8.3.0\php.exe scripts\import_curlie_tsv.php --categories="C:\curlie-data\rdf-Society-s.tsv" --sites="C:\curlie-data\rdf-Society-c.tsv"
```

## Current scope

This first build does:

- import categories from `*-s.tsv`
- import sites from `*-c.tsv`
- derive path metadata and local path candidates
- link sites to imported category IDs
- flag missing category links
- flag duplicate normalized URLs within a batch run
- show imported data in a Bootstrap UI

## Next recommended improvements

- edit mapping UI for `local_path_final`
- export true `INSERT` statements for the live directory schema
- batch-level filters and detail counts
- selective export by top branch
- site validation / checker integration before export


## Phase 2 additions

- Bulk approve/reject/reset actions on Categories and Sites
- Pagination controls (25/50/100/250 rows)
- Filters for branch, status, category path, and URL/title
- Included public/.htaccess for Apache rewrite support
