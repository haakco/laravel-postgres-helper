# Docker Setup for Laravel PostgreSQL Helper

This Docker setup provides PostgreSQL databases for development and testing of the Laravel PostgreSQL Helper package.

## Quick Start

1. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

2. Start the Docker containers:
   ```bash
   composer docker:up
   # or
   docker-compose up -d
   ```

3. Run tests:
   ```bash
   composer test
   ```

4. Stop the containers:
   ```bash
   composer docker:down
   # or
   docker-compose down
   ```

## Services

### PostgreSQL Main Database
- **Container**: `laravel-postgres-helper-db`
- **Port**: 5432 (configurable via `DB_PGSQL_PORT`)
- **Database**: `laravel_postgres_helper`
- **Username**: `postgres`
- **Password**: `postgres`

### PostgreSQL Test Database
- **Container**: `laravel-postgres-helper-test-db`
- **Port**: 5433 (configurable via `DB_PGSQL_TEST_PORT`)
- **Database**: `laravel_postgres_helper_test`
- **Username**: `postgres`
- **Password**: `postgres`

## Features

Both PostgreSQL instances include:
- PostgreSQL 16 with Debian Bullseye base
- Extensions: TimescaleDB, PostGIS, pg_stat_statements, pg_cron, auto_explain
- Optimized for development (fsync=off, synchronous_commit=off)
- Health checks for container readiness
- Automatic SQL file execution from `tests/sql/` directory

## Configuration

### Environment Variables
- `DB_PGSQL_PORT`: Main database port (default: 5432)
- `DB_PGSQL_TEST_PORT`: Test database port (default: 5433)

### Database Optimization
The databases are configured with development-optimized settings:
- Disabled fsync and synchronous commit for faster performance
- Increased shared buffers and cache sizes
- Enabled query performance monitoring

**⚠️ WARNING**: These settings are for development only. NEVER use these in production!

## Troubleshooting

### Port Conflicts
If you have PostgreSQL already running locally, you can change the ports in your `.env` file:
```bash
DB_PGSQL_PORT=5434
DB_PGSQL_TEST_PORT=5435
```

### Connection Issues
Check container status:
```bash
docker-compose ps
docker-compose logs postgres
docker-compose logs postgres-test
```

### Reset Databases
To completely reset the databases:
```bash
docker-compose down -v
docker-compose up -d
```

This will remove all data and recreate fresh databases.