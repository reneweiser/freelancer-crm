<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $userId = auth()->id();

        return [
            $this->getOpenInvoicesStat($userId),
            $this->getMonthlyRevenueStat($userId),
            $this->getYearlyRevenueStat($userId),
            $this->getActiveProjectsStat($userId),
        ];
    }

    protected function getOpenInvoicesStat(int $userId): Stat
    {
        $openInvoices = Invoice::query()
            ->where('user_id', $userId)
            ->unpaid()
            ->get();

        $count = $openInvoices->count();
        $total = $openInvoices->sum('total');

        return Stat::make('Offene Rechnungen', $count)
            ->description(Number::currency($total, 'EUR', 'de_DE'))
            ->color('warning');
    }

    protected function getMonthlyRevenueStat(int $userId): Stat
    {
        $monthlyRevenue = Invoice::query()
            ->where('user_id', $userId)
            ->paid()
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total');

        return Stat::make('Umsatz diesen Monat', Number::currency($monthlyRevenue, 'EUR', 'de_DE'))
            ->color('success');
    }

    protected function getYearlyRevenueStat(int $userId): Stat
    {
        $yearlyRevenue = Invoice::query()
            ->where('user_id', $userId)
            ->paid()
            ->whereYear('paid_at', now()->year)
            ->sum('total');

        return Stat::make('Umsatz dieses Jahr', Number::currency($yearlyRevenue, 'EUR', 'de_DE'))
            ->color('success');
    }

    protected function getActiveProjectsStat(int $userId): Stat
    {
        $activeCount = Project::query()
            ->where('user_id', $userId)
            ->where('status', ProjectStatus::InProgress)
            ->count();

        return Stat::make('Aktive Projekte', $activeCount)
            ->color('info');
    }
}
