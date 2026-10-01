<?php

namespace Arzcode\Sisifo\Traits;

trait HasEnumFunctions
{
    /**
     * @return list<int|string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): ?string
    {
        return __($this->name);
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn($item) => [$item->value => __($item->name)])
            ->all();
    }
}
