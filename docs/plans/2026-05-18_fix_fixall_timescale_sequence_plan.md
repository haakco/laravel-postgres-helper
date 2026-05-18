# Fix PgHelperLibrary fixAll Timescale and Sequence Bugs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Make `PgHelperLibrary::fixAll()` safe for non-public extension schemas and for tables whose maximum explicit ID is below `1`.

**Architecture:** Keep the fix in the package SQL function definitions so fresh installs and package migrations generate the same safe PostgreSQL functions. Add a new package migration to update existing consuming applications that already ran the previous helper-function migrations.

**Tech Stack:** PHP 8.4, Laravel package, Orchestra Testbench, PostgreSQL PL/pgSQL, PHPUnit 11.

---

## Current State Verified

**Last verified:** 2026-05-18

**Files examined:**
- `src/Libraries/sql/000010_update_date_columns_default.sql` - scans all `information_schema.tables` base tables with `created_at` or `updated_at` timestamp columns and does not restrict to `public`.
- `src/Libraries/sql/000030_updated_at_column_for_tables.sql` - scans all `information_schema.tables` base tables with `updated_at` and does not restrict to `public`.
- `src/Libraries/sql/000040_fix_all_seq.sql` - uses `COALESCE(MAX(id) + 1, 1)`, which can produce `0` when `MAX(id)` is `-1`.
- `src/Libraries/sql/000050_fix_db.sql` - calls `update_updated_at_column_for_tables()`, `update_date_columns_default()`, and `fix_all_seq()`.
- `database/migrations/1800_02_03_000001_fix_identifier_quoting_in_helper_functions.php` - latest package migration that recreates the helper SQL functions in existing apps.
- `tests/Integration/PgHelperLibraryTest.php` - existing integration coverage for `PgHelperLibrary::fixAll()`, sequence fixing, trigger creation, and structure validation.
- `docker-compose.yml` - test DB service uses `ghcr.io/haakco/postgresql:18-standard-trixie` with `postgres_test_data` mounted at `/var/lib/postgresql/data`.

**Key findings:**
- TrackLab local `PgHelperLibrary::fixAll()` failed with `setval: value 0 is out of bounds for sequence "collectors_id_seq"` because the live package function used `COALESCE(MAX(id) + 1, 1)`.
- TrackLab schema dump already contains the safer sequence expression: `GREATEST(COALESCE(MAX(id) + 1, 1), 1)`.
- TrackLab QA failure against `_timescaledb_catalog.continuous_aggs_jobs_refresh_ranges` is explained by the two timestamp helper functions scanning non-public schemas.
- Restricting the SQL helper functions to `t.table_schema = 'public'` matches the package's PHP helper methods, which already query only the `public` schema in `src/Libraries/Traits/PostgresQueries.php`.
- Package test DB did not start in this workspace because the PostgreSQL 18 image rejected the existing volume layout mounted at `/var/lib/postgresql/data`. Do not use destructive Docker volume cleanup without coordinator approval.

**Shared branch coordination:**
- Other work exists in this repo: `composer.lock` is modified and was not touched by this plan author.
- Teams must not use `git stash`, `git reset`, checkout-overwrites, or volume/data cleanup commands without coordinator approval.
- Stage only files changed for this fix.

**Security review trigger:** Yes. This changes database helper behavior across consuming apps and intentionally avoids touching extension-owned schemas.

**Deferred Work:** None.

---

### Task 1: Add Failing Coverage for Sequence Lower Bound

**Files:**
- Modify: `tests/Integration/PgHelperLibraryTest.php`

**Team ownership:** Package test team owns `tests/Integration/PgHelperLibraryTest.php`.

- [x] **Step 1: Add the failing test**

Add this method after `test_it_handles_fix_all_method_for_backward_compatibility()`:

```php
public function test_fix_all_keeps_sequences_at_or_above_one_when_table_contains_negative_ids(): void
{
    DB::table('test_standard')->truncate();
    DB::table('test_standard')->insert(['id' => -1, 'name' => 'Negative ID']);

    PgHelperLibrary::fixAll();

    $nextId = DB::selectOne("SELECT nextval('test_standard_id_seq') AS next_id");

    self::assertNotNull($nextId);
    self::assertGreaterThanOrEqual(1, $nextId->next_id);
}
```

- [x] **Step 2: Verify the test fails for the current bug**

Run:

```bash
vendor/bin/phpunit tests/Integration/PgHelperLibraryTest.php --filter test_fix_all_keeps_sequences_at_or_above_one_when_table_contains_negative_ids
```

Expected: FAIL with a PostgreSQL `setval: value 0 is out of bounds` error.

If the test DB is not reachable on port `5433`, first resolve the Docker test DB issue in Task 6.

---

### Task 2: Add Failing Coverage for Non-Public Schema Exclusion

**Files:**
- Modify: `tests/Integration/PgHelperLibraryTest.php`

**Team ownership:** Package test team owns `tests/Integration/PgHelperLibraryTest.php`.

- [x] **Step 1: Add teardown cleanup**

Update `tearDown()` to drop the schema created by the new test:

```php
#[\Override]
protected function tearDown(): void
{
    DB::statement('DROP SCHEMA IF EXISTS "_timescaledb_catalog" CASCADE');
    $this->dropTestTables();
    parent::tearDown();
}
```

- [x] **Step 2: Add the failing test**

Add this method after the sequence lower-bound test:

```php
public function test_fix_all_ignores_non_public_schema_tables(): void
{
    DB::statement('CREATE SCHEMA IF NOT EXISTS "_timescaledb_catalog"');
    DB::statement('CREATE TABLE "_timescaledb_catalog"."continuous_aggs_jobs_refresh_ranges" (id BIGSERIAL PRIMARY KEY, created_at TIMESTAMPTZ NULL)');

    PgHelperLibrary::fixAll();

    $column = DB::selectOne("
        SELECT column_default
        FROM information_schema.columns
        WHERE table_schema = '_timescaledb_catalog'
          AND table_name = 'continuous_aggs_jobs_refresh_ranges'
          AND column_name = 'created_at'
    ");

    self::assertNotNull($column);
    self::assertNull($column->column_default);
}
```

- [x] **Step 3: Verify the test fails for the current bug**

Run:

```bash
vendor/bin/phpunit tests/Integration/PgHelperLibraryTest.php --filter test_fix_all_ignores_non_public_schema_tables
```

Expected: FAIL because `fixAll()` mutates the non-public schema table by setting `created_at` default to `now()`.

---

### Task 3: Fix SQL Helper Function Definitions

**Files:**
- Modify: `src/Libraries/sql/000010_update_date_columns_default.sql`
- Modify: `src/Libraries/sql/000030_updated_at_column_for_tables.sql`
- Modify: `src/Libraries/sql/000040_fix_all_seq.sql`

**Team ownership:** SQL function team owns only `src/Libraries/sql/*.sql` listed above.

- [x] **Step 1: Restrict date defaults to public schema**

In `src/Libraries/sql/000010_update_date_columns_default.sql`, update the `WHERE` clause to:

```sql
    WHERE
      t.table_type = 'BASE TABLE'
      AND t.table_schema = 'public'
```

- [x] **Step 2: Restrict updated_at triggers to public schema**

In `src/Libraries/sql/000030_updated_at_column_for_tables.sql`, update the `WHERE` clause to:

```sql
    WHERE
      tr.event_object_table IS NULL
      AND t.table_type = 'BASE TABLE'
      AND t.table_schema = 'public'
```

- [x] **Step 3: Guard sequence reset against values below one**

In `src/Libraries/sql/000040_fix_all_seq.sql`, replace:

```sql
        ', COALESCE(MAX(' || QUOTE_IDENT(c.attname) || ') + 1, 1),FALSE) FROM ' ||
```

with:

```sql
        ', GREATEST(COALESCE(MAX(' || QUOTE_IDENT(c.attname) || ') + 1, 1), 1), FALSE) FROM ' ||
```

- [x] **Step 4: Run focused tests**

Run:

```bash
vendor/bin/phpunit tests/Integration/PgHelperLibraryTest.php --filter 'test_fix_all_keeps_sequences_at_or_above_one_when_table_contains_negative_ids|test_fix_all_ignores_non_public_schema_tables'
```

Expected: PASS.

---

### Task 4: Add Upgrade Migration for Existing Consumers

**Files:**
- Create: `database/migrations/1800_02_04_000001_fix_internal_schema_exclusions_and_safe_sequences.php`

**Team ownership:** Migration team owns only the new migration file.

- [x] **Step 1: Create the migration**

Create `database/migrations/1800_02_04_000001_fix_internal_schema_exclusions_and_safe_sequences.php` with:

```php
<?php

declare(strict_types=1);

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recreates helper functions so fixAll() ignores non-public extension schemas
     * and never resets sequences below PostgreSQL's valid lower bound.
     */
    public function up(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        DB::statement("COMMENT ON FUNCTION public.fix_db() IS 'Updated by laravel-postgres-helper v4.0.10 on {$timestamp} - exclude internal schemas and guard sequence lower bound'");

        PgHelperLibrary::updateDateColumnsDefault();
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Functions use CREATE OR REPLACE. Previous versions would need to be restored manually.
    }
};
```

- [x] **Step 2: Verify migration syntax**

Run:

```bash
php -l database/migrations/1800_02_04_000001_fix_internal_schema_exclusions_and_safe_sequences.php
```

Expected: `No syntax errors detected`.

---

### Task 5: Fix Auto-Apply Standards Shadowed Variable

**Files:**
- Modify: `src/Libraries/sql/000060_auto_apply_standards.sql`

**Team ownership:** SQL function team owns `src/Libraries/sql/000060_auto_apply_standards.sql`.

- [x] **Step 1: Fix the shadowed predicate**

In `src/Libraries/sql/000060_auto_apply_standards.sql`, replace the local variable name and all uses in `auto_apply_table_standards()`.

Replace:

```sql
    table_name text;
```

with:

```sql
    target_table_name text;
```

Replace:

```sql
        table_name := split_part(obj.object_identity, '.', 2);
```

with:

```sql
        target_table_name := split_part(obj.object_identity, '.', 2);
```

Replace:

```sql
        IF table_name IS NULL OR 
           table_name LIKE 'pg_%' OR 
           table_name LIKE 'sql_%' OR
           obj.schema_name != 'public' THEN
            CONTINUE;
        END IF;
```

with:

```sql
        IF target_table_name IS NULL OR
           target_table_name LIKE 'pg_%' OR
           target_table_name LIKE 'sql_%' OR
           obj.schema_name != 'public' THEN
            CONTINUE;
        END IF;
```

Replace:

```sql
        SELECT EXISTS (
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = table_name 
            AND column_name = 'updated_at'
        ) INTO has_updated_at;
```

with:

```sql
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = target_table_name
              AND column_name = 'updated_at'
        ) INTO has_updated_at;
```

Replace:

```sql
                table_name, table_name
```

with:

```sql
                target_table_name, target_table_name
```

Replace:

```sql
            RAISE NOTICE 'Applied updated_at trigger to table: %', table_name;
```

with:

```sql
            RAISE NOTICE 'Applied updated_at trigger to table: %', target_table_name;
```

Replace:

```sql
        PERFORM fix_sequence_for_table(table_name);
```

with:

```sql
        PERFORM fix_sequence_for_table(target_table_name);
```

---

### Task 6: Restore Package Test Database Availability

**Files:**
- Inspect only: `docker-compose.yml`

**Team ownership:** Validation team owns test environment investigation. Do not change Docker volumes or compose config without coordinator approval.

- [x] **Step 1: Check current container state**

Run:

```bash
docker compose ps postgres-test
docker logs --tail=80 laravel-postgres-helper-test-db
```

Expected if broken: PostgreSQL 18 reports the existing data volume is mounted at `/var/lib/postgresql/data` with an incompatible layout.

- [x] **Step 2: Ask coordinator before destructive cleanup**

If the PostgreSQL 18 volume-layout error appears, ask before running any command that deletes Docker volumes or database data.

Recommended coordinator-approved cleanup command:

```bash
docker compose down -v
docker compose up -d postgres-test
```

- [x] **Step 3: Confirm test DB is reachable**

Run:

```bash
until docker exec laravel-postgres-helper-test-db pg_isready -U postgres; do sleep 1; done
```

Expected: `accepting connections`.

---

### Task 7: Full Validation

**Files:**
- Validate all changed files.

**Team ownership:** Final validator owns full suite and lint.

- [x] **Step 1: Run focused integration test**

Run:

```bash
vendor/bin/phpunit tests/Integration/PgHelperLibraryTest.php
```

Expected: PASS.

- [x] **Step 2: Run full test suite**

Run:

```bash
composer test
```

Expected: PASS.

- [x] **Step 3: Run full lint**

Run:

```bash
composer lint
```

Expected: PASS with zero PHP-CS-Fixer, Rector, or PHPStan failures.

- [x] **Step 4: Check git status**

Run:

```bash
git status --short
```

Expected changed files for this work:

```text
?? database/migrations/1800_02_04_000001_fix_internal_schema_exclusions_and_safe_sequences.php
M src/Libraries/sql/000010_update_date_columns_default.sql
M src/Libraries/sql/000030_updated_at_column_for_tables.sql
M src/Libraries/sql/000040_fix_all_seq.sql
M src/Libraries/sql/000060_auto_apply_standards.sql
M tests/Integration/PgHelperLibraryTest.php
```

Do not stage unrelated files such as a pre-existing `composer.lock` modification unless the coordinator explicitly assigns it.

---

## Review Requirements

- Stage 1 Spec Compliance Review: confirm both reported failures are covered and no unrelated behavior was added.
- Stage 2 Code Quality Review: confirm SQL is simple, schema restrictions match existing PHP helper assumptions, and tests are behavior-focused.
- Stage 3 Security Review: required because this changes database mutation boundaries. Confirm extension/internal schemas are not mutated and public app tables still receive standards.

## Completion Criteria

- `PgHelperLibrary::fixAll()` does not mutate non-public schema tables.
- `PgHelperLibrary::fixAll()` does not attempt `setval(..., 0, ...)`.
- Existing consumers receive corrected helper functions through a new package migration.
- `vendor/bin/phpunit tests/Integration/PgHelperLibraryTest.php` passes.
- `composer test` passes.
- `composer lint` passes.
