<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'description',
        'reference',
        'type',
        'hourly_rate',
        'fixed_price',
        'status',
        'offer_date',
        'offer_valid_until',
        'offer_sent_at',
        'offer_accepted_at',
        'start_date',
        'end_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'hourly_rate' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'offer_date' => 'date',
            'offer_valid_until' => 'date',
            'offer_sent_at' => 'datetime',
            'offer_accepted_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class)->orderBy('position');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderByDesc('started_at');
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'emailable')->orderByDesc('created_at');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Get total value from items or fixed price.
     */
    public function getTotalValueAttribute(): float
    {
        if ($this->type === ProjectType::Fixed && $this->fixed_price) {
            return (float) $this->fixed_price;
        }

        return $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
    }

    /**
     * Scope for active projects (in progress).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            ProjectStatus::Accepted,
            ProjectStatus::InProgress,
        ]);
    }

    /**
     * Scope for offer stage projects.
     */
    public function scopeOffers($query)
    {
        return $query->whereIn('status', [
            ProjectStatus::Draft,
            ProjectStatus::Sent,
        ]);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, ProjectStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Transition to a new status with validation.
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(ProjectStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Ungültiger Statusübergang von '{$this->status->getLabel()}' zu '{$newStatus->getLabel()}'."
            );
        }

        $this->update(['status' => $newStatus]);
    }

    /**
     * Send the offer to the client.
     */
    public function sendOffer(): void
    {
        $this->transitionTo(ProjectStatus::Sent);
        $this->update(['offer_sent_at' => now()]);
    }

    /**
     * Mark offer as accepted by client.
     */
    public function acceptOffer(): void
    {
        $this->transitionTo(ProjectStatus::Accepted);
        $this->update(['offer_accepted_at' => now()]);
    }

    /**
     * Mark offer as declined by client.
     */
    public function declineOffer(): void
    {
        $this->transitionTo(ProjectStatus::Declined);
    }

    /**
     * Start working on the project.
     */
    public function startProject(?string $startDate = null): void
    {
        $this->transitionTo(ProjectStatus::InProgress);
        $this->update(['start_date' => $startDate ?? now()]);
    }

    /**
     * Mark project as completed.
     */
    public function completeProject(?string $endDate = null): void
    {
        $this->transitionTo(ProjectStatus::Completed);
        $this->update(['end_date' => $endDate ?? now()]);
    }

    /**
     * Reopen a completed project.
     */
    public function reopenProject(): void
    {
        $this->transitionTo(ProjectStatus::InProgress);
        $this->update(['end_date' => null]);
    }

    /**
     * Cancel the project.
     */
    public function cancel(): void
    {
        $this->transitionTo(ProjectStatus::Cancelled);
    }

    /**
     * Check if project can be invoiced.
     */
    public function canBeInvoiced(): bool
    {
        return $this->status->isActive() || $this->status === ProjectStatus::Completed;
    }

    /**
     * Check if this is an hourly project.
     */
    public function isHourly(): bool
    {
        return $this->type === ProjectType::Hourly;
    }

    /**
     * Get total tracked hours (all entries).
     */
    public function getTotalHoursAttribute(): float
    {
        return round($this->timeEntries()->sum('duration_minutes') / 60, 2);
    }

    /**
     * Get total billable hours.
     */
    public function getBillableHoursAttribute(): float
    {
        return round($this->timeEntries()->billable()->sum('duration_minutes') / 60, 2);
    }

    /**
     * Get unbilled hours (billable but not yet invoiced).
     */
    public function getUnbilledHoursAttribute(): float
    {
        return round($this->timeEntries()->billable()->unbilled()->sum('duration_minutes') / 60, 2);
    }

    /**
     * Get unbilled amount based on hourly rate.
     */
    public function getUnbilledAmountAttribute(): float
    {
        if (! $this->isHourly() || $this->hourly_rate === null) {
            return 0;
        }

        return round($this->unbilled_hours * (float) $this->hourly_rate, 2);
    }
}
