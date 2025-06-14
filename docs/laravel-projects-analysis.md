# Laravel Projects Analysis Report - HaakCo Ecosystem

## Executive Summary

This report analyzes Laravel projects across the HaakCo ecosystem to identify patterns, inconsistencies, and opportunities for standardization. The analysis covers composer scripts, Docker setup, testing approaches, directory structures, and configuration files.

## Projects Analyzed

### Main Laravel Projects
1. **courier/api** - Laravel 12, delivery management API
2. **TrackLab/tl-api** - Laravel 11, solar tracking systems backend  
3. **lucidview/lv-api** - Laravel 10, network monitoring API
4. **HaakCo/AiProjects/eloquent-generator** - Laravel library for model generation
5. **HaakCo/AiProjects/php-code-mcp** - Laravel 10, MCP server for PHP code analysis
6. **HaakCo/AiProjects/BackgroundTestRunner** - Laravel 12, automated testing infrastructure
7. **HaakCo/AiProjects/laravel-postgres-helper** - Laravel library for PostgreSQL helpers

## Key Findings

### 1. Composer Scripts Inconsistencies

#### Standard Pattern (Found in courier/api, TrackLab/tl-api)
```json
{
  "lint": [
    "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes --verbose",
    "vendor/bin/rector process --no-diffs",
    "vendor/bin/phpstan analyse --no-progress"
  ],
  "lint-check": [
    "vendor/bin/phpstan analyse --no-progress",
    "vendor/bin/rector process --dry-run"
  ],
  "test": ["php -d xdebug.mode=off vendor/bin/phpunit"]
}
```

#### Variations Found
- **lucidview/lv-api**: Uses different linting approach with `fix-code-format` and ECS instead of PHP-CS-Fixer
- **php-code-mcp**: Uses Pint instead of PHP-CS-Fixer, has `fix-all` pattern
- **BackgroundTestRunner**: Uses both PHP-CS-Fixer and has well-organized scripts with `check-all`, `fix-all` patterns

### 2. Docker Setup Patterns

#### Projects with Dockerfile
- **courier/api**: Has Dockerfile (production ready)
- **lucidview/lv-api**: Has Dockerfile
- **TrackLab/tl-api**: No Dockerfile found
- **Other Laravel libraries**: No Dockerfiles (expected for libraries)

#### Docker Compose
- Most projects rely on external docker-compose setups or Laravel Sail
- courier/api references `../infra/local_stack/docker-compose.yml`

### 3. Testing Approaches

#### Standard Pattern
All projects correctly use `DatabaseTransactions` trait (✅ Good):
```php
use Illuminate\Foundation\Testing\DatabaseTransactions;
```

#### Testing Commands Variations
- **Standard**: `vendor/bin/phpunit`
- **With optimization**: `php -d xdebug.mode=off vendor/bin/phpunit`
- **With Pest**: `vendor/bin/pest`
- **Parallel testing**: `php artisan test --parallel`

### 4. Configuration Files

#### PHP-CS-Fixer Configuration
- **courier/api & TrackLab/tl-api**: Have comprehensive .php-cs-fixer.dist.php with parallel support
- **lucidview/lv-api**: Uses ECS (Easy Coding Standard) instead
- Both approaches achieve similar results but create inconsistency

#### PHPStan Configuration
- **courier/api**: Extensive phpstan.neon with 174 lines, many custom ignores
- **TrackLab/tl-api**: Simpler phpstan.neon with 59 lines
- **Libraries**: Minimal or no phpstan.neon files

#### Rector Configuration
- **courier/api**: Comprehensive rector.php with Laravel-specific rules
- **Other projects**: Similar but less comprehensive configurations

### 5. Directory Structure

All projects follow standard Laravel structure ✅:
```
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
tests/
```

### 6. Missing Standard Tooling

#### Projects Missing justfile
- TrackLab/tl-api
- lucidview/lv-api
- Most library projects

#### Projects Missing .php-cs-fixer.dist.php
- lucidview/lv-api (uses ECS instead)

## Good Patterns to Standardize

### 1. Composer Scripts Structure (BackgroundTestRunner Pattern)
```json
{
  "check-all": ["@cs-check", "@phpstan", "@test"],
  "fix-all": ["@cs-fix", "@phpstan", "@test"],
  "cs-check": "php-cs-fixer fix --dry-run --diff --verbose",
  "cs-fix": "php-cs-fixer fix --verbose",
  "phpstan": "phpstan analyse --memory-limit=2G",
  "rector": "rector process --dry-run",
  "rector-fix": "rector process",
  "test": "@php artisan test --parallel"
}
```

### 2. Model Generation Scripts (courier/api Pattern)
```json
{
  "create-models": [
    "./artisan migrate --step",
    "find app/Models -maxdepth 1 -type f -name '*.php' ! -name 'Company.php' -delete",
    "php artisan modelEnum:create",
    "php artisan eloquent-generator:generate-models",
    "find app/Models/Enums -maxdepth 1 -type f -name '*.php' -delete",
    "php artisan modelEnum:create",
    "./scripts/development/fixModels.sh",
    "composer update-ide",
    "composer lint"
  ]
}
```

### 3. Git Hooks Integration
Most projects use `brainmaestro/composer-git-hooks` with pre-commit linting ✅

### 4. Comprehensive Error Ignoring (courier/api PHPStan)
Well-documented ignore patterns with explanations

## Recommendations for Standardization

### 1. Create Laravel Project Template
Create a standard Laravel project template with:
- Standardized composer.json scripts
- Common .php-cs-fixer.dist.php configuration
- Base phpstan.neon with common ignores
- Standard rector.php configuration
- justfile with common tasks
- Docker setup templates

### 2. Standardize Linting Tools
Choose one approach:
- **Recommended**: PHP-CS-Fixer + PHPStan + Rector (most common)
- Deprecate ECS usage in lucidview/lv-api

### 3. Create Shared Configuration Package
Consider creating `haakco/laravel-dev-tools` package with:
- Shared PHP-CS-Fixer rules
- Shared PHPStan baseline
- Shared Rector rules
- Standard composer scripts

### 4. Standardize Testing Commands
```json
{
  "test": "php -d xdebug.mode=off vendor/bin/phpunit",
  "test-parallel": "php artisan test --parallel --processes=20",
  "test-coverage": "php -d xdebug.mode=coverage vendor/bin/pest --coverage"
}
```

### 5. Add Missing Tooling
- Add justfile to TrackLab/tl-api and lucidview/lv-api
- Add Dockerfile templates for projects needing containerization
- Ensure all projects have proper phpstan.neon configuration

### 6. Document Standards
Create a `LARAVEL_STANDARDS.md` in the docs/shared directory covering:
- Required composer scripts
- Configuration file standards
- Testing approach
- Model generation workflow
- Deployment patterns

## Immediate Action Items

1. **High Priority**
   - Standardize lucidview/lv-api to use PHP-CS-Fixer instead of ECS
   - Add justfile to TrackLab/tl-api and lucidview/lv-api
   - Create shared configuration templates

2. **Medium Priority**
   - Align all phpstan.neon files with common baseline
   - Standardize rector.php configurations
   - Update all projects to use standardized composer scripts

3. **Low Priority**
   - Add Dockerfiles where missing (for non-library projects)
   - Document all patterns in shared documentation
   - Consider creating laravel-dev-tools package

## Conclusion

The Laravel projects in the HaakCo ecosystem show good adherence to Laravel standards but lack consistency in development tooling. The main areas for improvement are:

1. Standardizing linting and code quality tools
2. Consistent composer script naming and functionality
3. Shared configuration files
4. Better documentation of standards

By implementing these recommendations, the development experience across all Laravel projects will be more consistent and efficient.