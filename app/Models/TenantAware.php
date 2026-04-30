<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class TenantAware extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Illuminate\Support\Str::uuid();
            }

            // Auto-set tenant_id from the authenticated user if not already set
            if (empty($model->tenant_id) && ($user = auth()->user()) && isset($user->tenant_id)) {
                $model->tenant_id = $user->tenant_id;
            }
        });

        // Global scope: all queries automatically filter by current tenant
        static::addGlobalScope('tenant', function ($query) {
            if (($user = auth()->user()) && isset($user->tenant_id) && $user->tenant_id) {
                $query->where($query->getModel()->getTable() . '.tenant_id', $user->tenant_id);
            }
        });
    }
}
