<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'vat_rate',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get line total (net).
     */
    public function getTotalAttribute(): float
    {
        return (float) ($this->quantity * $this->unit_price);
    }

    /**
     * Get line VAT amount.
     */
    public function getVatAmountAttribute(): float
    {
        return $this->total * ($this->vat_rate / 100);
    }

    /**
     * Get line gross total.
     */
    public function getGrossTotalAttribute(): float
    {
        return $this->total + $this->vat_amount;
    }
}
