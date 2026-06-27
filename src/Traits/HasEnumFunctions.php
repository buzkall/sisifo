<?php

namespace Arzcode\Sisifo\Traits;

trait HasEnumFunctions
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): ?string
    {
        return __($this->name);
    }

    public static function options(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn($item) => [$item->value => __($item->name)])
            ->toArray();
    }
}
