<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Shift extends Model
{
    use HasFactory; // Removed SoftDeletes

    protected $fillable = [
        'title', 
        'slug', 
        'start_time_utc', 
        'end_time_utc', 
        'description', 
        'is_active',
        'company_id',
        'created_by'
    ];
    
    protected $casts = [
        'start_time_utc' => 'datetime:H:i:s',
        'end_time_utc' => 'datetime:H:i:s',
        'is_active' => 'boolean'
    ];

    // Automatically generate unique slug
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shift) {
            $shift->slug = self::generateUniqueSlug($shift->title);
        });

        static::updating(function ($shift) {
            if ($shift->isDirty('title')) {
                $shift->slug = self::generateUniqueSlug($shift->title, $shift->id);
            }
        });
    }

    private static function generateUniqueSlug($title, $excludeId = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        $query = Shift::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;

            $query = Shift::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    // Get formatted time (simple method)
    public function getFormattedTime()
    {
        return [
            'start' => $this->start_time_utc->format('H:i:s'),
            'end' => $this->end_time_utc->format('H:i:s'),
            'title' => $this->title
        ];
    }

    // Relationship with jobs (many-to-many)
    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'job_shifts')
            ->withTimestamps();
    }

    // Relationship with company
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Relationship with creator
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include shifts for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include active shifts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Get duration in seconds
    public function getDurationInSecondsAttribute()
    {
        $start = \Carbon\Carbon::createFromFormat('H:i:s', $this->start_time_utc);
        $end = \Carbon\Carbon::createFromFormat('H:i:s', $this->end_time_utc);

        if ($end < $start) {
            $end->addDay();
        }

        return $end->diffInSeconds($start);
    }

    // Format duration
    public function getFormattedDurationAttribute()
    {
        $duration = $this->duration_in_seconds;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);

        return "{$hours}h {$minutes}m";
    }
}