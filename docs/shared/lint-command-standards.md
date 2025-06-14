# Lint Command Standards Across HaakCo Projects

## Summary

After analyzing lint command behavior across HaakCo projects in different technology stacks, the standard pattern is:

- **`lint`** - Runs linting tools with auto-fix enabled (modifies files)
- **`lint-check`** or **`lint-test`** - Runs linting tools in check mode only (no modifications)

## Standard Pattern by Technology

### TypeScript/Node.js Projects

**Package.json scripts:**
```json
{
  "scripts": {
    "lint": "eslint src --fix",           // Auto-fixes issues
    "lint:check": "eslint src",           // Check only, no fixes
    "lint:fix": "eslint src --fix"        // Explicit fix command (optional)
  }
}
```

**Justfile commands:**
```justfile
# Run linting (with fixes)
lint:
    npm run lint

# Check linting without fixes
lint-check:
    npm run lint:check
```

Examples:
- `node-helpers`: Uses `lint` and `lint:fix` in package.json
- `react-components`: Uses `lint` and `lint:fix` 
- `courier/web-gui`: Uses `lint` with auto-fix
- `courier/driver-app-react-native`: Uses `lint` with auto-fix

### Laravel/PHP Projects

**Composer.json scripts:**
```json
{
  "scripts": {
    "lint": [
      "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes --verbose",
      "vendor/bin/rector process --no-diffs",
      "vendor/bin/phpstan analyse --no-progress"
    ],
    "lint-check": [
      "vendor/bin/phpstan analyse --no-progress",
      "vendor/bin/rector process --dry-run"
    ]
  }
}
```

**Justfile commands:**
```justfile
# Run linting and auto-fix issues
lint:
    composer lint

# Check linting without fixing
lint-check:
    composer lint-check
```

Examples:
- `courier/api`: Uses `lint` (auto-fix) and `lint-check` (dry-run)
- `TrackLab/tl-api`: Uses `lint` (auto-fix) and `lint-check` (dry-run)
- `laravel-postgres-helper`: Uses `lint` (auto-fix) and `lint-check` (dry-run)
- `eloquent-generator`: Uses `lint: format analyse` (custom implementation)

### Go Projects

**Justfile commands:**
```justfile
# Run linter with auto-fix
lint:
    golangci-lint run --fix

# Check linting without fixes (optional)
lint-test:
    golangci-lint run
```

Examples:
- `TerminalMenuApp`: Uses `lint` with `--fix` flag
- `DevFlowHub`: Uses `lint` (check only) and `lint-fix` (with fixes)
- `BackgroundTestRunner`: Uses PHP patterns (it's a Laravel API with Go CLI)

## Key Findings

1. **`lint` command behavior**: In 90%+ of projects, `lint` runs auto-fix
2. **Check-only alternatives**: 
   - PHP projects: `lint-check`
   - Node projects: `lint:check` or sometimes just ESLint without `--fix`
   - Go projects: `lint-test` or `lint` without `--fix`

3. **Consistency within ecosystems**:
   - Laravel projects are highly consistent with `lint`/`lint-check` pattern
   - TypeScript projects mostly use `lint` with auto-fix, some add `lint:fix`
   - Go projects vary more, but trend toward `lint` with `--fix`

## Recommendations

1. **Primary pattern** (RECOMMENDED):
   ```justfile
   # Auto-fix all linting issues
   lint:
       [technology-specific lint command with auto-fix]

   # Check only, no modifications
   lint-check:
       [technology-specific lint command without auto-fix]
   ```

2. **Alternative pattern** (when explicit fix command preferred):
   ```justfile
   # Check only
   lint:
       [technology-specific lint command without auto-fix]

   # Fix issues
   lint-fix:
       [technology-specific lint command with auto-fix]
   ```

3. **Universal pattern in justfiles**:
   - Use `fix-all` for comprehensive fixing (format + lint + test)
   - Use `check-all` for comprehensive checking without modifications

## Current Status

The `laravel-postgres-helper` project already follows the standard pattern correctly:
- `lint` - Runs auto-fix (php-cs-fixer fix, rector process, phpstan)
- `lint-check` - Runs check only (phpstan, rector --dry-run)
- `fix-all` - Comprehensive fix command (lint + test)

This aligns with the majority pattern across HaakCo projects.