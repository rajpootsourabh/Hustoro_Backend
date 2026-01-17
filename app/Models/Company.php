<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Company extends Model
{
    use HasFactory;

    // Allow mass-assignment for specific fields
    protected $fillable = [
        'name',
        'website',
        'size',
        'phone_number',
        'company_logo',
        'company_description',
        'evaluating_website',
        'status',
        'approved_at',
    ];

    /**
     * Relationship: A company can have many users.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
    /**
     * Relationship: A company can have many job posts.
     */
    public function jobPosts()
    {
        return $this->hasMany(JobPost::class);
    }

    /**
     * A company can have many employees.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'company_id', 'id');
    }

    /**
     * A company can have many subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class, 'company_id');
    }

    /**
     * Get the active subscription of the company.
     */
    public function activeSubscription()
    {
        return $this->hasOne(CompanySubscription::class, 'company_id')
            ->where('is_active', 1)
            ->where('ends_at', '>=', now());
    }

    /**
     * Get the current plan directly.
     */
    public function currentPlan()
    {
        return $this->activeSubscription?->plan;
    }

    // Add these relationships
    public function companyStages()
    {
        return $this->hasMany(CompanyStage::class)->orderBy('stage_order');
    }

    public function activeStages()
    {
        return $this->hasMany(CompanyStage::class)->active()->orderBy('stage_order');
    }

    public function hiringStages()
    {
        return $this->activeStages()->where('type', 'hiring');
    }

    public function onboardingStages()
    {
        return $this->activeStages()->where('type', 'onboarding');
    }
}
