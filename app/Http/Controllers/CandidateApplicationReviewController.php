<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateApplicationReview;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ApiResponse;

class CandidateApplicationReviewController extends Controller
{
    use ApiResponse;

    public function addReview(Request $request, $applicationId)
    {
        try {
            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'feedback' => 'nullable|string',
            ]);

            $validated['candidate_application_id'] = $applicationId;
            $validated['reviewed_by'] = auth()->id(); // 👈 Automatically get logged-in user ID

            $review = CandidateApplicationReview::create($validated);

            return $this->successResponse($review, 'Review added successfully');
        } catch (\Exception $e) {
            Log::error('Failed to add review', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'application_id' => $applicationId,
                'user_id' => auth()->id(),
            ]);

            return $this->errorResponse('Failed to add review', 500);
        }
    }



    public function getReviews($candidateApplicationId)
    {
        try {
            $reviews = CandidateApplicationReview::where('candidate_application_id', $candidateApplicationId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($review) {
                    // Get the user who made the review
                    $user = User::find($review->reviewed_by);

                    // Initialize variables
                    $reviewerName = null;
                    $reviewerProfilePic = null;
                    $reviewerCountry = null;
                    $reviewerAddress = null;
                    $reviewerRole = null;

                    if ($user) {
                        $reviewerRole = $user->role;

                        // Fetch details based on role
                        switch ($user->role) {
                            case 1: // Employer/Admin
                                $company = Company::where('id', $user->company_id)->first();
                                if ($company) {
                                    $reviewerName = $company->name;
                                    $reviewerProfilePic = $company->company_logo
                                        ? generateFileUrl($company->company_logo)
                                        : null;
                                    // Company doesn't have country/address in your schema
                                    // but you can add if needed
                                } else {
                                    // Fallback to user name if company not found
                                    $reviewerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                                    if (empty($reviewerName)) {
                                        $reviewerName = 'Employer';
                                    }
                                }
                                break;

                            case 5: // Employee
                                $employee = Employee::where('id', $user->employee_id)->first();
                                if ($employee) {
                                    $reviewerName = trim($employee->first_name . ' ' . $employee->last_name);
                                    $reviewerProfilePic = $employee->profile_image
                                        ? generateFileUrl($employee->profile_image)
                                        : null;
                                    $reviewerCountry = $employee->country;
                                    $reviewerAddress = $employee->address;
                                } else {
                                    // Fallback to user name
                                    $reviewerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                                    if (empty($reviewerName)) {
                                        $reviewerName = 'Employee';
                                    }
                                }
                                break;

                            case 6: // Candidate
                                // IMPORTANT: Candidates are linked by EMAIL, not user_id
                                // Look for candidate with matching email
                                $candidate = Candidate::where('email', $user->email)->first();

                                if ($candidate) {
                                    $reviewerName = trim($candidate->first_name . ' ' . $candidate->last_name);
                                    $reviewerProfilePic = $candidate->profile_pic
                                        ? generateFileUrl($candidate->profile_pic)
                                        : null;
                                    $reviewerCountry = $candidate->country;
                                    $reviewerAddress = $candidate->location; // candidates table has 'location' field
                                } else {
                                    // Fallback 1: Try to find candidate by name if email doesn't match
                                    $candidate = Candidate::where('first_name', $user->first_name ?? '')
                                        ->where('last_name', $user->last_name ?? '')
                                        ->first();

                                    if ($candidate) {
                                        $reviewerName = trim($candidate->first_name . ' ' . $candidate->last_name);
                                        $reviewerProfilePic = $candidate->profile_pic
                                            ? generateFileUrl($candidate->profile_pic)
                                            : null;
                                        $reviewerCountry = $candidate->country;
                                        $reviewerAddress = $candidate->location;
                                    } else {
                                        // Fallback 2: Use user data
                                        $reviewerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                                        if (empty($reviewerName)) {
                                            // Use email username as fallback
                                            $reviewerName = explode('@', $user->email)[0] ?? 'Candidate';
                                        }
                                    }
                                }
                                break;

                            default:
                                // For other roles or fallback
                                $reviewerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                                if (empty($reviewerName)) {
                                    // Use email username as fallback
                                    $reviewerName = explode('@', $user->email)[0] ?? 'User';
                                }
                                break;
                        }

                        // Final fallback for profile picture
                        if (empty($reviewerProfilePic) && !empty($user->profile_image)) {
                            $reviewerProfilePic = generateFileUrl($user->profile_image);
                        }
                    } else {
                        // User not found - use default values
                        $reviewerName = 'Unknown User';
                    }

                    return [
                        'id' => $review->id,
                        'candidate_application_id' => $review->candidate_application_id,
                        'rating' => $review->rating,
                        'feedback' => $review->feedback,
                        'created_at' => $review->created_at,
                        'updated_at' => $review->updated_at,
                        'reviewed_by_id' => $review->reviewed_by,
                        'reviewer_name' => $reviewerName,
                        'reviewer_profile_pic' => $reviewerProfilePic,
                        'reviewer_country' => $reviewerCountry,
                        'reviewer_address' => $reviewerAddress,
                        'reviewer_role' => $reviewerRole,
                        'reviewer_email' => $user->email ?? null, // Add email for debugging
                    ];
                });

            return $this->successResponse($reviews, 'Reviews fetched successfully');
        } catch (\Exception $e) {
            Log::error('Failed to fetch reviews', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'applicationId' => $candidateApplicationId
            ]);

            return $this->errorResponse('Failed to fetch reviews', 500);
        }
    }
}
