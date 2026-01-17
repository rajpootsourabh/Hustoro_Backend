<?php
// app/Models/JobTimeLog.php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'candidate_id',
        'start_time',
        'end_time',
        'total_seconds',
        'cumulative_seconds',
        'status',
        'notes'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    // Calculate total active seconds (excluding paused time)
    public function calculateActiveSeconds()
    {
        if ($this->status === 'in_progress') {
            $totalSeconds = $this->cumulative_seconds ?? 0;

            if ($this->start_time) {
                $currentTime = now();
                $activeSeconds = $currentTime->diffInSeconds($this->start_time);
                return $totalSeconds + $activeSeconds;
            }
        }

        return $this->cumulative_seconds ?? 0;
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    // Calculate total seconds
    public function calculateTotalSeconds()
    {
        if ($this->start_time && $this->end_time) {
            // Convert to Carbon instances if they aren't already
            $start = $this->start_time instanceof Carbon
                ? $this->start_time
                : Carbon::parse($this->start_time);

            $end = $this->end_time instanceof Carbon
                ? $this->end_time
                : Carbon::parse($this->end_time);

            return $end->diffInSeconds($start);
        }
        return 0;
    }

    // Format total time
    public function getFormattedTimeAttribute()
    {
        $totalSeconds = $this->total_seconds;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    // Get duration in hours with decimals (for payment calculation)
    public function getDurationInHoursAttribute()
    {
        return $this->total_seconds / 3600;
    }
}
