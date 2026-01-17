<?php
// app/Models/CandidateStageDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CandidateStageDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_application_id',
        'document_id',
        'file_path',
        'is_completed',
        'completed_at',
        'uploaded_by'
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(CandidateApplication::class, 'candidate_application_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function markAsCompleted()
    {
        $this->update([
            'is_completed' => true,
            'completed_at' => now()
        ]);
    }

    /**
     * Safe deletion with file cleanup
     */
    public function safeDelete(): bool
    {
        // Delete the physical file first
        $this->deletePhysicalFile();

        // Then delete the database record
        return $this->delete();
    }

    /**
     * Delete the physical file from storage
     */
    public function deletePhysicalFile(): bool
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            return Storage::delete($this->file_path);
        }
        return false;
    }

    /**
     * Get file information safely
     */
    public function getFileInfo(): array
    {
        return [
            'file_path' => $this->file_path,
            'file_exists' => $this->file_path && Storage::exists($this->file_path),
            'file_size' => $this->file_path && Storage::exists($this->file_path)
                ? Storage::size($this->file_path)
                : null,
            'is_completed' => $this->is_completed,
            'uploaded_at' => $this->created_at,
        ];
    }
}
