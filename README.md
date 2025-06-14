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

The integration tests require a PostgreSQL database. You have three options:

#### Option 1: Docker Compose (Recommended)

```bash
# Start the test database
just up
# or
docker-compose up -d

# Database is available at:
# Main DB: localhost:5432 (laravel_postgres_helper)
# Test DB: localhost:5433 (laravel_postgres_helper_test)
```

#### Option 2: Justfile Commands

```bash
# Complete setup with database
just setup

# Or just reset the database
just db-reset
```

#### Option 3: Manual Setup

1. Copy the test environment file:
```bash
cp .env.testing.example .env.testing
```

2. The default configuration uses Docker Compose settings:
```
DB_TEST_HOST=127.0.0.1
DB_TEST_PORT=5433
DB_TEST_DATABASE=laravel_postgres_helper_test
DB_TEST_USERNAME=postgres
DB_TEST_PASSWORD=postgres
```

### Running Tests

```bash
# Run all tests
composer test
# or
just test

# Run only unit tests (no database required)
composer test-unit
# or
just test-unit

# Run only integration tests
composer test-integration
# or
just test-integration

# Run with coverage
composer test-coverage
# or
just test-coverage

# Run tests with automatic database setup
just test-with-db
```

## Development Commands

### Justfile Commands

```bash
# Install dependencies
just install

# Linting and formatting
just lint          # Auto-fix all issues
just lint-check    # Check without fixing
just format        # PHP-CS-Fixer only
just phpstan       # PHPStan analysis only

# Testing
just test          # Run all tests
just test-unit     # Unit tests only
just test-integration # Integration tests only
just test-coverage # Generate coverage report

# Database management
just up            # Start Docker containers
just down          # Stop Docker containers
just db-reset      # Reset test database
just db            # Access main PostgreSQL
just db-test       # Access test PostgreSQL

# Quality assurance
just fix-all       # Lint and test
just check-all     # Check everything
just pre-commit    # Pre-commit checks

# Development workflow
just setup         # Complete setup
just dev           # Quick fix-all cycle
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