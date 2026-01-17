<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'job_post_id',
        'status',
        'applied_at',
        'company_stage_id'
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobPost()
    {
        return $this->belongsTo(JobPost::class, 'job_post_id');
    }

    public function logs()
    {
        return $this->hasMany(CandidateApplicationLog::class);
    }

    public function comments()
    {
        return $this->hasMany(CandidateApplicationComment::class);
    }

    public function communications()
    {
        return $this->hasMany(CandidateApplicationCommunication::class);
    }

    public function reviews()
    {
        return $this->hasMany(CandidateApplicationReview::class);
    }

    // Keep stage for backward compatibility
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    // New company stage relationship
    public function companyStage()
    {
        return $this->belongsTo(CompanyStage::class, 'company_stage_id');
    }

    public function stageDocuments()
    {
        return $this->hasMany(CandidateStageDocument::class);
    }

    /**
     * Check if application is at final stage
     */
    public function isAtFinalStage(): bool
    {
        if (!$this->companyStage) {
            return false;
        }

        return !CompanyStage::where('company_id', $this->jobPost->company_id)
            ->where('stage_order', '>', $this->companyStage->stage_order)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get next stage if available
     */
    public function getNextStageAttribute()
    {
        if (!$this->companyStage) {
            return null;
        }

        return CompanyStage::where('company_id', $this->jobPost->company_id)
            ->where('stage_order', '>', $this->companyStage->stage_order)
            ->where('is_active', true)
            ->orderBy('stage_order')
            ->first();
    }

    /**
     * Get current stage name with fallback
     */
    public function getCurrentStageNameAttribute()
    {
        return $this->companyStage ? $this->companyStage->name : ($this->stage ? $this->stage->name : 'Unknown');
    }

    public function candidateStageDocuments()
    {
        return $this->hasMany(CandidateStageDocument::class, 'candidate_application_id');
    }
}