<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        $generateFileUrl = function (?string $filePath) {
            if (!$filePath) return null;
            $encodedPath = implode('/', array_map('rawurlencode', explode('/', $filePath)));
            return url('api/v.1/files/' . $encodedPath);
        };

        return response()->json([
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->is_active,
                'profile_image' => $generateFileUrl($user->profile_image),
            ]
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

      Log::info('Request all', $request->all());

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {

            // Delete old profile image if exists
            if ($user->profile_image && Storage::disk('private')->exists($user->profile_image)) {
                Storage::disk('private')->delete($user->profile_image);
            }

            // Generate filename
            $filename = 'profile_' . uniqid() . '.' . $request->file('profile_image')->extension();

            // Store
            $user->profile_image = $request->file('profile_image')->storeAs(
                'profiles',
                $filename,
                'private'
            );
        }

        if (isset($validated['first_name'])) {
            $user->first_name = $validated['first_name'];
        }
        if (isset($validated['last_name'])) {
            $user->last_name = $validated['last_name'];
        }

        $user->save();


        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->is_active,
                'profile_image' => generateFileUrl($user->profile_image),
            ]
        ]);
    }
}
