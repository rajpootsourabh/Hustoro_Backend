<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\CompanyWelcomeMail;
use App\Models\Company;
use App\Models\CompanyStage;
use App\Models\Document;
use App\Models\StageDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login','register','me']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => "error", 'message' => $validator->messages()], 400);
        }

        $credentials = $request->only('email', 'password');

        // First, find the user to check their status
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'error' => 'Account deactivated',
                'message' => 'Your account has been deactivated. Please contact your administrator.'
            ], 403);
        }

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }


    /**
     * Register a User and create a new Company
     */
    public function register(Request $request)
    {
        Log::info('Register request data:', $request->all());

        $validator = Validator::make($request->all(), [
            'companyName' => ['required', 'string', 'max:255'],
            'companyWebsite' => ['required', 'string'],
            'companySize' => ['nullable', 'integer'],
            'phoneNumber' => ['required', 'string', 'unique:companies,phone_number'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'evaluatingWebsite' => ['required'],
            'role' => ['required'],
            'password' => ['required', 'string', 'min:6'],
            'stage_type' => ['required', 'in:default,custom'],
            'stages' => ['sometimes', 'required_if:stage_type,custom', 'array', 'min:1'], // Use 'sometimes'
            'stages.*.name' => ['required_with:stages', 'string', 'max:255'],
            'stages.*.type' => ['required_with:stages', 'in:hiring,onboarding,custom'],
            'stages.*.order' => ['required_with:stages', 'integer', 'min:1'],
            'stages.*.documents' => ['sometimes', 'array'],
            'stages.*.documents.*.code' => ['required_with:stages.*.documents', 'exists:documents,code'],
            'stages.*.documents.*.isRequired' => ['required_with:stages.*.documents', 'boolean'],
            'stages.*.documents.*.order' => ['required_with:stages.*.documents', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => "error", 'message' => $validator->messages()], 400);
        }

        try {
            DB::beginTransaction();

            // Create a new company record
            $company = Company::create([
                'name' => $request->companyName,
                'website' => $request->companyWebsite,
                'size' => $request->companySize ?? 1,
                'phone_number' => $request->phoneNumber,
                'evaluating_website' => $request->evaluatingWebsite,
            ]);

            // Create stages based on stage_type
            if ($request->stage_type === 'default') {
                $this->createDefaultStages($company);
                $message = 'Company registered successfully with default stages';
            } else {
                // Only create custom stages if stages are provided
                if ($request->has('stages') && !empty($request->stages)) {
                    $this->createCompanyStages($company, $request->stages);
                    $message = 'Company registered successfully with custom stages';
                } else {
                    throw new \Exception('Custom stages required when stage_type is custom');
                }
            }

            // Create the user and associate with company
            $user = User::create([
                'company_id' => $company->id,
                'first_name' => "Admin",
                'last_name' => "",
                'email' => $request->email,
                'role' => 1, // Admin role
                'is_active' => 1,
                'password' => Hash::make($request->password),
            ]);

            DB::commit();

            // Send welcome email
            $data = [
                'company_name' => $request->companyName,
                'email' => $user->email,
                'password' => $request->password,
                'role' => 'Admin',
            ];

            Mail::to($data['email'])->send(new CompanyWelcomeMail($data));

            // Return user with company stages and documents
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'user' => new UserResource($user),
                'company_stages' => $company->companyStages()->with(['documents' => function ($query) {
                    $query->orderBy('document_order');
                }])->get()
            ]);
        } catch (\Exception $exp) {
            DB::rollBack();
            Log::error('Registration failed: ' . $exp->getMessage());
            Log::error('Registration stack trace: ' . $exp->getTraceAsString());
            return response()->json(['status' => "error", 'message' => $exp->getMessage()], 400);
        }
    }

    /**
     * Create custom stages for a company with documents
     */
    private function createCompanyStages(Company $company, array $stagesData)
    {
        foreach ($stagesData as $stageData) {
            $this->createCompanyStage($company, $stageData);
        }
    }

    /**
     * Create a single company stage with documents
     */
    private function createCompanyStage(Company $company, array $stageData)
    {
        // Create the stage
        $stage = CompanyStage::create([
            'company_id' => $company->id,
            'name' => $stageData['name'],
            'type' => $stageData['type'],
            'stage_order' => $stageData['order'],
            'is_active' => true
        ]);

        // Add documents if provided
        if (isset($stageData['documents']) && is_array($stageData['documents'])) {
            foreach ($stageData['documents'] as $documentData) {
                $document = Document::where('code', $documentData['code'])->first();
                if ($document) {
                    StageDocument::create([
                        'company_stage_id' => $stage->id,
                        'document_id' => $document->id,
                        'is_required' => $documentData['isRequired'],
                        'document_order' => $documentData['order']
                    ]);
                } else {
                    Log::warning("Document with code {$documentData['code']} not found for stage {$stageData['name']}");
                }
            }
        }

        return $stage;
    }

    /**
     * Create default stages for a company using the exact stages and forms
     */
    private function createDefaultStages(Company $company)
    {
        $defaultStages = [
            [
                'name' => 'Pre-Hire',
                'type' => 'hiring',
                'order' => 1,
                'documents' => [
                    ['code' => '1020', 'isRequired' => true, 'order' => 1],  // Employment Application
                    ['code' => '1021', 'isRequired' => true, 'order' => 2],  // Equal Employment Opportunity
                    ['code' => '1050', 'isRequired' => true, 'order' => 3],  // Skills Checklist
                    ['code' => '1060', 'isRequired' => true, 'order' => 4],  // Request for Reference
                    ['code' => '1070', 'isRequired' => true, 'order' => 5],  // Background Check Authorization
                    ['code' => '1204', 'isRequired' => true, 'order' => 6]   // Care Associate Availability
                ]
            ],
            [
                'name' => 'Onboarding',
                'type' => 'onboarding',
                'order' => 2,
                'documents' => [
                    ['code' => '1010', 'isRequired' => true, 'order' => 1],  // Employee Personal Action
                    ['code' => '1201', 'isRequired' => true, 'order' => 2],  // Handbook Acknowledgement
                    ['code' => '1202', 'isRequired' => true, 'order' => 3],  // Orientation Acknowledgement
                    ['code' => '1203', 'isRequired' => true, 'order' => 4],  // Orientation Curriculum
                    ['code' => '1220', 'isRequired' => true, 'order' => 5],  // Abuse_Neglect Policy
                    ['code' => '1530', 'isRequired' => true, 'order' => 6],  // Care Associate Schedule Acknowledgement
                    ['code' => '1600', 'isRequired' => true, 'order' => 7],  // Emergency Contact Information
                    ['code' => '1720', 'isRequired' => true, 'order' => 8],  // Hepatitis B_Consent-Declination
                    ['code' => '1740', 'isRequired' => true, 'order' => 9],  // Pre-Employment Drug Consent
                    ['code' => '2900', 'isRequired' => true, 'order' => 10], // ID Agreement
                    ['code' => '4000', 'isRequired' => true, 'order' => 11], // Nondiclosure_Noncompete Agreement
                    ['code' => 'I-9', 'isRequired' => true, 'order' => 12],  // I-9 Form
                    ['code' => 'W-4', 'isRequired' => true, 'order' => 13]   // W-4 Form
                ]
            ]
        ];

        $this->createCompanyStages($company, $defaultStages);
    }

    /**
     * Get available documents for frontend (for checkboxes)
     */
    public function getAvailableDocuments()
    {
        $documents = Document::orderBy('code')->get();

        return response()->json([
            'status' => 'success',
            'data' => $documents
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $user = auth()->user();

        // Load necessary relationships based on role
        $user->load(['company', 'employee']);

        // Determine manager status for role 5 (employees)
        $isManager = false;
        $managerRole = 4; // Frontend manager role

        if ($user->role == 5 && $user->employee) {
            // Check if employee has associates (is a manager)
            $isManager = $user->employee->isManager();
        }

        $response = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => new UserResource($user),
        ];

        // Add employee_id and manager status for role 5
        if ($user->role == 5) {
            $response['employee_id'] = $user->employee_id;
            $response['is_manager'] = $isManager;

            // If employee is manager, set role to 4 for frontend
            if ($isManager) {
                $response['user']->additional(['frontend_role' => 4]);
            }
        }

        return response()->json($response);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user();
        $user->load(['company', 'employee']);

        // Determine manager status for role 5 (employees)
        $isManager = false;
        if ($user->role == 5 && $user->employee) {
            $isManager = $user->employee->isManager();
        }

        $response = [
            'status' => "success",
            'user' => new UserResource($user),
            'is_manager' => $isManager,
        ];

        if ($user->role == 5) {
            $response['employee_id'] = $user->employee_id;
        }

        return response()->json($response);
    }
}
