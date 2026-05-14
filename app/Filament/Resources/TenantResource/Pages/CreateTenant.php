<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Branch;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        // Auto-create a default branch for the new tenant
        Branch::create([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $this->record->id,
            'name'       => 'Main Branch',
            'is_default' => true,
            'is_active'  => true,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
