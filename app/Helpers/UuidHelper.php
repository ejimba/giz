<?php

namespace App\Helpers;

use Ramsey\Uuid\Uuid;

class UuidHelper
{
    /**
     * Format a string to UUID format if it is a valid UUID.
     *
     * @param string $string
     * @return string|null
     */
    public static function formatUuid($string)
    {
        if (strlen($string) === 32 && ctype_xdigit($string)) {
            return preg_replace('/^(.{8})(.{4})(.{4})(.{4})(.{12})$/', '$1-$2-$3-$4-$5', $string);
        }

        // Alternatively, check if it's already a valid UUID.
        if (Uuid::isValid($string)) {
            return $string;
        }

        // If not a valid UUID, return null or handle as needed.
        return null;
    }
}
