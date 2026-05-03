<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'party_id', 'name', 'company', 'phone', 'email', 'address',
        'city_id', 'area_id',
        'city',
        'ntn', 'cnic', 'payment_terms', 'opening_balance', 'current_balance',
        'is_active', 'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Supplier $s) {
            if (empty($s->id)) {
                $s->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function districtCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
