<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateApplicationLog extends Model
{
    protected $fillable = [
        'candidate_application_id',
        'from_stage',
        'to_stage',
        'changed_by',
        'changed_at',
        'note',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    // Relationships
    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the from stage (company stage)
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(CompanyStage::class, 'from_stage');
    }

    /**
     * Get the to stage (company stage)
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(CompanyStage::class, 'to_stage');
    }

    /**
     * Get from stage label
     */
    public function getFromStageLabelAttribute(): string
    {
        if (!$this->from_stage) {
            return 'Initial Stage';
        }
        
        return $this->fromStage ? $this->fromStage->name : 'Unknown Stage';
    }

    /**
     * Get to stage label
     */
    public function getToStageLabelAttribute(): string
    {
        return $this->toStage ? $this->toStage->name : 'Unknown Stage';
    }

    /**
     * Check if this log represents a hire action
     */
    public function isHireAction(): bool
    {
        // Check if moving to a stage that indicates hiring
        if ($this->toStage) {
            return strtolower($this->toStage->name) === 'hired' || 
                   strtolower($this->toStage->name) === 'onboarding' ||
                   $this->toStage->type === 'onboarding';
        }
        return false;
    }
}