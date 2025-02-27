<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use function is_bool;
use function is_string;

final class InputHelper
{
    /**
     * @param mixed $value
     */
    public static function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentGiven($value, 'string|null');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function string($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentGiven($value, 'string');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public static function bool($value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentGiven($value, 'boolean');
        }

        return $value;
    }
}
