<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

use Illuminate\Support\Facades\DB;

class SequenceValidator
{
    /**
     * Check if sequences need fixing.
     *
     * @param array<object{sequence_name: string}> $sequences
     *
     * @return array<string> Warning messages
     */
    public static function validateSequences(array $sequences): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (object $sequence): ?string => self::checkSequence($sequence),
                    $sequences
                )
            )
        );
    }

    /**
     * Check a single sequence.
     *
     * @param object{sequence_name: string} $sequence
     */
    private static function checkSequence(object $sequence): ?string
    {
        $lastValue = self::getSequenceLastValue($sequence->sequence_name);

        return $lastValue < 1
            ? "Sequence '{$sequence->sequence_name}' may need to be reset"
            : null;
    }

    /**
     * Get the last value of a sequence.
     */
    private static function getSequenceLastValue(string $sequenceName): int
    {
        $result = DB::selectOne("SELECT last_value FROM {$sequenceName}");

        return $result ? (int) $result->last_value : 0;
    }
}
