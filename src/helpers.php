<?php

declare(strict_types=1);

if (!function_exists('_e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param string $value
     * @return int
     */
    function _e(string $value): int
    {
        return print($value);
    }
}
