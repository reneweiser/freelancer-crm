<?php

namespace App\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskFrequency: string implements HasColor, HasLabel
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weekly => 'Wöchentlich',
            self::Monthly => 'Monatlich',
            self::Quarterly => 'Vierteljährlich',
            self::Yearly => 'Jährlich',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Weekly => 'info',
            self::Monthly => 'primary',
            self::Quarterly => 'warning',
            self::Yearly => 'success',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Weekly => 'Woche',
            self::Monthly => 'Monat',
            self::Quarterly => 'Quartal',
            self::Yearly => 'Jahr',
        };
    }

    public function nextDueDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonth(),
            self::Quarterly => $from->copy()->addQuarter(),
            self::Yearly => $from->copy()->addYear(),
        };
    }

    public function daysBefore(): int
    {
        return match ($this) {
            self::Weekly => 2,
            self::Monthly => 7,
            self::Quarterly => 14,
            self::Yearly => 30,
        };
    }
}
