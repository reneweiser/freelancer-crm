<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'project_id',
        'number',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
        'payment_method',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total',
        'service_period_start',
        'service_period_end',
        'notes',
        'footer_text',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'date',
            'service_period_start' => 'date',
            'service_period_end' => 'date',
            'subtotal' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * Generate next invoice number for the current year.
     * Format: YYYY-NNN (e.g., 2026-001)
     */
    public static function generateNextNumber(): string
    {
        $year = now()->year;
        $prefix = $year.'-';

        $lastNumber = DB::table('invoices')
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max('number');

        if ($lastNumber) {
            $sequence = (int) substr($lastNumber, -3) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $vatAmount = $subtotal * ($this->vat_rate / 100);

        $this->subtotal = $subtotal;
        $this->vat_amount = $vatAmount;
        $this->total = $subtotal + $vatAmount;
    }

    /**
     * Get formatted total.
     */
    public function getFormattedTotalAttribute(): string
    {
        return Number::currency($this->total, 'EUR', 'de_DE');
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [
            InvoiceStatus::Sent,
            InvoiceStatus::Overdue,
        ]);
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', InvoiceStatus::Paid);
    }

    /**
     * Scope for overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', InvoiceStatus::Sent)
            ->where('due_at', '<', now());
    }

    /**
     * Scope by year.
     */
    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('issued_at', $year);
    }

    /**
     * Scope by date range.
     */
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('issued_at', [$start, $end]);
    }

    /**
     * Mark invoice as paid.
     *
     * @param  array{paid_at?: string, payment_method?: string}  $data
     */
    public function markAsPaid(array $data = []): void
    {
        $this->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => $data['paid_at'] ?? now(),
            'payment_method' => $data['payment_method'] ?? null,
        ]);
    }
}
