<?php

namespace App\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasLabel;

enum ReminderRecurrence: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Täglich',
            self::Weekly => 'Wöchentlich',
            self::Monthly => 'Monatlich',
            self::Yearly => 'Jährlich',
        };
    }

    public function nextDueDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::Daily => $from->copy()->addDay(),
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonth(),
            self::Yearly => $from->copy()->addYear(),
        };
    }
}
