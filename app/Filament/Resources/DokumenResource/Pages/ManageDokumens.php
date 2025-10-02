<?php

namespace App\Filament\Resources\DokumenResource\Pages;

use App\Filament\Resources\DokumenResource;
use App\Models\Dokumen;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Resource;
use FilippoToso\PdfWatermarker\Facades\ImageWatermarker;
use FilippoToso\PdfWatermarker\Facades\TextWatermarker;
use FilippoToso\PdfWatermarker\Support\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Str;

class ManageDokumens extends ManageRecords
{
    protected static string $resource = DokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->form([
                    Forms\Components\TextInput::make('file_name'),
                    Forms\Components\TagsInput::make('tags')
                        ->suggestions(fn() => Dokumen::all()->pluck('tags')->flatten()->unique())
                        ->required(),
                    Forms\Components\FileUpload::make('file_path')
                        ->hint('supported file type : *.pdf')
                        ->acceptedFileTypes(['application/pdf'])
                        ->required()
                        ->label('File')
                        ->hiddenOn('edit')
                        ->previewable(false)
                        ->multiple()
                        ->storeFiles(false),
                ])
                ->action(function (array $data) {
                    $user = auth()->id();
                    $dir = now()->year . '/' . now()->quarter;
                    $fileName = $data['file_name'];

                    foreach ($data['file_path'] as $file) {
                        $originalName = Str::replace(['/', '_'], '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                        $extension = $file->getClientOriginalExtension();
                        $uuid = uniqid();
                        $dateSuffix = now()->format('dmY');

                        if ($fileName == '')
                            $newFilename = "{$originalName}_{$uuid}_{$dateSuffix}.{$extension}";
                        else
                            $newFilename = "{$fileName}_{$uuid}_{$dateSuffix}.{$extension}";

                        $path = $file->storeAs($dir, $newFilename, 'public');

                        // add watermark (per-page orientation & size aware)
                        $inputPath = Storage::disk('public')->path($path);
                        $outRelative = $dir . '/wm_' . $newFilename;
                        $outPath = Storage::disk('public')->path($outRelative);

                        $pdf = new Fpdi();
                        $pageCount = $pdf->setSourceFile($inputPath);

                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $tplId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($tplId);

                            // orientation
                            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

                            // create page with exact size so watermark fits
                            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                            $pdf->useTemplate($tplId);

                            // detect paper (approx) by dimensions (mm). Fpdi default unit is mm.
                            $width_mm = $size['width'];
                            $height_mm = $size['height'];
                            $tolerance = 8; // mm tolerance for detection

                            if (abs($width_mm - 210) < $tolerance && abs($height_mm - 297) < $tolerance) {
                                $paper = 'a4';
                            } elseif (abs($width_mm - 330) < $tolerance && abs($height_mm - 210) < $tolerance) {
                                $paper = 'f4';
                            } else {
                                // fallback: if long side > 300mm guess F4 else A4
                                $longSide = max($width_mm, $height_mm);
                                $paper = ($longSide > 300) ? 'f4' : 'a4';
                            }

                            // choose watermark image based on paper and orientation
                            $orientName = ($orientation === 'P') ? 'potret' : 'landscape';
                            $wmImage = public_path("asset/wm-{$paper}-{$orientName}.png");

                            if (file_exists($wmImage)) {
                                // place watermark to cover full page
                                // Image(x, y, w, h) — use page width/height so it fills the page
                                $pdf->Image($wmImage, 0, 0, $size['width'], $size['height']);
                            }
                        }

                        // write watermarked PDF
                        $pdf->Output($outPath, 'F');

                        // store the watermarked file path instead of original
                        $files[] = [
                            'user_id' => $user,
                            'file_path' => $outRelative,
                            'file_name' => $data['file_name'] ?? $originalName,
                            'tags' => json_encode($data['tags']),
                            'page_count' => $pageCount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    Dokumen::insert($files);
                })
                ->successNotificationTitle('Files uploaded successfully!')
                ->label('Upload')
                ->icon('heroicon-m-arrow-up-tray')
                ->modalWidth('md')
                ->modalHeading('Upload file')
                ->modalSubmitActionLabel('Upload'),
            Actions\Action::make('print_report')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Select::make('tahun')
                        ->native(false)
                        ->searchable()
                        ->required()
                        ->options(function () {
                            $startYear = 2024;
                            $endYear = now()->year;
                            for ($i = $startYear; $i <= $endYear; $i++)
                                $options[$i] = $i;

                            return $options;
                        }),
                    Forms\Components\Select::make('triwulan')
                        ->native(false)
                        ->required()
                        ->options([
                            '1' => 'TW 1 (Januari, Februari, Maret)',
                            '2' => 'TW 2 (April, Mei, Juni)',
                            '3' => 'TW 3 (Juli, Agustus, September)',
                            '4' => 'TW 4 (Oktober, November, Desember)',
                        ]),
                ])
                ->action(function (array $data) {
                    // prepare data rows (ganti query sesuai kebutuhan)
                    $query = Dokumen::query()->whereDate('created_at', $data['tanggal']);

                    // save to db
                    $query->update(['nomor_ba' => $data['nomor']]);

                    // generate word document
                    $templatePath = 'asset/template-ba.docx';

                    // load template
                    $tp = new TemplateProcessor($templatePath);

                    $rows = $query->get()->toArray();

                    $count = count($rows);
                    if ($count > 0) {
                        $tp->cloneRow('no', $count);

                        foreach ($rows as $i => $row) {
                            $idx = $i + 1;
                            $tp->setValue("no#{$idx}", $idx);
                            $tp->setValue("file-name#{$idx}", $row['file_name']);
                            $tp->setValue("page#{$idx}", $row['page_count']);
                            $tp->setValue("created-at#{$idx}", date_create($row['created_at'])->format('d F Y'));
                        }
                    }

                    // tambahan field dari form
                    $today = now();
                    $today->setLocale('id');

                    $tp->setValue('nomor', $data['nomor']);
                    $tp->setValue('date', $today->format('d F Y'));

                    // simpan file hasil generate ke storage/public/generated/
                    $outDir = storage_path('app/public/generated');
                    if (!file_exists($outDir)) {
                        mkdir($outDir, 0755, true);
                    }
                    $outName = 'ba_' . now()->format('Ymd_His') . '.docx';
                    $outPath = $outDir . '/' . $outName;
                    $tp->saveAs($outPath);

                    // simpan path ke DB bila perlu, atau biarkan user download
                    return response()->download($outPath)->deleteFileAfterSend(true);
                })
                ->icon('heroicon-m-printer')
                ->color('info')
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make(),
            'belum diarsip' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('nomor_ba', null)),
            'sudah diarsip' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNot('nomor_ba', null)),
        ];
    }
}
