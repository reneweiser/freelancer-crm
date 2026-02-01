<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmailLogStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'Warteschlange',
            self::Sent => 'Gesendet',
            self::Failed => 'Fehlgeschlagen',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
