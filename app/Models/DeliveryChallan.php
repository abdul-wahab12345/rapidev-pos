<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryChallan extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'sale_id', 'quotation_id', 'customer_id', 'user_id',
        'challan_number', 'status', 'delivery_date',
        'vehicle_number', 'driver_name', 'site_address', 'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class, 'challan_id');
    }

    public static function generateNumber(string $tenantId): string
    {
        $count = static::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->count() + 1;

        return 'DC-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }
}
