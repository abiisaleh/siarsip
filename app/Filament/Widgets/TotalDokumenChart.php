<?php

namespace App\Filament\Widgets;

use App\Models\Dokumen;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class TotalDokumenChart extends ChartWidget
{
    protected static ?string $heading = 'Total Dokumen Chart';

    protected function getData(): array
    {
        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $startDate = Carbon::create(now()->year, ($quarter - 1) * 3 + 1, 1)->startOfDay();
            $endDate = Carbon::create(now()->year, $quarter * 3, 1)->endOfMonth()->endOfDay();

            $dokumenCount[] = Dokumen::whereBetween('created_at', [$startDate, $endDate])->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total upload dokumen',
                    'data' => $dokumenCount
                ]
            ],
            'labels' => ['TW 1', 'TW 2', 'TW 3', 'TW 4']
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
