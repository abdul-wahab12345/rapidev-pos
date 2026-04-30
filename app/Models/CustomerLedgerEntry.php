<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedgerEntry extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'customer_id', 'sale_id', 'type',
        'amount', 'running_balance', 'description', 'payment_method',
    ];

    protected $casts = [
        'amount' => 'integer',
        'running_balance' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerLedgerEntry $entry) {
            if (empty($entry->id)) {
                $entry->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
