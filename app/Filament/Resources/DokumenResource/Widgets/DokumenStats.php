<?php

namespace App\Filament\Resources\DokumenResource\Widgets;

use App\Models\Dokumen;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DokumenStats extends BaseWidget
{
    protected function getStats(): array
    {
        $year = now()->year;
        $quarter = now()->quarter;

        return [
            Stat::make("Dokumen {$year}", number_format(Dokumen::query()->whereYear('created_at', $year)->count())),
            Stat::make("Dokumen TW{$quarter}", function () {
                $startDate = Carbon::create(now()->year, (now()->quarter - 1) * 3 + 1, 1)->startOfDay();
                $endDate = Carbon::create(now()->year, now()->quarter * 3, 1)->endOfMonth()->endOfDay();
                return Dokumen::whereBetween('created_at', [$startDate, $endDate])->count();
            }),
            Stat::make("Dokumen Hari ini", number_format(Dokumen::query()->whereDate('created_at', now()->today())->count())),
        ];
    }
}
