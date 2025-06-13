# Laravel PostgreSQL Helper

A comprehensive PostgreSQL utility package for Laravel applications that provides automatic sequence management, trigger automation, and database health monitoring.

## Installation

```bash
composer require haakco/laravel-postgres-helper
```

## Requirements

- PHP 8.3+
- Laravel 10, 11, or 12
- PostgreSQL 13+

## Testing

### Prerequisites

The integration tests require a PostgreSQL database. You have two options:

#### Option 1: Local PostgreSQL

1. Create a test database:
```bash
createdb postgres_helper_test
```

2. Copy the test environment file:
```bash
cp .env.testing.example .env.testing
```

3. Update `.env.testing` with your database credentials:
```
DB_TEST_HOST=127.0.0.1
DB_TEST_PORT=5432
DB_TEST_DATABASE=postgres_helper_test
DB_TEST_USERNAME=postgres
DB_TEST_PASSWORD=your_password
```

#### Option 2: Docker

```bash
docker run --name postgres-test -e POSTGRES_DB=postgres_helper_test -e POSTGRES_PASSWORD=postgres -p 5432:5432 -d postgres:16
```

### Running Tests

```bash
# Run all tests
composer test

# Run only unit tests (no database required)
./vendor/bin/phpunit --testsuite Unit

# Run with coverage
composer test -- --coverage-html coverage
```

## Usage

### Basic Usage

```php
use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;

// Fix all sequences after inserting records with explicit IDs
PgHelperLibrary::fixAll();

// Fix only specific tables (much faster)
PgHelperLibrary::fixSequences(['users', 'permissions']);

// Fix triggers for tables with updated_at columns
PgHelperLibrary::fixTriggers(['users', 'posts']);
```

### Health Checks

```php
// Run comprehensive health check
$health = PgHelperLibrary::runHealthCheck();
echo "Database health: {$health['overall_score']}%";

// Validate table structure
$validation = PgHelperLibrary::validateStructure();
if (!$validation['valid']) {
    // Handle validation errors
}
```

## License

MIT