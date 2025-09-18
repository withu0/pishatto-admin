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

        // Search by LINE ID
        if ($request->has('search') && $request->search) {
            $query->where('line_id', 'like', '%' . $request->search . '%');
        }

        $perPage = (int) $request->input('per_page', 10);
        $applications = $query->with(['preliminaryReviewer', 'finalReviewer'])
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
        $application->load(['preliminaryReviewer', 'finalReviewer']);
        
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

    /**
     * Approve preliminary screening
     */
    public function approvePreliminary(Request $request, CastApplication $application)
    {
        $request->validate([
            'preliminary_notes' => 'nullable|string|max:1000',
        ]);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Application has already been processed');
        }

        $application->update([
            'status' => 'preliminary_passed',
            'preliminary_notes' => $request->preliminary_notes,
            'preliminary_reviewed_at' => now(),
            'preliminary_reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Preliminary screening approved successfully');
    }

    /**
     * Reject preliminary screening
     */
    public function rejectPreliminary(Request $request, CastApplication $application)
    {
        $request->validate([
            'preliminary_notes' => 'required|string|max:1000',
        ]);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Application has already been processed');
        }

        $application->update([
            'status' => 'preliminary_rejected',
            'preliminary_notes' => $request->preliminary_notes,
            'preliminary_reviewed_at' => now(),
            'preliminary_reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Preliminary screening rejected successfully');
    }

    /**
     * Approve final screening
     */
    public function approveFinal(Request $request, CastApplication $application)
    {
        $request->validate([
            'final_notes' => 'nullable|string|max:1000',
        ]);

        // Allow skipping final directly from pending by marking preliminary as passed and final as passed
        if ($application->status === 'pending') {
            $application->update([
                'status' => 'final_passed',
                // If preliminary was not reviewed, mark it as passed at the same time
                'preliminary_notes' => $application->preliminary_notes ?? $request->final_notes,
                'preliminary_reviewed_at' => now(),
                'preliminary_reviewed_by' => Auth::id(),
                'final_notes' => $request->final_notes,
                'final_reviewed_at' => now(),
                'final_reviewed_by' => Auth::id(),
            ]);

            return back()->with('success', 'Final screening approved successfully');
        }

        if ($application->status !== 'preliminary_passed') {
            return back()->with('error', 'Application must pass preliminary screening first');
        }

        $application->update([
            'status' => 'final_passed',
            'final_notes' => $request->final_notes,
            'final_reviewed_at' => now(),
            'final_reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Final screening approved successfully');
    }

    /**
     * Reject final screening
     */
    public function rejectFinal(Request $request, CastApplication $application)
    {
        $request->validate([
            'final_notes' => 'required|string|max:1000',
        ]);

        if ($application->status !== 'preliminary_passed') {
            return back()->with('error', 'Application must pass preliminary screening first');
        }

        $application->update([
            'status' => 'final_rejected',
            'final_notes' => $request->final_notes,
            'final_reviewed_at' => now(),
            'final_reviewed_by' => Auth::id(),
        ]);

        return back()->with('success', 'Final screening rejected successfully');
    }
}
