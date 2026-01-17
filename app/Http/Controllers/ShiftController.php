<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $shifts = Shift::forCompany($user->company_id)
                ->where('created_by', $user->id) // Only user's own shifts
                ->with(['company:id,name', 'creator:id,first_name,last_name'])
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $shifts
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching shifts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching shifts'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'start_time_utc' => 'required|date_format:H:i:s',
                'end_time_utc' => 'required|date_format:H:i:s',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $shift = Shift::create([
                'title' => $validated['title'],
                'start_time_utc' => $validated['start_time_utc'],
                'end_time_utc' => $validated['end_time_utc'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'company_id' => $user->company_id, // Auto-set from auth user
                'created_by' => $user->id // Auto-set from auth user
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shift created successfully',
                'data' => $shift->load(['company:id,name', 'creator:id,first_name,last_name'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating shift'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = Auth::user();

            $shift = Shift::forCompany($user->company_id)
                ->with(['company:id,name', 'creator:id,first_name,last_name'])
                ->find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shift not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $shift
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $shift = Shift::forCompany($user->company_id)->find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shift not found or you do not have permission to update it'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'start_time_utc' => 'sometimes|date_format:H:i:s',
                'end_time_utc' => 'sometimes|date_format:H:i:s',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $shift->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Shift updated successfully',
                'data' => $shift->load(['company:id,name', 'creator:id,first_name,last_name'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating shift'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = Auth::user();

            $shift = Shift::forCompany($user->company_id)->find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shift not found or you do not have permission to delete it'
                ], 404);
            }

            // Check if shift is being used in any jobs
            $isUsed = $shift->jobs()->exists();

            if ($isUsed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete shift. It is assigned to one or more jobs.'
                ], 400);
            }

            // Permanently delete the shift (since no soft deletes)
            $shift->forceDelete(); // Changed from delete() to forceDelete() to be explicit

            return response()->json([
                'success' => true,
                'message' => 'Shift deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting shift: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting shift'
            ], 500);
        }
    }

    // Get only active shifts (alternative endpoint)
    public function active()
    {
        try {
            $user = Auth::user();

            $shifts = Shift::forCompany($user->company_id)
                ->active()
                ->with(['company:id,name', 'creator:id,first_name,last_name'])
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $shifts
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching active shifts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching shifts'
            ], 500);
        }
    }
}
