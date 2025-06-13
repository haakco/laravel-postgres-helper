<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Commands;

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PostgresHelperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postgres:helper
                            {action : The action to perform (health|validate|fix|standards|event-triggers|generate-migration)}
                            {--tables=* : Specific tables to process}
                            {--dry-run : Show what would be done without making changes}
                            {--enable : Enable feature (for event-triggers)}
                            {--disable : Disable feature (for event-triggers)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'PostgreSQL helper commands for database management';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $tables = $this->option('tables');
        $tables = \is_array($tables) && [] !== $tables ? $tables : null;
        $dryRun = (bool) $this->option('dry-run');

        if (!\is_string($action)) {
            $this->error('Invalid action provided');

            return Command::FAILURE;
        }

        return match ($action) {
            'health' => $this->runHealthCheck(),
            'validate' => $this->validateStructure($tables),
            'fix' => $this->fixIssues($tables),
            'standards' => $this->applyStandards($tables, $dryRun),
            'event-triggers' => $this->manageEventTriggers(),
            'generate-migration' => $this->generateMigration(),
            default => $this->invalidAction($action),
        };
    }

    /**
     * Run database health check.
     */
    protected function runHealthCheck(): int
    {
        $this->info('Running database health check...');

        $health = PgHelperLibrary::runHealthCheck();

        // Display overall score with color
        $score = $health['overall_score'];
        $scoreColor = match (true) {
            $score >= 80 => 'green',
            $score >= 60 => 'yellow',
            default => 'red',
        };

        $this->line("Overall Health Score: <fg={$scoreColor};options=bold>{$score}%</>");
        $this->newLine();

        // Display individual checks
        $this->info('Health Check Results:');
        foreach ($health['checks'] as $checkName => $check) {
            $statusIcon = match ($check['status']) {
                'healthy' => '✅',
                'warning' => '⚠️ ',
                'critical' => '❌',
                default => '❓',
            };

            $this->line("  {$statusIcon} {$checkName}: {$check['message']}");

            // @phpstan-ignore-next-line
            if (\array_key_exists('details', $check) && $this->output->isVerbose()) {
                /** @var array<string, mixed> $details */
                $details = $check['details'];
                foreach ($details as $key => $value) {
                    if (\is_array($value)) {
                        $this->line("     - {$key}: " . implode(', ', \array_slice($value, 0, 3)));
                        if (\count($value) > 3) {
                            $this->line('       ... and ' . (\count($value) - 3) . ' more');
                        }
                    } else {
                        $this->line("     - {$key}: {$value}");
                    }
                }
            }
        }

        // Display recommendations
        if (!empty($health['recommendations'])) {
            $this->newLine();
            $this->warn('Recommendations:');
            foreach ($health['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Validate database structure.
     */
    protected function validateStructure(?array $tables): int
    {
        $this->info('Validating database structure...');

        $result = PgHelperLibrary::validateStructure($tables);

        $this->line("Tables checked: {$result['tables_checked']}");

        if ($result['valid']) {
            $this->info('✅ All tables pass validation!');
        } else {
            $this->error('❌ Validation errors found!');

            foreach ($result['errors'] as $table => $errors) {
                $this->error("  {$table}:");
                foreach ($errors as $error) {
                    $this->line("    - {$error}");
                }
            }
        }

        if (!empty($result['warnings'])) {
            $this->newLine();
            $this->warn('⚠️  Warnings:');
            foreach ($result['warnings'] as $table => $warnings) {
                $this->warn("  {$table}:");
                foreach ($warnings as $warning) {
                    $this->line("    - {$warning}");
                }
            }
        }

        return $result['valid'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Fix database issues.
     */
    protected function fixIssues(?array $tables): int
    {
        $this->info('Fixing database issues...');

        if ($tables) {
            $sequenceResult = PgHelperLibrary::fixSequences($tables);
            $triggerResult = PgHelperLibrary::fixTriggers($tables);
        } else {
            $this->info('Running fixAll() on entire database...');
            PgHelperLibrary::fixAll();

            return Command::SUCCESS;
        }

        // Display results
        if (!empty($sequenceResult['sequences_fixed'])) {
            $this->info('Sequences fixed: ' . implode(', ', $sequenceResult['sequences_fixed']));
        }

        if (!empty($triggerResult['triggers_created'])) {
            $this->info('Triggers created: ' . implode(', ', $triggerResult['triggers_created']));
        }

        $this->info('✅ Database issues fixed!');

        return Command::SUCCESS;
    }

    /**
     * Apply PostgreSQL standards.
     */
    protected function applyStandards(?array $tables, bool $dryRun): int
    {
        $this->info($dryRun ? 'Checking what standards would be applied...' : 'Applying PostgreSQL standards...');

        $result = PgHelperLibrary::applyBestPractices($tables, $dryRun);

        $this->line("Tables processed: {$result['tables_processed']}");

        if (!empty($result['sequences_fixed'])) {
            $this->info('Sequences ' . ($dryRun ? 'to fix' : 'fixed') . ':');
            foreach ($result['sequences_fixed'] as $sequence) {
                $this->line("  - {$sequence}");
            }
        }

        if (!empty($result['triggers_created'])) {
            $this->info('Triggers ' . ($dryRun ? 'to create' : 'created') . ':');
            foreach ($result['triggers_created'] as $trigger) {
                $this->line("  - {$trigger}");
            }
        }

        if ($dryRun) {
            $this->warn('This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info('✅ PostgreSQL standards applied!');
        }

        return Command::SUCCESS;
    }

    /**
     * Manage event triggers.
     */
    protected function manageEventTriggers(): int
    {
        if ($this->option('enable') && $this->option('disable')) {
            $this->error('Cannot use both --enable and --disable');

            return Command::FAILURE;
        }

        if (!$this->option('enable') && !$this->option('disable')) {
            // Show current status
            $enabled = PgHelperLibrary::areEventTriggersEnabled();
            $this->info('Event triggers are currently: ' . ($enabled ? 'ENABLED ✅' : 'DISABLED ❌'));

            if (!$enabled) {
                $this->line('Use --enable to automatically apply standards to new tables.');
            }

            return Command::SUCCESS;
        }

        $enable = (bool) $this->option('enable');

        if ($enable && !$this->confirm('This will automatically apply standards to all new tables. Continue?')) {
            return Command::FAILURE;
        }

        try {
            $result = PgHelperLibrary::enableEventTriggers($enable);
            $this->info($result['message']);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to configure event triggers: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Generate migration for applying standards.
     */
    protected function generateMigration(): int
    {
        $this->info('Generating migration to apply PostgreSQL standards...');

        $migration = PgHelperLibrary::generateStandardsMigration();

        // Write the migration file
        $fullPath = base_path($migration['path']);
        $directory = \dirname($fullPath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($fullPath, $migration['content']);

        $this->info("Migration created: {$migration['path']}");
        $this->line('Run `php artisan migrate` to apply standards to existing tables.');

        return Command::SUCCESS;
    }

    /**
     * Handle invalid action.
     */
    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Available actions:');
        $this->line('  health             - Run database health check');
        $this->line('  validate           - Validate database structure');
        $this->line('  fix                - Fix database issues (sequences, triggers)');
        $this->line('  standards          - Apply PostgreSQL best practices');
        $this->line('  event-triggers     - Manage automatic standards application');
        $this->line('  generate-migration - Generate migration for existing tables');

        return Command::FAILURE;
    }
}
