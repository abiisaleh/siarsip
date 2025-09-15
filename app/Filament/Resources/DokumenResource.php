<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DokumenResource\Pages;
use App\Filament\Resources\DokumenResource\RelationManagers;
use App\Models\Dokumen;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DokumenResource extends Resource
{
    protected static ?string $model = Dokumen::class;

    protected static ?string $pluralLabel = 'Dokumen';

    protected static ?string $navigationIcon = 'heroicon-s-folder-open';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\Group::make([
                    Forms\Components\TextInput::make('file_name'),
                    Forms\Components\TextInput::make('nama_arsip'),
                    Forms\Components\Textarea::make('desc'),
                ])->visibleOn('edit'),
                Forms\Components\FileUpload::make('file_path')
                    ->required()
                    ->label('File')
                    ->hiddenOn('edit')
                    ->previewable(false)
                    ->storeFileNamesIn('file_name')
                    ->directory(fn() => now()->year . '/TW' . now()->quarter)
                    ->multiple(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-c-document-text')
                    ->iconColor(Color::Red),
                Tables\Columns\TextColumn::make('size'),
                Tables\Columns\ImageColumn::make('user.avatar')
                    ->tooltip(fn($record) => $record->user->name)
                    ->circular(),
                Tables\Columns\TextColumn::make('created_at')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('Periode')
                    ->form([
                        Forms\Components\Select::make('tahun')
                            ->native(false)
                            ->searchable()
                            ->options(function () {
                                $startYear = 2024;
                                $endYear = now()->year;
                                for ($i = $startYear; $i <= $endYear; $i++)
                                    $options[$i] = $i;

                                return $options;
                            }),
                        Forms\Components\Select::make('triwulan')
                            ->native(false)
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
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('download')
                        ->hiddenLabel()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(fn(Dokumen $record) => Storage::disk('public')->download($record->file_path)),
                    Tables\Actions\EditAction::make()
                        ->color('warning')
                        ->modalWidth('md'),
                    Tables\Actions\DeleteAction::make()
                        ->after(function (Dokumen $record) {
                            if (Storage::disk('public')->exists($record->file_path)) {
                                $file = Str::replace('wm_', '', $record->file_path);
                                Storage::disk('public')->delete($file);
                                Storage::disk('public')->delete($record->file_path);
                            }
                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (Collection $record) {
                            foreach ($record as $dokumen) {
                                if (Storage::disk('public')->exists($dokumen->file_path)) {
                                    $file = Str::replace('wm_', '', $dokumen->file_path);
                                    Storage::disk('public')->delete($file);
                                    Storage::disk('public')->delete($dokumen->file_path);
                                }
                            }
                        }),
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
                ]),
            ])
            ->recordUrl(fn(Dokumen $record) => Storage::disk('public')->url($record->file_path), true)
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDokumens::route('/'),
        ];
    }
}
