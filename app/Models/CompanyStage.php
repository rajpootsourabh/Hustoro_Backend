<?php
// app/Models/CompanyStage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'stage_order',
        'is_active'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function stageDocuments()
    {
        return $this->hasMany(StageDocument::class)->orderBy('document_order');
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'stage_documents')
            ->withPivot('is_required', 'document_order')
            ->orderBy('document_order');
    }

    public function candidateApplications()
    {
        return $this->hasMany(CandidateApplication::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Safety check before deletion
     */
    public function getDeletionSafetyInfo(): array
    {
        return [
            'has_candidate_applications' => $this->candidateApplications()->exists(),
            'candidate_applications_count' => $this->candidateApplications()->count(),
            'active_candidates_count' => $this->candidateApplications()->where('status', 'Active')->count(),
            'can_safely_delete' => !$this->candidateApplications()->exists(),
            'warning' => $this->candidateApplications()->exists()
                ? 'Deleting this stage will affect ' . $this->candidateApplications()->count() . ' candidate application(s)'
                : null
        ];
    }

    /**
     * Safe deletion with validation
     */
    public function safeDelete(): bool
    {
        if ($this->candidateApplications()->exists()) {
            return false;
        }

        return $this->delete();
    }

    /**
     * Check if stage can be modified safely
     */
    public function canBeModifiedSafely(): bool
    {
        return !$this->candidateApplications()->where('status', 'Active')->exists();
    }
}
