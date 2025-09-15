<?php

namespace App\Filament\Widgets;

use App\Models\Dokumen;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class RecentDokumen extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Dokumen::orderBy('created_at', 'desc')->take(10)
            )
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->limit('25')
                    ->grow(false)
                    ->icon('heroicon-c-document-text')
                    ->iconColor(Color::Red),
                Tables\Columns\TextColumn::make('size'),
                Tables\Columns\ImageColumn::make('user.avatar')
                    ->tooltip(fn($record) => $record->user->name)
                    ->circular(),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
            ])
            ->recordUrl(fn(Dokumen $record) => Storage::disk('public')->url($record->file_path), true);
    }
}
