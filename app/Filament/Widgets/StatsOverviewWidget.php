<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Businesses', Tenant::count())
                ->description('Registered tenants')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Active Businesses', Tenant::where('status', 'active')->count())
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Total Users', User::where('is_super_admin', false)->count())
                ->description('Across all businesses')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Trial Businesses', Tenant::where('plan', 'trial')->count())
                ->description('On trial plan')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
