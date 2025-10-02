<?php

namespace App\Filament\Widgets;

use App\Models\Dokumen;
use Carbon\Carbon;
use Filament\Support\Colors\Color;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AllDocuments extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function is_admin(): bool
    {
        return filament()->auth()->id() == 1;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Dokumen::orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->searchable()
                    ->icon('heroicon-c-document-text')
                    ->iconColor(Color::Red),
                Tables\Columns\TextColumn::make('tags')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('size'),
                Tables\Columns\ImageColumn::make('user.avatar')
                    ->tooltip(fn($record) => $record->user->name)
                    ->circular(),
                Tables\Columns\TextColumn::make('uploaded')
                    ->state(fn($record) => $record->created_at)
                    ->since(),
                Tables\Columns\TextColumn::make('created_at')
                    ->toggleable()
                    ->toggledHiddenByDefault()
            ])
            ->filters([
                Tables\Filters\Filter::make('Periode')
                    ->columns()
                    ->form([
                        Forms\Components\Select::make('tahun')
                            ->native(false)
                            ->searchable()
                            ->hiddenLabel()
                            ->placeholder('Tahun')
                            ->options(function () {
                                $startYear = 2024;
                                $endYear = now()->year;
                                for ($i = $startYear; $i <= $endYear; $i++)
                                    $options[$i] = $i;

                                return $options;
                            }),
                        Forms\Components\Select::make('triwulan')
                            ->native(false)
                            ->hiddenLabel()
                            ->placeholder('Triwulan')
                            ->options([
                                '1' => 'TW 1',
                                '2' => 'TW 2',
                                '3' => 'TW 3',
                                '4' => 'TW 4',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['tahun'])
                            $query->whereYear('created_at', $data['tahun']);
                        if ($data['triwulan']) {
                            $startDate = Carbon::create($data['tahun'], ($data['triwulan'] - 1) * 3 + 1, 1)->startOfDay();
                            $endDate = Carbon::create($data['tahun'], $data['triwulan'] * 3, 1)->endOfMonth()->endOfDay();
                            $query->whereBetween('created_at', [$startDate, $endDate]);
                        }
                    }),
                Tables\Filters\SelectFilter::make('tags')
                    ->native(false)
                    ->multiple()
                    ->searchable()
                    ->options(
                        function () {
                            $tags = Dokumen::all()->pluck('tags')->flatten()->unique();

                            foreach ($tags as $tag) {
                                $options[$tag] = $tag;
                            }

                            return $options;
                        }
                    )
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['values']))
                            foreach ($data['values'] as $tag) {
                                $query->whereJsonContains('tags', $tag);
                            }
                        return $query;
                    }),
                Tables\Filters\TrashedFilter::make()
                    ->visible($this->is_admin()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('download')
                        ->hiddenLabel()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn(Dokumen $record) => Storage::disk('public')->download($record->file_path)),
                    Tables\Actions\EditAction::make()
                        ->form([
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('file_name')
                                    ->required(),
                                Forms\Components\TagsInput::make('tags')
                                    ->suggestions(fn() => Dokumen::all()->pluck('tags')->flatten()->unique())
                                    ->required(),
                                Forms\Components\TextInput::make('nama_arsip'),
                                Forms\Components\Textarea::make('desc'),
                            ])
                        ])
                        ->color('warning')
                        ->modalWidth('md'),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ForceDeleteAction::make()
                        ->after(function (Dokumen $record) {
                            if (Storage::disk('public')->exists($record->file_path)) {
                                $file = Str::replace('wm_', '', $record->file_path);
                                Storage::disk('public')->delete($file);
                                Storage::disk('public')->delete($record->file_path);
                            }
                        }),
                    Tables\Actions\RestoreAction::make()
                ])->visible($this->is_admin())
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('download_selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Collection $records) {
                        $zipName = 'siarsip_dokumen_' . now()->format('siH_dmY') . '.zip';
                        $zipPath = storage_path('app/public/generated/' . $zipName);

                        if (!file_exists(dirname($zipPath))) {
                            mkdir(dirname($zipPath), 0755, true);
                        }

                        $zip = new \ZipArchive();
                        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
                            foreach ($records as $dokumen) {
                                $filePath = Storage::disk('public')->path($dokumen->file_path);
                                if (file_exists($filePath)) {
                                    $zip->addFile($filePath, basename($dokumen->file_path));
                                }
                            }
                            $zip->close();
                        }

                        return response()->download($zipPath)->deleteFileAfterSend(true);
                    }),
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->after(function (Collection $record) {
                            foreach ($record as $dokumen) {
                                if (Storage::disk('public')->exists($dokumen->file_path)) {
                                    $file = Str::replace('wm_', '', $dokumen->file_path);
                                    Storage::disk('public')->delete($file);
                                    Storage::disk('public')->delete($dokumen->file_path);
                                }
                            }
                        }),
                    Tables\Actions\RestoreBulkAction::make()

                ])->visible($this->is_admin()),
            ])
            ->recordUrl(fn(Dokumen $record) => Storage::disk('public')->url($record->file_path), true);
    }
}
