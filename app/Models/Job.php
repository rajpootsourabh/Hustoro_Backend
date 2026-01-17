<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_title',
        'job_startdate',
        'job_enddate',
        'job_description',
        'candidate_id',
        'company_id',
        'client_id',
        'status',
        'notes',
        'created_by',
        'assigned_by',
        'assigned_at',
        'confirmed_at'
    ];

    protected $casts = [
        'job_startdate' => 'date',
        'job_enddate' => 'date',
        'assigned_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

        /**
     * Scope a query to only include jobs for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Relationship with shifts (many-to-many)
    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'job_shifts')
            ->withTimestamps();
    }

    // Relationship with candidate
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationship with client
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Relationship with company
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Get active time log
    public function activeTimeLog()
    {
        return $this->hasOne(JobTimeLog::class)->where('status', 'in_progress');
    }

    // Helper method to get total worked time in seconds
    public function getTotalWorkedTimeAttribute()
    {
        return $this->timeLogs()->sum('total_seconds');
    }

    // Relationship with time logs
    public function timeLogs()
    {
        return $this->hasMany(JobTimeLog::class);
    }

    // Helper method to format total worked time
    public function getFormattedWorkedTimeAttribute()
    {
        $totalSeconds = $this->total_worked_time;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
