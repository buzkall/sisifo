<?php

namespace Arzcode\Sisifo\Support;

/**
 * @internal
 */
class ConfigValue
{
    /**
     * Read an integer setting. Env-backed values arrive as numeric strings, so
     * they are cast instead of rejected; anything non-numeric yields the default.
     */
    public static function int(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int)$value : $default;
    }
}
