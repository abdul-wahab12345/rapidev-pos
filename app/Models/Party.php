<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Party extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'name', 'company', 'phone', 'email',
        'address', 'city', 'ntn', 'cnic',
        'is_customer', 'is_supplier', 'notes',
    ];

    protected $casts = [
        'is_customer' => 'boolean',
        'is_supplier' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Party $party) {
            if (empty($party->id)) {
                $party->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'party_id');
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class, 'party_id');
    }

    public function getReceivableBalanceAttribute(): float
    {
        return (float) ($this->customer?->current_balance ?? 0);
    }

    public function getPayableBalanceAttribute(): float
    {
        return (float) ($this->supplier?->current_balance ?? 0);
    }

    public function getNetBalanceAttribute(): float
    {
        return $this->receivable_balance - $this->payable_balance;
    }
}
