<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Gesendet',
            self::Accepted => 'Angenommen',
            self::Declined => 'Abgelehnt',
            self::InProgress => 'In Bearbeitung',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Storniert',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Accepted, self::Declined, self::Cancelled],
            self::Accepted => [self::InProgress, self::Cancelled],
            self::Declined => [],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [self::InProgress],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Declined, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Accepted, self::InProgress], true);
    }
}
