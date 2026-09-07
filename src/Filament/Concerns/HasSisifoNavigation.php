<?php

namespace Arzcode\Sisifo\Filament\Concerns;

use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Shared navigation behaviour for Sisifo's resources: the host app's configured
 * navigation group, and an active icon that mirrors the resource's own outlined
 * icon in its solid variant.
 */
trait HasSisifoNavigation
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = config('sisifo.navigation_group');

        if (is_string($group)) {
            return __($group);
        }

        return $group instanceof UnitEnum ? $group : null;
    }

    public static function getActiveNavigationIcon(): string|Heroicon|null
    {
        $icon = static::getNavigationIcon();

        if ($icon instanceof Heroicon) {
            $iconName = $icon->name;

            if (str_starts_with($iconName, 'Outlined')) {
                $solidName = substr($iconName, 8);

                $solidIcon = constant(Heroicon::class . '::' . $solidName);

                return $solidIcon instanceof Heroicon ? $solidIcon : $icon;
            }

            return $icon;
        }

        if (is_string($icon)) {
            return str($icon)->replace('heroicon-o-', 'heroicon-s-')->toString();
        }

        return null;
    }
}
