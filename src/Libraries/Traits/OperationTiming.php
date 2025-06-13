<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

trait OperationTiming
{
    protected static ?float $lastOperationTime = null;

    /**
     * @var array<string, array{count: int, total_time: float, average_time: float, last_time: float}>
     */
    protected static array $operationStats = [];

    /**
     * Get the execution time of the last operation.
     *
     * @return float|null Time in seconds, or null if no operation has been performed
     */
    public static function getLastOperationTime(): ?float
    {
        return self::$lastOperationTime;
    }

    /**
     * Get detailed statistics about operations.
     *
     * @return array{total_operations: int, operations: array<string, array{count: int, total_time: float, average_time: float, last_time: float}>}
     */
    public static function getOperationStats(): array
    {
        return [
            'total_operations' => array_sum(array_column(self::$operationStats, 'count')),
            'operations' => self::$operationStats,
        ];
    }

    /**
     * Execute an operation with timing.
     *
     * @param array<string, mixed> $context
     */
    protected static function withTiming(callable $operation, string $operationName, array $context = []): mixed
    {
        $startTime = microtime(true);

        try {
            $result = $operation();
            self::recordSuccessfulOperation($startTime, $operationName, $context);

            return $result;
        } catch (\Exception $e) {
            self::recordFailedOperation($startTime, $operationName, $context, $e);

            throw $e;
        }
    }

    /**
     * Record a successful operation.
     *
     * @param array<string, mixed> $context
     */
    private static function recordSuccessfulOperation(
        float $startTime,
        string $operation,
        array $context
    ): void {
        $duration = microtime(true) - $startTime;
        self::updateOperationStats($operation, $duration);
        self::logOperation($operation, $duration, $context, true);
    }

    /**
     * Record a failed operation.
     *
     * @param array<string, mixed> $context
     */
    private static function recordFailedOperation(
        float $startTime,
        string $operation,
        array $context,
        \Exception $exception
    ): void {
        $duration = microtime(true) - $startTime;
        $errorContext = array_merge($context, [
            'error' => $exception->getMessage(),
            'failed' => true,
        ]);

        self::updateOperationStats($operation, $duration);
        self::logOperation($operation, $duration, $errorContext, false);
    }

    /**
     * Update operation statistics.
     */
    private static function updateOperationStats(string $operation, float $duration): void
    {
        self::$lastOperationTime = $duration;
        self::initializeOperationStats($operation);
        self::incrementOperationStats($operation, $duration);
    }

    /**
     * Initialize stats for an operation if needed.
     */
    private static function initializeOperationStats(string $operation): void
    {
        if (!isset(self::$operationStats[$operation])) {
            self::$operationStats[$operation] = [
                'count' => 0,
                'total_time' => 0.0,
                'average_time' => 0.0,
                'last_time' => 0.0,
            ];
        }
    }

    /**
     * Increment operation statistics.
     */
    private static function incrementOperationStats(string $operation, float $duration): void
    {
        $stats = &self::$operationStats[$operation];
        $stats['count']++;
        $stats['total_time'] += $duration;
        $stats['average_time'] = $stats['total_time'] / $stats['count'];
        $stats['last_time'] = $duration;
    }

    /**
     * Log operation details.
     *
     * @param array<string, mixed> $context
     */
    private static function logOperation(
        string $operation,
        float $duration,
        array $context,
        bool $success
    ): void {
        self::logSlowOperation($operation, $duration, $context);

        if ($success) {
            self::logSuccessfulOperation($operation, $duration, $context);
        }
    }

    /**
     * Log slow operations if configured.
     *
     * @param array<string, mixed> $context
     */
    private static function logSlowOperation(string $operation, float $duration, array $context): void
    {
        if (!self::shouldLogSlowOperations()) {
            return;
        }

        $threshold = self::getSlowOperationThreshold();

        if ($duration > $threshold) {
            self::getLogger()->warning("Slow PostgreSQL helper operation: {$operation}", [
                'duration' => $duration,
                'context' => $context,
            ]);
        }
    }

    /**
     * Log successful operations if configured.
     *
     * @param array<string, mixed> $context
     */
    private static function logSuccessfulOperation(
        string $operation,
        float $duration,
        array $context
    ): void {
        if (!self::shouldLogSuccessfulOperations()) {
            return;
        }

        self::getLogger()->info("PostgreSQL helper operation completed: {$operation}", [
            'duration' => $duration,
            'context' => $context,
        ]);
    }

    /**
     * Check if slow operations should be logged.
     */
    private static function shouldLogSlowOperations(): bool
    {
        return Config::get('postgreshelper.performance.log_slow_operations', true);
    }

    /**
     * Check if successful operations should be logged.
     */
    private static function shouldLogSuccessfulOperations(): bool
    {
        return Config::get('postgreshelper.logging.log_success', false);
    }

    /**
     * Get slow operation threshold in seconds.
     */
    private static function getSlowOperationThreshold(): float
    {
        return Config::get('postgreshelper.performance.slow_operation_threshold', 1000) / 1000;
    }

    /**
     * Get the configured logger.
     */
    private static function getLogger(): LoggerInterface
    {
        return Log::channel(Config::get('postgreshelper.logging.channel', 'daily'));
    }
}
