<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Helper function to generate file URLs
        $generateFileUrl = function (?string $filePath) {
            if (!$filePath) {
                return null;
            }
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));
            return url('api/v.1/files/' . $encodedPath);
        };

        return [
            // Candidate data
            'candidate' => [
                'id' => $this->candidate->id,
                'first_name' => $this->candidate->first_name,
                'last_name' => $this->candidate->last_name,
                'designation' => $this->candidate->designation,
                'experience' => $this->candidate->experience,
                'phone' => $this->candidate->phone,
                'location' => $this->candidate->location,
                'current_ctc' => $this->candidate->current_ctc,
                'expected_ctc' => $this->candidate->expected_ctc,
                'profile_pic' => $generateFileUrl($this->candidate->profile_pic),
                'resume' => $generateFileUrl($this->candidate->resume),
                'email' => $this->candidate->email,
                'country' => $this->candidate->country,
                'education' => $this->candidate->education,
                'source_id' => $this->candidate->source_id,
                'company_id' => $this->candidate->company_id,
                'created_at' => $this->candidate->created_at?->toDateTimeString(),
                'updated_at' => $this->candidate->updated_at?->toDateTimeString(),
            ],
            // Application data
            'application' => [
                'id' => $this->id,
                'job_post_id' => $this->job_post_id,
                'status' => $this->status,
                'stage_id' => $this->stage_id, // Legacy stage ID
                'company_stage_id' => $this->company_stage_id, // New company stage ID
                'current_stage' => $this->companyStage ? [
                    'id' => $this->companyStage->id,
                    'name' => $this->companyStage->name,
                    'type' => $this->companyStage->type, // FIXED: Changed from candidateStage to companyStage
                    'stage_order' => $this->companyStage->stage_order,
                    'is_active' => $this->companyStage->is_active,
                ] : null,
                'applied_at' => $this->applied_at?->toDateTimeString(),
                'created_at' => $this->created_at?->toDateTimeString(),
                'updated_at' => $this->updated_at?->toDateTimeString(),
            ],
            // Job post data if loaded
            'job_post' => $this->whenLoaded('jobPost', function () {
                return [
                    'id' => $this->jobPost->id,
                    'job_title' => $this->jobPost->job_title,
                    'job_code' => $this->jobPost->job_code,
                    'job_location' => $this->jobPost->job_location,
                    'employment_type' => $this->jobPost->employment_type,
                    'company_industry' => $this->jobPost->company_industry,
                ];
            }),
        ];
    }
}