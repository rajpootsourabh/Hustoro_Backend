<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Initialize basic user data
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Determine display name and profile image based on role
        $displayName = null;
        $profileImage = null;
        $firstName = null;
        $lastName = null;

        switch ($this->role) {
            case 1: // Employer/Admin
                // For employer, use company data
                if ($this->company) {
                    $displayName = $this->company->name;
                    $profileImage = $this->company->company_logo 
                        ? generateFileUrl($this->company->company_logo) 
                        : null;
                }
                // Keep user's first_name/last_name as fallback (for employer registration)
                $firstName = $this->first_name ?? 'Admin';
                $lastName = $this->last_name ?? '';
                break;

            case 5: // Employee
                // For employee, use employee table data
                if ($this->employee) {
                    $firstName = $this->employee->first_name;
                    $lastName = $this->employee->last_name;
                    $displayName = trim($this->employee->first_name . ' ' . $this->employee->last_name);
                    $profileImage = $this->employee->profile_image 
                        ? generateFileUrl($this->employee->profile_image) 
                        : null;
                }
                break;

            case 6: // Candidate
                // For candidate, use candidate table data
                // Note: Candidates may not have direct user records in your system
                // This assumes a candidate->user relationship exists
                if ($this->candidate) {
                    $firstName = $this->candidate->first_name;
                    $lastName = $this->candidate->last_name;
                    $displayName = trim($this->candidate->first_name . ' ' . $this->candidate->last_name);
                    $profileImage = $this->candidate->profile_pic 
                        ? generateFileUrl($this->candidate->profile_pic) 
                        : null;
                }
                break;

            default:
                // For other roles, try to get from user table or company
                $firstName = $this->first_name;
                $lastName = $this->last_name;
                $displayName = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
                
                if ($this->company) {
                    $profileImage = $this->company->company_logo 
                        ? generateFileUrl($this->company->company_logo) 
                        : null;
                }
                break;
        }

        // Fallback for display name if still null
        if (empty($displayName) && !empty($this->email)) {
            $displayName = explode('@', $this->email)[0];
        }

        // Fallback for profile image if still null
        if (empty($profileImage) && $this->profile_image) {
            $profileImage = generateFileUrl($this->profile_image);
        }

        // Add name fields to data
        $data['first_name'] = $firstName;
        $data['last_name'] = $lastName;
        $data['display_name'] = $displayName;
        $data['profile_image'] = $profileImage;

        // Add role-specific nested data
        switch ($this->role) {
            case 1: // Employer/Admin
                $data['company'] = $this->company ? [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'website' => $this->company->website,
                    'size' => $this->company->size,
                    'phone_number' => $this->company->phone_number,
                    'evaluating_website' => $this->company->evaluating_website,
                    'company_logo' => $this->company->company_logo 
                        ? generateFileUrl($this->company->company_logo) 
                        : null,
                    'company_description' => $this->company->company_description,
                ] : null;
                break;

            case 5: // Employee
                if ($this->employee) {
                    $data['employee'] = [
                        'id' => $this->employee->id,
                        'first_name' => $this->employee->first_name,
                        'last_name' => $this->employee->last_name,
                        'middle_name' => $this->employee->middle_name,
                        'preferred_name' => $this->employee->preferred_name,
                        'work_email' => $this->employee->work_email,
                        'personal_email' => $this->employee->personal_email,
                        'profile_image' => $this->employee->profile_image 
                            ? generateFileUrl($this->employee->profile_image) 
                            : null,
                        'phone' => $this->employee->phone,
                        'gender' => $this->employee->gender,
                        'marital_status' => $this->employee->marital_status,
                        'birthdate' => $this->employee->birthdate,
                        'country' => $this->employee->country,
                        'address' => $this->employee->address,
                        'chat_video_call' => $this->employee->chat_video_call,
                        'social_media' => $this->employee->social_media,
                    ];
                }
                break;

            case 6: // Candidate
                if ($this->candidate) {
                    $data['candidate'] = [
                        'id' => $this->candidate->id,
                        'first_name' => $this->candidate->first_name,
                        'last_name' => $this->candidate->last_name,
                        'email' => $this->candidate->email,
                        'phone' => $this->candidate->phone,
                        'profile_pic' => $this->candidate->profile_pic 
                            ? generateFileUrl($this->candidate->profile_pic) 
                            : null,
                        'country' => $this->candidate->country,
                        'location' => $this->candidate->location,
                        'designation' => $this->candidate->designation,
                        'experience' => $this->candidate->experience,
                        'education' => $this->candidate->education,
                        'current_ctc' => $this->candidate->current_ctc,
                        'expected_ctc' => $this->candidate->expected_ctc,
                    ];
                }
                break;

            default:
                if ($this->company) {
                    $data['company'] = [
                        'id' => $this->company->id,
                        'name' => $this->company->name,
                        'company_logo' => $this->company->company_logo 
                            ? generateFileUrl($this->company->company_logo) 
                            : null,
                    ];
                }
                break;
        }

        return $data;
    }
    
    /**
     * Add additional data to the resource response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        $additionalData = [];

        // Check if frontend_role was set in controller
        if (isset($this->additional['frontend_role'])) {
            $additionalData['frontend_role'] = $this->additional['frontend_role'];
        }

        return $additionalData;
    }
}