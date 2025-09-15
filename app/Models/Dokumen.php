<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class Dokumen extends Model
{
    public function getIconAttribute()
    {
        $extension = pathinfo($this->file_name, PATHINFO_EXTENSION);
        return match ($extension) {
            'png', 'jpg', 'jpeg', 'svg' => 'heroicon-c-photo',
            'mp3', 'wav', 'flac', 'acc', 'ogg' => 'heroicon-c-musical-note',
            '3gp', 'mp4', 'mkv' => 'heroicon-c-film',
            'pdf', 'doc', 'docx' => 'heroicon-c-document-text',
            'xls', 'xlsx' => 'heroicon-c-table-cells',
            'ppt', 'pptx' => 'heroicon-c-presentation-chart-bar',
            default => 'heroicon-c-document',
        };
    }

    public function getSizeAttribute()
    {
        $sizeinbytes = Storage::disk('public')->size($this->file_path);
        return Number::fileSize($sizeinbytes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
