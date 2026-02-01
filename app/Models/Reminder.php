<?php

namespace App\Models;

use App\Enums\ReminderPriority;
use App\Enums\ReminderRecurrence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'remindable_type',
        'remindable_id',
        'title',
        'description',
        'due_at',
        'snoozed_until',
        'completed_at',
        'recurrence',
        'priority',
        'is_system',
        'system_type',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'completed_at' => 'datetime',
            'recurrence' => ReminderRecurrence::class,
            'priority' => ReminderPriority::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * Global scope to ensure users only see their own reminders.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->pending()
            ->where(function ($q) {
                $q->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            })
            ->where('due_at', '<=', now());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query
            ->pending()
            ->where('due_at', '<=', now()->addDays($days))
            ->orderBy('due_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->pending()
            ->where('due_at', '<', now()->startOfDay());
    }

    // Attributes

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at < now()->startOfDay() && ! $this->completed_at;
    }

    public function getIsDueTodayAttribute(): bool
    {
        return $this->due_at->isToday() && ! $this->completed_at;
    }

    public function getEffectiveDueAtAttribute(): Carbon
    {
        return $this->snoozed_until ?? $this->due_at;
    }

    // Actions

    public function complete(): void
    {
        if ($this->recurrence) {
            // Create next occurrence
            self::create([
                'user_id' => $this->user_id,
                'remindable_type' => $this->remindable_type,
                'remindable_id' => $this->remindable_id,
                'title' => $this->title,
                'description' => $this->description,
                'due_at' => $this->recurrence->nextDueDate($this->due_at),
                'recurrence' => $this->recurrence,
                'priority' => $this->priority,
            ]);
        }

        $this->update(['completed_at' => now()]);
    }

    public function snooze(int $hours = 24): void
    {
        $this->update(['snoozed_until' => now()->addHours($hours)]);
    }

    public function reopen(): void
    {
        $this->update([
            'completed_at' => null,
            'snoozed_until' => null,
        ]);
    }
}
