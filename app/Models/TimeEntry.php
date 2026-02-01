<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'invoice_id',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'billable',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'billable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TimeEntry $entry) {
            $entry->calculateDuration();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Calculate duration from start/end times if not manually set.
     */
    public function calculateDuration(): void
    {
        if ($this->started_at && $this->ended_at && $this->ended_at->gt($this->started_at)) {
            $this->duration_minutes = (int) $this->started_at->diffInMinutes($this->ended_at);
        }
    }

    /**
     * Get formatted duration as hours and minutes.
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_minutes === null) {
            return '-';
        }

        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours} Std. {$minutes} Min.";
        }

        if ($hours > 0) {
            return "{$hours} Std.";
        }

        return "{$minutes} Min.";
    }

    /**
     * Get duration in hours (decimal).
     */
    public function getDurationHoursAttribute(): float
    {
        if ($this->duration_minutes === null) {
            return 0;
        }

        return round($this->duration_minutes / 60, 2);
    }

    /**
     * Check if this time entry has been invoiced.
     */
    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    /**
     * Scope for billable entries.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('billable', true);
    }

    /**
     * Scope for non-billable entries.
     */
    public function scopeNonBillable(Builder $query): Builder
    {
        return $query->where('billable', false);
    }

    /**
     * Scope for unbilled (not yet invoiced) entries.
     */
    public function scopeUnbilled(Builder $query): Builder
    {
        return $query->whereNull('invoice_id');
    }

    /**
     * Scope for invoiced entries.
     */
    public function scopeInvoiced(Builder $query): Builder
    {
        return $query->whereNotNull('invoice_id');
    }

    /**
     * Scope for entries within a date range.
     */
    public function scopeInDateRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('started_at', [$start, $end]);
    }

    /**
     * Scope for today's entries.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('started_at', today());
    }

    /**
     * Scope for this week's entries.
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('started_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope for this month's entries.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('started_at', now()->month)
            ->whereYear('started_at', now()->year);
    }
}
