<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Helpers\StorageHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IdentityVerificationController extends Controller
{
    /**
     * Display a listing of pending identity verifications.
     */
    public function index(Request $request): Response
    {
        $query = Guest::where('identity_verification_completed', 'pending')
                     ->whereNotNull('identity_verification');

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('line_id', 'like', "%{$search}%");
            });
        }

        $verifications = $query->orderBy('created_at', 'desc')->paginate(20);

        // Add avatar_url to each guest
        $verifications->getCollection()->transform(function ($guest) {
            $guest->avatar_url = $guest->first_avatar_url;
            $guest->avatar_urls = $guest->avatar_urls;
            
            // Generate identity verification URL with debugging
            if ($guest->identity_verification) {
                $guest->identity_verification_url = StorageHelper::publicUrl($guest->identity_verification);
                // Log for debugging
                \Log::info('Identity verification URL generated', [
                    'guest_id' => $guest->id,
                    'file_path' => $guest->identity_verification,
                    'url' => $guest->identity_verification_url,
                    'exists' => StorageHelper::publicExists($guest->identity_verification)
                ]);
            } else {
                $guest->identity_verification_url = null;
            }
            
            return $guest;
        });

        return Inertia::render('admin/identity-verifications', [
            'verifications' => $verifications,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Approve an identity verification.
     */
    public function approve(Request $request, $guestId)
    {
        $guest = Guest::findOrFail($guestId);
        
        if ($guest->identity_verification_completed !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'この認証は既に処理済みです'], 400);
            }
            return back()->with('error', 'この認証は既に処理済みです');
        }

        $guest->identity_verification_completed = 'success';
        $guest->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', '身分証明書が承認されました');
    }

    /**
     * Reject an identity verification.
     */
    public function reject(Request $request, $guestId)
    {
        $guest = Guest::findOrFail($guestId);
        
        if ($guest->identity_verification_completed !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'この認証は既に処理済みです'], 400);
            }
            return back()->with('error', 'この認証は既に処理済みです');
        }

        $guest->identity_verification_completed = 'failed';
        $guest->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', '身分証明書が却下されました');
    }

    /**
     * Get verification statistics.
     */
    public function stats()
    {
        $stats = [
            'pending' => Guest::where('identity_verification_completed', 'pending')
                             ->whereNotNull('identity_verification')->count(),
            'approved' => Guest::where('identity_verification_completed', 'success')->count(),
            'rejected' => Guest::where('identity_verification_completed', 'failed')->count(),
            'total' => Guest::whereNotNull('identity_verification')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Debug endpoint to check file storage.
     */
    public function debug(Request $request)
    {
        $guestId = $request->get('guest_id');
        $guest = Guest::find($guestId);
        
        if (!$guest) {
            return response()->json(['error' => 'Guest not found'], 404);
        }

        $debug = [
            'guest_id' => $guest->id,
            'identity_verification' => $guest->identity_verification,
            'file_exists' => $guest->identity_verification ? StorageHelper::publicExists($guest->identity_verification) : false,
            'url' => $guest->identity_verification ? StorageHelper::publicUrl($guest->identity_verification) : null,
            'storage_path' => StorageHelper::publicPath($guest->identity_verification),
            'public_path' => public_path('storage/' . $guest->identity_verification),
        ];

        return response()->json($debug);
    }

    /**
     * Test file storage and access.
     */
    public function testStorage()
    {
        // Create a test file
        $testContent = 'This is a test file for identity verification storage testing.';
        $testPath = 'identity_verification/test.txt';
        
        Storage::disk('public')->put($testPath, $testContent);
        
        $exists = StorageHelper::publicExists($testPath);
        $url = StorageHelper::publicUrl($testPath);
        $content = Storage::disk('public')->get($testPath);
        
        // Test the specific file that's failing
        $failingFile = 'identity_verification/CxhAZY0ZZFFw9qZej5a8ZAvYdlq0SRu49xA5Jccz.jpg';
        $failingFileExists = StorageHelper::publicExists($failingFile);
        $failingFileUrl = StorageHelper::publicUrl($failingFile);
        
        return response()->json([
            'test_file_created' => $exists,
            'test_file_url' => $url,
            'test_file_content' => $content,
            'failing_file_exists' => $failingFileExists,
            'failing_file_url' => $failingFileUrl,
            'storage_disk' => config('filesystems.default'),
            'public_disk_config' => config('filesystems.disks.public'),
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
        ]);
    }
} 