<?php
// app/Http/Controllers/InvoiceController.php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobTimeLog;
use App\Models\CandidateEmployeeAssignment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    use ApiResponse;

    /**
     * Get employee ID from authenticated user
     */
    private function getEmployeeId()
    {
        $user = Auth::user();

        // Try to get employee_id from user model
        if ($user->employee_id) {
            return $user->employee_id;
        }

        // If not directly available, try to find employee by user's email
        $employee = Employee::where('work_email', $user->email)
            ->orWhere('personal_email', $user->email)
            ->first();

        if ($employee) {
            return $employee->id;
        }

        return null;
    }


    /**
     * Display candidate work hours report for logged-in employee WITH CLIENT DETAILS
     */
    // app/Http/Controllers/InvoiceController.php - Update the candidateWorkHoursByEmployee method
    // app/Http/Controllers/InvoiceController.php - Update the candidateWorkHoursByEmployee method
    public function candidateWorkHoursByEmployee(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_id' => 'nullable|exists:jobs,id',
        ]);

        $companyId = Auth::user()->company_id;
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        // Get candidates assigned to this employee
        $assignedCandidates = CandidateEmployeeAssignment::where('employee_id', $employeeId)
            ->with(['candidate' => function ($query) {
                $query->select(['id', 'first_name', 'last_name', 'email', 'phone']);
            }])
            ->get()
            ->pluck('candidate_id');

        if ($assignedCandidates->isEmpty()) {
            return $this->successResponse(
                [
                    'employee' => null,
                    'candidates' => [],
                    'filters' => [
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'total_candidates' => 0,
                        'total_hours' => 0,
                    ]
                ],
                'No candidates assigned to you'
            );
        }

        // Get all assigned candidates first
        $candidates = Candidate::where('candidates.company_id', $companyId)
            ->whereIn('candidates.id', $assignedCandidates)
            ->select([
                'candidates.id',
                'candidates.first_name',
                'candidates.last_name',
                'candidates.email',
                'candidates.phone',
                'candidates.designation',
            ])
            ->get();

        $candidatesWithHours = collect();

        foreach ($candidates as $candidate) {
            // Build query for job time logs for this candidate within date range
            $timeLogsQuery = JobTimeLog::where('candidate_id', $candidate->id)
                ->where('status', 'completed');

            // Apply date filters if provided
            if ($request->start_date) {
                $timeLogsQuery->whereDate('start_time', '>=', $request->start_date);
            }
            if ($request->end_date) {
                $timeLogsQuery->whereDate('start_time', '<=', $request->end_date);
            }

            // Get time logs and calculate totals
            $timeLogs = $timeLogsQuery->get();
            $totalSeconds = $timeLogs->sum('total_seconds');

            // Skip candidates with zero seconds (no work at all)
            if ($totalSeconds <= 0) {
                continue;
            }

            // Calculate hours with more precision
            $totalHours = $totalSeconds / 3600; // Keep as float for precision

            // Format hours for display - show decimal places only if needed
            $formattedHours = $this->formatHoursForDisplay($totalHours);

            // Get the active job for this candidate
            $activeJob = Job::where('candidate_id', $candidate->id)
                ->whereIn('status', ['assigned', 'confirmed', 'in_progress'])
                ->first();

            // Get client info if job exists
            $client = null;
            $jobTitle = null;
            $clientId = null;

            if ($activeJob) {
                $jobTitle = $activeJob->job_title;
                $clientId = $activeJob->client_id;

                if ($clientId) {
                    $client = Client::find($clientId);
                }
            }

            // Get work period dates from the filtered logs
            $firstWorkDate = $timeLogs->min('start_time');
            $lastWorkDate = $timeLogs->max('end_time');

            $workPeriod = null;
            if ($firstWorkDate && $lastWorkDate) {
                $workPeriod = [
                    'first_work_date' => Carbon::parse($firstWorkDate)->format('Y-m-d'),
                    'last_work_date' => Carbon::parse($lastWorkDate)->format('Y-m-d'),
                ];
            }

            // Prepare candidate data
            $candidateData = [
                'id' => $candidate->id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'designation' => $candidate->designation,
                'total_seconds' => $totalSeconds,
                'total_hours' => $totalHours, // Raw hours for calculations
                'formatted_hours' => $formattedHours, // Formatted for display
                'work_period' => $workPeriod,
                'has_hours_in_period' => true,
                'job_id' => $activeJob ? $activeJob->id : null,
                'job_title' => $jobTitle,
                'job_status' => $activeJob ? $activeJob->status : null,
                'client_id' => $clientId,
                'client' => $client ? [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->contact_number,
                    'address' => $client->address,
                ] : null,
                'client_name' => $client ? $client->name : null,
            ];

            $candidatesWithHours->push((object) $candidateData);
        }

        // Add employee info to response
        $employee = Employee::find($employeeId);

        return $this->successResponse([
            'employee' => [
                'id' => $employeeId,
                'name' => $employee ? $employee->first_name . ' ' . $employee->last_name : null,
            ],
            'candidates' => $candidatesWithHours,
            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'total_candidates' => $candidatesWithHours->count(),
                'total_hours' => $candidatesWithHours->sum('total_hours'),
            ]
        ], 'Candidate work hours retrieved successfully');
    }

    /**
     * Format hours for display - handle small time intervals
     */
    private function formatHoursForDisplay($hours)
    {
        // If less than 0.01 hours (36 seconds), show in minutes or seconds
        if ($hours < 0.01) {
            $minutes = $hours * 60;

            // If less than 0.1 minutes (6 seconds), show in seconds
            if ($minutes < 0.1) {
                $seconds = round($hours * 3600);
                return $seconds . ' sec';
            }

            // Show in minutes with 1 decimal place
            return round($minutes, 1) . ' min';
        }

        // For hours, show up to 4 decimal places if needed
        $rounded = round($hours, 4);

        // Remove trailing zeros
        $formatted = rtrim(rtrim(sprintf('%.4f', $rounded), '0'), '.');

        return $formatted . ' hrs';
    }
    /**
     * Get work hours summary for the logged-in employee's dashboard
     */
    public function employeeDashboardSummary(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
        ]);

        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        // Get date range based on period
        $dateRange = $this->getDateRange($request->period);

        // Get assigned candidates count
        $assignedCandidatesCount = CandidateEmployeeAssignment::where('employee_id', $employeeId)
            ->count();

        // Get total work hours for assigned candidates
        $workHours = JobTimeLog::whereHas('candidate.candidateEmployeeAssignments', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId);
        })
            ->where('status', 'completed')
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('start_time', [$dateRange['start'], $dateRange['end']]);
            })
            ->sum('total_seconds');

        $totalHours = round($workHours / 3600, 2);

        // Get active jobs count for assigned candidates
        $activeJobsCount = Job::whereHas('candidate.candidateEmployeeAssignments', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId);
        })
            ->whereIn('status', ['assigned', 'confirmed', 'in_progress'])
            ->count();

        // Get recent time logs
        $recentLogs = JobTimeLog::whereHas('candidate.candidateEmployeeAssignments', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId);
        })
            ->with(['candidate', 'job'])
            ->where('status', 'completed')
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                $log->hours = round($log->total_seconds / 3600, 2);
                return $log;
            });

        return $this->successResponse([
            'employee_id' => $employeeId,
            'assigned_candidates_count' => $assignedCandidatesCount,
            'total_work_hours' => $totalHours,
            'active_jobs_count' => $activeJobsCount,
            'recent_time_logs' => $recentLogs,
            'period' => $request->period ?? 'all',
            'date_range' => $dateRange,
        ], 'Dashboard summary retrieved successfully');
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber($companyId)
    {
        $prefix = 'INV-' . date('Y') . '-';
        $lastInvoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }


    /**
     * Generate PDF for invoice
     */
    public function generatePDF($id)
    {
        $invoice = Invoice::with(['candidate', 'job', 'company', 'client'])
            ->where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        // Check if PDF exists and if it's older than invoice update
        $shouldRegenerate = true;

        if ($invoice->pdf_path && Storage::exists($invoice->pdf_path)) {
            $pdfModifiedTime = Storage::lastModified($invoice->pdf_path);
            $invoiceModifiedTime = strtotime($invoice->updated_at);

            // Regenerate only if invoice was updated after PDF was created
            if ($invoiceModifiedTime <= $pdfModifiedTime) {
                $shouldRegenerate = false;
            }
        }

        if ($shouldRegenerate) {
            $pdfPath = $this->generateInvoicePDF($invoice);
        } else {
            $pdfPath = $invoice->pdf_path;
        }

        return response()->download(
            storage_path('app/' . $pdfPath),
            "Invoice-{$invoice->invoice_number}.pdf"
        );
    }


    /**
     * Generate invoice PDF and save to storage
     */
    private function generateInvoicePDF($invoice)
    {
        $company = Company::find($invoice->company_id);
        $employee = Employee::find($invoice->employee_id);

        // Get client - either from relationship or from stored client_details
        $client = null;
        if ($invoice->client) {
            $client = $invoice->client;
        } elseif ($invoice->client_details) {
            $client = (object) $invoice->client_details;
        } elseif ($invoice->job && $invoice->job->client) {
            $client = $invoice->job->client;
        }

        $data = [
            'invoice' => $invoice,
            'company' => $company,
            'employee' => $employee,
            'client' => $client,
            'items' => is_array($invoice->items) ? $invoice->items : json_decode($invoice->items, true),
            'date' => Carbon::parse($invoice->invoice_date)->format('F d, Y'),
            'due_date' => Carbon::parse($invoice->due_date)->format('F d, Y'),
        ];

        $pdf = PDF::loadView('invoices.pdf', $data);

        $filename = "invoices/invoice_{$invoice->invoice_number}.pdf";
        Storage::put($filename, $pdf->output());

        // Update invoice with PDF path
        $invoice->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, $id)
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        $invoice = Invoice::where('employee_id', $employeeId)
            ->findOrFail($id);

        $request->validate([
            'status' => 'required|in:draft,sent,paid,overdue',
            'payment_method' => 'nullable|string',
            'payment_date' => 'nullable|date',
        ]);

        $updateData = [
            'status' => $request->status,
        ];

        if ($request->payment_method) {
            $updateData['payment_method'] = $request->payment_method;
        }

        if ($request->payment_date) {
            $updateData['payment_date'] = $request->payment_date;
        }

        if ($request->status === 'paid' && !$invoice->payment_date) {
            $updateData['payment_date'] = Carbon::now();
        }

        $invoice->update($updateData);

        // Regenerate PDF to reflect changes
        $this->generateInvoicePDF($invoice);

        return $this->successResponse(
            $invoice->fresh(),
            'Invoice updated successfully'
        );
    }

    /**
     * Get invoices for a specific candidate
     */
    public function getCandidateInvoices(Request $request, $candidateId)
    {
        $request->validate([
            'status' => 'nullable|in:draft,sent,paid,overdue',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        // Verify candidate is assigned to employee
        $isAssigned = CandidateEmployeeAssignment::where('employee_id', $employeeId)
            ->where('candidate_id', $candidateId)
            ->exists();

        if (!$isAssigned) {
            return $this->errorResponse('Candidate is not assigned to you', 403);
        }

        $query = Invoice::with(['candidate', 'job', 'client'])
            ->where('candidate_id', $candidateId)
            ->where('employee_id', $employeeId)
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->start_date, function ($q) use ($request) {
                $q->whereDate('invoice_date', '>=', $request->start_date);
            })
            ->when($request->end_date, function ($q) use ($request) {
                $q->whereDate('invoice_date', '<=', $request->end_date);
            })
            ->orderBy('created_at', 'desc');

        $invoices = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return $this->successResponse([
            'candidate_id' => $candidateId,
            'invoices' => $invoices,
            'total_invoices' => $invoices instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $invoices->total()
                : $invoices->count(),
            'total_amount' => $invoices->sum('total_amount')
        ], 'Candidate invoices retrieved successfully');
    }

    /**
     * Create invoice for selected candidates assigned to logged-in employee WITH CLIENT DETAILS
     */
    public function createInvoiceForEmployee(Request $request)
    {
        $request->validate([
            'candidate_ids' => 'required|array',
            'candidate_ids.*' => 'exists:candidates,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'hourly_rate' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'due_days' => 'nullable|integer|min:1|max:90',
            'client_id' => 'nullable|exists:clients,id', // Optional: override client
        ]);

        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        DB::beginTransaction();
        try {
            $companyId = Auth::user()->company_id;
            $userId = Auth::id();

            // Verify that all candidates are assigned to this employee
            $assignedCandidateIds = CandidateEmployeeAssignment::where('employee_id', $employeeId)
                ->whereIn('candidate_id', $request->candidate_ids)
                ->pluck('candidate_id')
                ->toArray();

            $unauthorizedCandidates = array_diff($request->candidate_ids, $assignedCandidateIds);

            if (!empty($unauthorizedCandidates)) {
                return $this->errorResponse(
                    'Some candidates are not assigned to you',
                    403,
                    ['unauthorized_candidates' => $unauthorizedCandidates]
                );
            }

            $invoices = [];
            $errors = [];

            foreach ($request->candidate_ids as $candidateId) {
                $candidate = Candidate::findOrFail($candidateId);

                // Calculate total hours worked in the period
                $timeLogs = JobTimeLog::where('candidate_id', $candidateId)
                    ->where('status', 'completed')
                    ->whereBetween(DB::raw('DATE(start_time)'), [
                        $request->period_start,
                        $request->period_end
                    ])
                    ->get();

                if ($timeLogs->isEmpty()) {
                    $errors[] = "Candidate {$candidate->first_name} {$candidate->last_name} has no logged hours in the selected period.";
                    continue;
                }

                $totalSeconds = $timeLogs->sum('total_seconds');
                $totalHours = round($totalSeconds / 3600, 2);

                // Get the active job for this candidate
                $activeJob = Job::where('candidate_id', $candidateId)
                    ->whereIn('status', ['assigned', 'confirmed', 'in_progress'])
                    ->first();

                // Get client details (use provided client_id or job's client)
                $clientId = $request->client_id;
                $clientDetails = null;

                if ($clientId) {
                    $client = Client::find($clientId);
                    if ($client) {
                        $clientDetails = [
                            'id' => $client->id,
                            'name' => $client->name,
                            'email' => $client->email,
                            'phone' => $client->contact_number,
                            'address' => $client->address,
                        ];
                        $clientId = $client->id; // Use this for client_id field
                    }
                } elseif ($activeJob && $activeJob->client_id) {
                    $client = Client::find($activeJob->client_id);
                    if ($client) {
                        $clientDetails = [
                            'id' => $client->id,
                            'name' => $client->name,
                            'email' => $client->email,
                            'phone' => $client->contact_number,
                            'address' => $client->address,
                        ];
                        $clientId = $client->id; // Use this for client_id field
                    }
                }

                // Create invoice items from time logs
                $items = [];
                foreach ($timeLogs as $log) {
                    $job = Job::find($log->job_id);
                    $items[] = [
                        'date' => $log->start_time->format('Y-m-d'),
                        'description' => $job ? "Worked on {$job->job_title}" : "Work hours",
                        'hours' => round($log->total_seconds / 3600, 2),
                        'rate' => $request->hourly_rate,
                        'amount' => round(($log->total_seconds / 3600) * $request->hourly_rate, 2),
                        'time_log_id' => $log->id
                    ];
                }

                $subtotal = $totalHours * $request->hourly_rate;
                $taxRate = $request->tax_rate ?? 0;
                $taxAmount = $subtotal * ($taxRate / 100);
                $discount = $request->discount ?? 0;
                $totalAmount = $subtotal + $taxAmount - $discount;

                // Generate invoice number
                $invoiceNumber = $this->generateInvoiceNumber($companyId);

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'company_id' => $companyId,
                    'candidate_id' => $candidateId,
                    'job_id' => $activeJob?->id,
                    'client_id' => $clientId, // Store client_id for relationship
                    'employee_id' => $employeeId,
                    'invoice_date' => Carbon::now(),
                    'due_date' => Carbon::now()->addDays($request->due_days ?? 30),
                    'status' => 'draft',
                    'billing_name' => "{$candidate->first_name} {$candidate->last_name}",
                    'billing_email' => $candidate->email,
                    'billing_phone' => $candidate->phone,
                    'client_details' => $clientDetails, // Store client details as JSON
                    'items' => $items,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'discount' => $discount,
                    'total_amount' => $totalAmount,
                    'total_hours' => $totalHours,
                    'hourly_rate' => $request->hourly_rate,
                    'period_start' => $request->period_start,
                    'period_end' => $request->period_end,
                    'notes' => $request->notes,
                    'created_by' => $userId
                ]);

                // Generate PDF immediately
                $pdfPath = $this->generateInvoicePDF($invoice);
                $invoice->update(['pdf_path' => $pdfPath]);

                $invoices[] = $invoice;
            }

            DB::commit();

            return $this->successResponse([
                'invoices' => $invoices,
                'errors' => $errors,
                'total_generated' => count($invoices),
                'total_failed' => count($errors),
                'employee_id' => $employeeId,
                'pdf_available' => true
            ], count($invoices) . ' invoice(s) created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to create invoices: ' . $e->getMessage(),
                500
            );
        }
    }


    /**
     * Get invoices created by logged-in employee
     */
    public function getEmployeeInvoices(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:draft,sent,paid,overdue',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        $query = Invoice::with(['candidate', 'job'])
            ->where('employee_id', $employeeId)
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->start_date, function ($q) use ($request) {
                $q->whereDate('invoice_date', '>=', $request->start_date);
            })
            ->when($request->end_date, function ($q) use ($request) {
                $q->whereDate('invoice_date', '<=', $request->end_date);
            })
            ->orderBy('created_at', 'desc');

        $invoices = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        return $this->successResponse([
            'invoices' => $invoices,
            'employee_id' => $employeeId,
            'total_invoices' => $invoices instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $invoices->total()
                : $invoices->count(),
            'total_amount' => $invoices->sum('total_amount')
        ], 'Your invoices retrieved successfully');
    }

    /**
     * Get invoice statistics for employee
     */
    public function employeeInvoiceStatistics()
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        $stats = Invoice::where('employee_id', $employeeId)
            ->select([
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft_invoices'),
                DB::raw('SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent_invoices'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid_invoices'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue_invoices'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN total_amount ELSE 0 END) as paid_amount'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN total_amount ELSE 0 END) as overdue_amount'),
                DB::raw('DATE(invoice_date) as date')
            ])
            ->groupBy(DB::raw('DATE(invoice_date)'))
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return $this->successResponse([
            'employee_id' => $employeeId,
            'statistics' => $stats,
            'summary' => [
                'total_candidates_invoiced' => Invoice::where('employee_id', $employeeId)
                    ->distinct('candidate_id')
                    ->count('candidate_id'),
                'total_jobs_invoiced' => Invoice::where('employee_id', $employeeId)
                    ->whereNotNull('job_id')
                    ->distinct('job_id')
                    ->count('job_id'),
                'total_clients_invoiced' => Invoice::where('employee_id', $employeeId)
                    ->whereNotNull('client_id')
                    ->distinct('client_id')
                    ->count('client_id'),
            ]
        ], 'Invoice statistics retrieved successfully');
    }

    /**
     * Helper method to get date range based on period
     */
    private function getDateRange($period)
    {
        switch ($period) {
            case 'today':
                return [
                    'start' => Carbon::today(),
                    'end' => Carbon::today()->endOfDay()
                ];
            case 'week':
                return [
                    'start' => Carbon::now()->startOfWeek(),
                    'end' => Carbon::now()->endOfWeek()
                ];
            case 'month':
                return [
                    'start' => Carbon::now()->startOfMonth(),
                    'end' => Carbon::now()->endOfMonth()
                ];
            case 'quarter':
                return [
                    'start' => Carbon::now()->startOfQuarter(),
                    'end' => Carbon::now()->endOfQuarter()
                ];
            case 'year':
                return [
                    'start' => Carbon::now()->startOfYear(),
                    'end' => Carbon::now()->endOfYear()
                ];
            default:
                return null;
        }
    }

    // Update the existing methods to use employee_id from auth if needed

    /**
     * Update invoice (only if created by the logged-in employee)
     */
    public function update(Request $request, $id)
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        $invoice = Invoice::where('employee_id', $employeeId)
            ->findOrFail($id);

        $request->validate([
            'status' => 'nullable|in:draft,sent,paid,overdue',
            'hourly_rate' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'payment_date' => 'nullable|date',
            'items' => 'nullable|array',
        ]);

        if ($request->has('items')) {
            // Recalculate totals if items are updated
            $items = $request->items;
            $totalHours = collect($items)->sum('hours');
            $subtotal = collect($items)->sum('amount');
            $taxRate = $request->tax_rate ?? $invoice->tax_rate;
            $taxAmount = $subtotal * ($taxRate / 100);
            $discount = $request->discount ?? $invoice->discount;
            $totalAmount = $subtotal + $taxAmount - $discount;

            $invoice->update([
                'items' => $items,
                'total_hours' => $totalHours,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'hourly_rate' => $totalHours > 0 ? $subtotal / $totalHours : $invoice->hourly_rate
            ]);
        }

        $updateData = $request->only([
            'status',
            'notes',
            'payment_method',
            'payment_date'
        ]);

        if ($request->has('tax_rate') && !$request->has('items')) {
            // Update tax if items not changed
            $taxRate = $request->tax_rate;
            $taxAmount = $invoice->subtotal * ($taxRate / 100);
            $totalAmount = $invoice->subtotal + $taxAmount - $invoice->discount;

            $updateData['tax_rate'] = $taxRate;
            $updateData['tax_amount'] = $taxAmount;
            $updateData['total_amount'] = $totalAmount;
        }

        if ($request->has('discount') && !$request->has('items')) {
            $discount = $request->discount;
            $totalAmount = $invoice->subtotal + $invoice->tax_amount - $discount;

            $updateData['discount'] = $discount;
            $updateData['total_amount'] = $totalAmount;
        }

        $invoice->update($updateData);

        return $this->successResponse(
            $invoice->fresh(),
            'Invoice updated successfully'
        );
    }

    /**
     * Delete invoice (only if created by the logged-in employee)
     */
    public function destroy($id)
    {
        $employeeId = $this->getEmployeeId();

        if (!$employeeId) {
            return $this->errorResponse('Employee not found for this user', 404);
        }

        $invoice = Invoice::where('employee_id', $employeeId)
            ->findOrFail($id);

        if ($invoice->status === 'paid') {
            return $this->errorResponse('Cannot delete a paid invoice', 400);
        }

        $invoice->delete();

        return $this->successResponse(null, 'Invoice deleted successfully');
    }


    /**
     * Get invoice statistics
     */
    public function statistics()
    {
        $companyId = auth()->user()->company_id;

        $stats = Invoice::where('company_id', $companyId)
            ->select([
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft_invoices'),
                DB::raw('SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent_invoices'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid_invoices'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue_invoices'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN total_amount ELSE 0 END) as paid_amount'),
                DB::raw('SUM(CASE WHEN status = "overdue" THEN total_amount ELSE 0 END) as overdue_amount'),
                DB::raw('MONTH(invoice_date) as month'),
                DB::raw('YEAR(invoice_date) as year')
            ])
            ->groupBy(DB::raw('YEAR(invoice_date), MONTH(invoice_date)'))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // Add other existing methods from your InvoiceController here...
    // (getAllInvoices, updateInvoice, deleteInvoice, etc.)
}
