<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\TextUI\Configuration\Source;

class Candidate extends Model
{
    use HasFactory;

    // public $incrementing = false;
    // protected $keyType = 'uuid';

    protected $fillable = [
        'company_id',
        'first_name',
        'last_name',
        'designation',
        'experience',
        'education',
        'phone',
        'email',
        'country',
        'location',
        'current_ctc',
        'expected_ctc',
        'profile_pic',
        'resume',
        'source_id',
    ];

    /**
     * Applications submitted by the candidate.
     */
    public function applications()
    {
        return $this->hasMany(CandidateApplication::class);
    }

    /**
     * The company this candidate belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Source of the candidate (e.g., referral, LinkedIn).
     */
    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function user()
    {
        return $this->hasOne(User::class, 'email', 'email');
    }

    /**
     * Employees to whom this candidate is assigned.
     */
    public function assignedEmployees()
    {
        return $this->belongsToMany(Employee::class, 'candidate_employee_assignments', 'candidate_id', 'employee_id')
            ->withPivot('assigned_by', 'notes', 'assigned_at') // removed 'status'
            ->withTimestamps();
    }

     public function jobAssignments()
    {
        return $this->hasMany(CandidateJob::class);
    }
}
