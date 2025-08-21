<?php

namespace App\Http\Controllers;

use App\Models\CastApplication;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CastApplicationController extends Controller
{
    /**
     * Submit a new cast application
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'line_url' => 'required|string|max:500',
            'front_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'full_body_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Upload images
            $frontImagePath = $this->uploadImage($request->file('front_image'), 'cast-applications');
            $profileImagePath = $this->uploadImage($request->file('profile_image'), 'cast-applications');
            $fullBodyImagePath = $this->uploadImage($request->file('full_body_image'), 'cast-applications');

            // Create application
            $application = CastApplication::create([
                'line_url' => $request->line_url,
                'front_image' => $frontImagePath,
                'profile_image' => $profileImagePath,
                'full_body_image' => $fullBodyImagePath,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Application submitted successfully',
                'application' => $application
            ], 201);

        } catch (\Exception $e) {
            // Clean up uploaded files if application creation fails
            if (isset($frontImagePath)) Storage::delete($frontImagePath);
            if (isset($profileImagePath)) Storage::delete($profileImagePath);
            if (isset($fullBodyImagePath)) Storage::delete($fullBodyImagePath);

            return response()->json([
                'message' => 'Failed to submit application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all applications (for admin)
     */
    public function index(Request $request)
    {
        $query = CastApplication::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by LINE URL
        if ($request->has('search')) {
            $query->where('line_url', 'like', '%' . $request->search . '%');
        }

        $applications = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'applications' => $applications
        ]);
    }

    /**
     * Get a specific application
     */
    public function show($id)
    {
        $application = CastApplication::findOrFail($id);

        return response()->json([
            'application' => $application
        ]);
    }

    /**
     * Approve an application
     */
    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $application = CastApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application has already been processed'
            ], 400);
        }

        $application->update([
            'status' => 'approved',
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id() ?? 1, // Default to admin ID 1 if no auth
        ]);

        return response()->json([
            'message' => 'Application approved successfully',
            'application' => $application
        ]);
    }

    /**
     * Reject an application
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $application = CastApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application has already been processed'
            ], 400);
        }

        $application->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id() ?? 1, // Default to admin ID 1 if no auth
        ]);

        return response()->json([
            'message' => 'Application rejected successfully',
            'application' => $application
        ]);
    }

    /**
     * Upload image to storage
     */
    private function uploadImage($file, $directory)
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');
        return $path;
    }
}
