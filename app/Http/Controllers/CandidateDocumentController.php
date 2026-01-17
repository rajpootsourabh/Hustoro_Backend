<?php

namespace App\Http\Controllers;

use App\Models\CandidateApplication;
use App\Models\CompanyStage;
use App\Models\Document;
use App\Models\CandidateStageDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CandidateDocumentController extends Controller
{
    /**
     * Generate complete file URL
     */
    private function generateFileUrl(?string $path): ?string
    {
        if (!$path) return null;

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        return url("api/v.1/public/{$encodedPath}");
    }

    /**
     * Generate URL for private files with authentication
     */
    private function generatePrivateFileUrl(?string $path): ?string
    {
        if (!$path) return null;

        // For private files, we need to use a route that checks authentication
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        return url("api/v.1/files/{$encodedPath}");
    }

    /**
     * Get documents for candidate's current stage
     */
    public function getCandidateStageDocuments($applicationId)
    {
        $application = CandidateApplication::with([
            'companyStage.documents' => function ($query) {
                $query->orderBy('pivot_document_order');
            },
            'candidateStageDocuments.document'
        ])->findOrFail($applicationId);

        $currentStage = $application->companyStage;

        // Get completed document IDs
        $completedDocuments = $application->candidateStageDocuments->pluck('document_id')->toArray();

        $stageDocuments = $currentStage->documents->map(function ($document) use ($completedDocuments, $application) {
            $candidateStageDoc = $application->candidateStageDocuments
                ->where('document_id', $document->id)
                ->first();

            return [
                'id' => $document->id,
                'code' => $document->code,
                'name' => $document->name,
                'description' => $document->description,
                'url' => $this->generateFileUrl($document->path),
                'file_name' => $document->file_name,
                'is_required' => $document->pivot->is_required,
                'document_order' => $document->pivot->document_order,
                'is_completed' => in_array($document->id, $completedDocuments),
                'completed_at' => $candidateStageDoc?->completed_at,
                'filled_document_url' => $candidateStageDoc?->file_path
                    ? $this->generateFileUrl($candidateStageDoc->file_path)
                    : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'current_stage' => [
                    'id' => $currentStage->id,
                    'name' => $currentStage->name,
                    'type' => $currentStage->type
                ],
                'documents' => $stageDocuments,
                'candidate' => [
                    'id' => $application->candidate->id,
                    'name' => $application->candidate->first_name . ' ' . $application->candidate->last_name,
                    'email' => $application->candidate->email
                ]
            ]
        ]);
    }

    /**
     * NEW: Generate individual document links for for current stage (currently in use)
     */
    public function generateDocumentLinks(Request $request, $applicationId)
    {
        $request->validate([
            'documents' => 'sometimes|array',
            'documents.*' => 'exists:documents,id',
            'expiry_days' => 'sometimes|integer|min:1|max:30'
        ]);

        $application = CandidateApplication::with([
            'companyStage.documents' => function ($query) {
                $query->orderBy('pivot_document_order');
            },
            'candidate'
        ])->findOrFail($applicationId);

        $expiryDays = $request->input('expiry_days', 7);

        // If no specific documents provided, get ALL documents from current stage
        if (empty($request->documents)) {
            $selectedDocuments = $application->companyStage->documents;
        } else {
            $selectedDocuments = Document::whereIn('id', $request->documents)->get();
        }

        $documentLinks = [];

        foreach ($selectedDocuments as $document) {
            // Generate unique token for each document
            $token = Str::random(60);

            // Store token data with document-specific info
            Cache::put(
                "candidate_doc_token_{$token}",
                [
                    'application_id' => $applicationId,
                    'candidate_id' => $application->candidate->id,
                    'document_id' => $document->id,
                    'expires_at' => now()->addDays($expiryDays)
                ],
                now()->addDays($expiryDays)
            );

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $documentUrl = "{$frontendUrl}/candidate/document/{$token}";

            $documentLinks[] = [
                'document_id' => $document->id,
                'document_name' => $document->name,
                'document_code' => $document->code,
                'token' => $token,
                'url' => $documentUrl,
                'expires_at' => now()->addDays($expiryDays)->toDateTimeString(),
                'expiry_days' => $expiryDays
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Document links generated successfully',
            'data' => [
                'candidate' => [
                    'name' => $application->candidate->first_name . ' ' . $application->candidate->last_name,
                    'email' => $application->candidate->email
                ],
                'stage' => $application->companyStage->name,
                'document_links' => $documentLinks,
                'expiry_days' => $expiryDays,
                'total_documents' => count($documentLinks)
            ]
        ]);
    }

    /**
     * NEW: Get all available documents for selection
     */
    public function getAvailableDocuments($applicationId)
    {
        $application = CandidateApplication::with([
            'companyStage.documents' => function ($query) {
                $query->orderBy('pivot_document_order');
            },
            'candidateStageDocuments'
        ])->findOrFail($applicationId);

        $completedDocuments = $application->candidateStageDocuments->pluck('document_id')->toArray();

        $documents = $application->companyStage->documents->map(function ($document) use ($completedDocuments) {
            return [
                'id' => $document->id,
                'code' => $document->code,
                'name' => $document->name,
                'description' => $document->description,
                'is_required' => $document->pivot->is_required,
                'document_order' => $document->pivot->document_order,
                'is_completed' => in_array($document->id, $completedDocuments),
                'is_fillable' => $document->is_fillable
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $documents,
                'total_documents' => $documents->count(),
                'completed_documents' => count($completedDocuments)
            ]
        ]);
    }


    /**
     * NEW: Send selected document links via email
     */
    public function sendDocumentLinksEmail(Request $request, $applicationId)
    {
        $request->validate([
            'document_links' => 'required|array',
            'document_links.*.token' => 'required|string',
            'document_links.*.document_id' => 'required|exists:documents,id',
            'custom_message' => 'nullable|string|max:1000',
            'send_copy_to_recruiter' => 'sometimes|boolean'
        ]);

        $application = CandidateApplication::with(['candidate', 'companyStage'])->findOrFail($applicationId);

        // Get document details for the email
        $documentIds = collect($request->document_links)->pluck('document_id')->toArray();
        $documents = Document::whereIn('id', $documentIds)->get();

        $documentLinks = collect($request->document_links)->map(function ($link) use ($documents) {
            $document = $documents->where('id', $link['document_id'])->first();
            return [
                'token' => $link['token'],
                'url' => $link['url'],
                'document_name' => $document ? $document->name : 'Unknown Document',
                'document_description' => $document ? $document->description : null
            ];
        });

        try {
            // Send email to candidate with individual document links
            Mail::send('emails.individual-document-links', [
                'candidate' => $application->candidate,
                'document_links' => $documentLinks,
                'custom_message' => $request->input('custom_message'),
                'stage' => $application->companyStage,
                'total_documents' => $documentLinks->count()
            ], function ($message) use ($application) {
                $message->to($application->candidate->email)
                    ->subject('Document Completion Links - ' . $application->companyStage->name);
            });

            $emailStatus = 'sent';
            Log::info("Individual document links email sent to {$application->candidate->email}");
        } catch (\Exception $e) {
            Log::error('Failed to send individual document links email: ' . $e->getMessage());
            $emailStatus = 'failed';

            // Add more detailed logging
            Log::error('Email error details:', [
                'candidate_email' => $application->candidate->email,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document links sent via email',
            'email_status' => $emailStatus,
            'documents_sent' => $documentLinks->count(),
            'candidate_email' => $application->candidate->email // Add this for debugging
        ]);
    }

    /**
     * NEW: Verify single document token (currently in use)
     */
    public function showCandidateDocument($token)
    {
        $tokenData = Cache::get("candidate_doc_token_{$token}");

        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired document link'
            ], 404);
        }

        $application = CandidateApplication::with(['candidate', 'companyStage'])->findOrFail($tokenData['application_id']);
        $document = Document::findOrFail($tokenData['document_id']);

        // Check if document is already completed
        $isCompleted = CandidateStageDocument::where([
            'candidate_application_id' => $application->id,
            'document_id' => $document->id,
            'is_completed' => true
        ])->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'candidate' => [
                    'name' => $application->candidate->first_name . ' ' . $application->candidate->last_name,
                    'email' => $application->candidate->email
                ],
                'stage' => $application->companyStage->name,
                'document' => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'description' => $document->description,
                    'url' => $this->generateFileUrl($document->path),
                    'is_required' => true,
                    'is_completed' => $isCompleted,
                    'is_fillable' => $document->is_fillable
                ],
                'token' => $token,
                'expires_at' => $tokenData['expires_at']
            ]
        ]);
    }

    /**
     * Submit completed single document - Store in private directory
     */
    public function submitCompletedDocument(Request $request, $token)
    {
        $request->validate([
            'filled_document' => 'required|file|mimes:pdf|max:10240'
        ]);

        $tokenData = Cache::get("candidate_doc_token_{$token}");

        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 404);
        }

        $application = CandidateApplication::findOrFail($tokenData['application_id']);
        $document = Document::findOrFail($tokenData['document_id']);

        try {
            // Store the filled document in private directory
            $file = $request->file('filled_document');

            // Generate unique filename with timestamp to avoid conflicts
            $fileName = 'filled_' . time() . '_' . Str::random(8) . '_' . $file->getClientOriginalName();

            // Store in private directory: storage/app/private/candidates/filled-documents/
            $filePath = $file->storeAs(
                "candidates/filled-documents/{$application->candidate_id}",
                $fileName,
                'private' // This stores in the private disk
            );

            // Create or update candidate stage document record
            CandidateStageDocument::updateOrCreate(
                [
                    'candidate_application_id' => $application->id,
                    'document_id' => $document->id
                ],
                [
                    'file_path' => $filePath, // This now points to private storage
                    'is_completed' => true,
                    'completed_at' => now(),
                    'uploaded_by' => null // Candidate uploaded it themselves
                ]
            );

            // Remove the used token
            // Cache::forget("candidate_doc_token_{$token}");

            Log::info("Document submitted successfully", [
                'application_id' => $application->id,
                'candidate_id' => $application->candidate_id,
                'document_id' => $document->id,
                'file_path' => $filePath,
                'file_name' => $fileName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document submitted successfully',
                'document' => [
                    'name' => $document->name,
                    'is_completed' => true,
                    'completed_at' => now()->toDateTimeString(),
                    'file_url' => $this->generatePrivateFileUrl($filePath) // Use private URL generator
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to submit document', [
                'token' => $token,
                'application_id' => $tokenData['application_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit document: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Check completion status for all stage documents
     */
    public function getCompletionStatus($applicationId)
    {
        $application = CandidateApplication::with([
            'companyStage.documents',
            'candidateStageDocuments'
        ])->findOrFail($applicationId);

        $totalDocuments = $application->companyStage->documents->count();
        $completedDocuments = $application->candidateStageDocuments->where('is_completed', true)->count();
        $requiredDocuments = $application->companyStage->documents->where('pivot.is_required', true)->count();
        $completedRequired = $application->candidateStageDocuments
            ->where('is_completed', true)
            ->filter(function ($csd) use ($application) {
                return $application->companyStage->documents
                    ->where('id', $csd->document_id)
                    ->where('pivot.is_required', true)
                    ->isNotEmpty();
            })
            ->count();

        $isStageComplete = $completedRequired >= $requiredDocuments;

        return response()->json([
            'success' => true,
            'data' => [
                'total_documents' => $totalDocuments,
                'completed_documents' => $completedDocuments,
                'required_documents' => $requiredDocuments,
                'completed_required' => $completedRequired,
                'is_stage_complete' => $isStageComplete,
                'completion_percentage' => $requiredDocuments > 0 ? round(($completedRequired / $requiredDocuments) * 100) : 0
            ]
        ]);
    }

    /**
     * Generate link for a SINGLE document by document ID
     */
    public function generateSingleDocumentLink($applicationId, $documentId)
    {
        $application = CandidateApplication::with(['companyStage', 'candidate'])->findOrFail($applicationId);
        $document = Document::findOrFail($documentId);

        // Verify the document belongs to the current stage
        $stageDocument = $application->companyStage->documents->where('id', $documentId)->first();

        if (!$stageDocument) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found in current stage'
            ], 404);
        }

        $expiryDays = 7; // Default expiry

        // Generate unique token for the document
        $token = Str::random(60);

        // Store token data with document-specific info
        Cache::put(
            "candidate_doc_token_{$token}",
            [
                'application_id' => $applicationId,
                'candidate_id' => $application->candidate->id,
                'document_id' => $document->id,
                'expires_at' => now()->addDays($expiryDays)
            ],
            now()->addDays($expiryDays)
        );

        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $documentUrl = "{$frontendUrl}/candidate/document/{$token}";

        $documentLink = [
            'document_id' => $document->id,
            'document_name' => $document->name,
            'document_code' => $document->code,
            'token' => $token,
            'url' => $documentUrl,
            'expires_at' => now()->addDays($expiryDays)->toDateTimeString(),
            'expiry_days' => $expiryDays
        ];

        return response()->json([
            'success' => true,
            'message' => 'Document link generated successfully',
            'data' => [
                'candidate' => [
                    'name' => $application->candidate->first_name . ' ' . $application->candidate->last_name,
                    'email' => $application->candidate->email
                ],
                'stage' => $application->companyStage->name,
                'document_link' => $documentLink,
                'expiry_days' => $expiryDays
            ]
        ]);
    }

    /**
     * Get ALL filled documents for a candidate across all stages
     */
   /**
 * Get ALL filled documents for a candidate across all stages - Alternative approach
 */
public function getAllFilledDocuments($applicationId)
{
    $application = CandidateApplication::with(['candidate'])->findOrFail($applicationId);

    // Use direct query with proper joins
    $filledDocuments = CandidateStageDocument::where('candidate_application_id', $applicationId)
        ->where('is_completed', true)
        ->whereNotNull('file_path')
        ->with([
            'document',
            'application.companyStage' // Use 'application' relationship
        ])
        ->get()
        ->map(function ($candidateStageDoc) {
            $document = $candidateStageDoc->document;
            $stage = $candidateStageDoc->application->companyStage ?? null;

            return [
                'id' => $document->id,
                'code' => $document->code,
                'name' => $document->name,
                'description' => $document->description,
                'stage_name' => $stage ? $stage->name : 'Unknown Stage',
                'stage_id' => $stage ? $stage->id : null,
                'completed_at' => $candidateStageDoc->completed_at,
                'filled_document_url' => $this->generatePrivateFileUrl($candidateStageDoc->file_path),
                'file_name' => $document->file_name,
                'uploaded_by' => $candidateStageDoc->uploaded_by,
                'created_at' => $candidateStageDoc->created_at,
                'updated_at' => $candidateStageDoc->updated_at
            ];
        })
        ->sortByDesc('completed_at')
        ->values();

    return response()->json([
        'success' => true,
        'data' => [
            'candidate' => [
                'id' => $application->candidate->id,
                'name' => $application->candidate->first_name . ' ' . $application->candidate->last_name,
                'email' => $application->candidate->email
            ],
            'filled_documents' => $filledDocuments,
            'total_filled' => $filledDocuments->count(),
            'application_id' => $applicationId
        ]
    ]);
}
}
