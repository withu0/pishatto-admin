<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CastApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CastApplicationController extends Controller
{
    /**
     * Display a listing of cast applications.
     */
    public function index(Request $request): Response
    {
        $query = CastApplication::query();

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search by LINE URL
        if ($request->has('search') && $request->search) {
            $query->where('line_url', 'like', '%' . $request->search . '%');
        }

        $perPage = (int) $request->input('per_page', 10);
        $applications = $query->with('reviewer')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Add image URLs to each application
        $applications->getCollection()->transform(function ($application) {
            $application->front_image_url = $application->front_image_url;
            $application->profile_image_url = $application->profile_image_url;
            $application->full_body_image_url = $application->full_body_image_url;
            return $application;
        });

        return Inertia::render('admin/cast-applications/index', [
            'applications' => $applications,
            'filters' => $request->only(['search', 'status'])
        ]);
    }

    /**
     * Display the specified cast application.
     */
    public function show(CastApplication $application): Response
    {
        $application->load('reviewer');
        
        // Add image URLs
        $application->front_image_url = $application->front_image_url;
        $application->profile_image_url = $application->profile_image_url;
        $application->full_body_image_url = $application->full_body_image_url;

        return Inertia::render('admin/cast-applications/show', [
            'application' => $application
        ]);
    }

    /**
     * Approve a cast application.
     */
    public function approve(Request $request, CastApplication $application)
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Application has already been processed');
        }

        $application->update([
            'status' => 'approved',
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Application approved successfully');
    }

    /**
     * Reject a cast application.
     */
    public function reject(Request $request, CastApplication $application)
    {
        $request->validate([
            'admin_notes' => 'required|string|max:1000',
        ]);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Application has already been processed');
        }

        $application->update([
            'status' => 'rejected',
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Application rejected successfully');
    }
}
