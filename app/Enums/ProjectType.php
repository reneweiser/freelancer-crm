<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectType: string implements HasLabel
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Festpreis',
            self::Hourly => 'Nach Aufwand',
        };
    }
}
