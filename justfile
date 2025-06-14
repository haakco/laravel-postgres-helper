#!/usr/bin/env just --justfile

# Default recipe to display help
default:
    @just --list

# Install composer dependencies
install:
    composer install --no-interaction --prefer-dist --optimize-autoloader

# Update composer dependencies
update:
    composer update --no-interaction --prefer-dist --optimize-autoloader

# Start Docker containers
up:
    docker-compose up -d
    @echo "Waiting for PostgreSQL to be ready..."
    @sleep 5
    @echo "PostgreSQL containers are ready!"

# Stop Docker containers
down:
    docker-compose down

# Restart Docker containers
restart: down up

# Reset Docker containers and volumes
reset:
    docker-compose down -v
    docker-compose up -d
    @echo "Databases have been reset!"

# Run all tests
test:
    composer test

# Run tests with coverage
test-coverage:
    composer test -- --coverage-html coverage

# Run linting and auto-fix issues
lint:
    composer lint

# Check linting without fixing
lint-check:
    composer lint-check

# Format code with PHP-CS-Fixer
format:
    composer format

# Check formatting without fixing
format-check:
    composer format-check

# Run PHPStan analysis
phpstan:
    composer phpstan

# Run Rector
rector:
    composer rector

# Check all (lint-check + test)
check-all:
    composer check-all

# Fix all issues and run tests
fix-all: lint test
    @echo "All fixes applied and tests passed!"

# Clear all caches
clear:
    rm -rf .phpunit.cache
    rm -rf .php-cs-fixer.cache
    rm -rf .phpstan
    rm -rf vendor
    composer install

# View Docker logs
logs:
    docker-compose logs -f

# Access PostgreSQL main database
db:
    docker exec -it laravel-postgres-helper-db psql -U postgres -d laravel_postgres_helper

# Access PostgreSQL test database
db-test:
    docker exec -it laravel-postgres-helper-test-db psql -U postgres -d laravel_postgres_helper_test

# Check Docker container status
status:
    docker-compose ps

# Build and tag a new release
release version:
    @echo "Preparing release {{version}}..."
    @echo "Running tests..."
    @just test
    @echo "Running linting..."
    @just lint-check
    git tag -a v{{version}} -m "Release version {{version}}"
    @echo "Release v{{version}} tagged. Don't forget to push: git push origin v{{version}}"

# Run a specific test file
test-file file:
    composer test -- {{file}}

# Run tests in a specific directory
test-dir dir:
    composer test -- tests/{{dir}}

# Generate documentation
docs:
    @echo "Generating documentation..."
    @echo "Documentation generation not configured for this package yet"

# Validate composer.json
validate:
    composer validate --strict

# Show outdated dependencies
outdated:
    composer outdated --direct