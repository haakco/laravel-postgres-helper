<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Traits;

use Illuminate\Support\Facades\DB;

trait SqlFileLoader
{
    /**
     * Load and execute a SQL file.
     *
     * @throws \RuntimeException
     */
    protected static function executeSqlFile(string $filename): void
    {
        $sql = self::loadSqlFile($filename);
        DB::unprepared($sql);
    }

    /**
     * Load SQL file contents.
     *
     * @throws \RuntimeException
     */
    protected static function loadSqlFile(string $filename): string
    {
        $path = self::getSqlFilePath($filename);
        self::validateFileExists($path, $filename);

        return self::readFileContents($path, $filename);
    }

    /**
     * Get the full path to a SQL file.
     */
    private static function getSqlFilePath(string $filename): string
    {
        return __DIR__ . '/../sql/' . $filename;
    }

    /**
     * Validate that a file exists.
     *
     * @throws \RuntimeException
     */
    private static function validateFileExists(string $path, string $filename): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("SQL file not found: {$filename}");
        }
    }

    /**
     * Read file contents safely.
     *
     * @throws \RuntimeException
     */
    private static function readFileContents(string $path, string $filename): string
    {
        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new \RuntimeException("Failed to read SQL file: {$filename}");
        }

        return $contents;
    }
}
