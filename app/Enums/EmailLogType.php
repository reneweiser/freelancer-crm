<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmailLogType: string implements HasLabel
{
    case Offer = 'offer';
    case Invoice = 'invoice';
    case PaymentReminder = 'payment_reminder';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Offer => 'Angebot',
            self::Invoice => 'Rechnung',
            self::PaymentReminder => 'Zahlungserinnerung',
            self::Custom => 'Benutzerdefiniert',
        };
    }
}
