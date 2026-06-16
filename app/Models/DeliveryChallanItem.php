<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeliveryChallanItem extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'challan_id', 'product_id', 'variant_id',
        'lot_number', 'product_name', 'product_unit',
        'quantity', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeliveryChallanItem $item) {
            if (empty($item->id)) {
                $item->id = (string) Str::uuid();
            }
        });
    }

    public function challan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'challan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
