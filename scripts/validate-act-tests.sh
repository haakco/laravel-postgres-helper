#!/usr/bin/env bash

# Laravel PostgreSQL Helper - ACT Local Testing Validation Script
# This script validates that GitHub Actions workflows work correctly with act

set -e

echo "🧪 Laravel PostgreSQL Helper - ACT Validation"
echo "============================================"

# Check if act is installed
if ! command -v act &> /dev/null; then
    echo "❌ Error: 'act' is not installed."
    echo "Please install act: https://github.com/nektos/act"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Error: Docker is not running."
    echo "Please start Docker and try again."
    exit 1
fi

echo "✅ Prerequisites checked"
echo ""

# Define test matrices
PHP_VERSIONS=("8.3" "8.4")
LARAVEL_VERSIONS=("10.*" "11.*" "12.*")
POSTGRES_VERSIONS=("13" "14" "15" "16")

# Test a single combination for quick validation
echo "🔄 Running quick validation test..."
echo "   PHP: 8.3.36, Laravel: 11.*, PostgreSQL: 15"
echo ""

# Create a temporary event file for act
cat > /tmp/act-event.json <<EOF
{
  "push": {
    "ref": "refs/heads/main"
  }
}
EOF

# Run act with a single matrix combination
act push \
    --rm \
    --matrix php:8.3.36 \
    --matrix laravel:11.* \
    --matrix postgres:15 \
    --eventpath /tmp/act-event.json \
    --job test

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ ACT validation successful!"
    echo ""
    echo "To run the full test matrix locally, use:"
    echo "  act push --rm"
    echo ""
    echo "To run specific combinations, use:"
    echo "  act push --rm --matrix php:8.3.36 --matrix laravel:11.* --matrix postgres:15"
else
    echo ""
    echo "❌ ACT validation failed!"
    echo "Please check the error messages above."
    exit 1
fi

# Clean up
rm -f /tmp/act-event.json
