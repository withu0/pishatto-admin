<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CastApplicationController extends Controller
{
    /**
     * Upload cast registration images temporarily
     */
    public function uploadImages(Request $request)
    {
        $request->validate([
            'front_image' => 'required|file|image|max:2048',
            'profile_image' => 'required|file|image|max:2048',
            'full_body_image' => 'required|file|image|max:2048',
        ]);

        try {
            // Generate a unique session ID for this upload session
            $sessionId = Str::uuid()->toString();

            // Create temporary directory for this session
            $sessionDir = "cast-applications/{$sessionId}";

            // Upload images with original extensions
            $frontExtension = $request->file('front_image')->getClientOriginalExtension();
            $profileExtension = $request->file('profile_image')->getClientOriginalExtension();
            $fullBodyExtension = $request->file('full_body_image')->getClientOriginalExtension();

            $frontImagePath = $request->file('front_image')->storeAs($sessionDir, "front.{$frontExtension}", 'public');
            $profileImagePath = $request->file('profile_image')->storeAs($sessionDir, "profile.{$profileExtension}", 'public');
            $fullBodyImagePath = $request->file('full_body_image')->storeAs($sessionDir, "full_body.{$fullBodyExtension}", 'public');

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'images' => [
                    'front' => url(Storage::url($frontImagePath)),
                    'profile' => url(Storage::url($profileImagePath)),
                    'full_body' => url(Storage::url($fullBodyImagePath)),
                ],
                'message' => 'Images uploaded successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('CastApplicationController: Image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Image upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a single image
     */
    public function uploadSingleImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|max:2048',
            'type' => 'required|in:front,profile,fullBody,full_body',
            'session_id' => 'nullable|string'
        ]);

        try {
            // Generate session ID if not provided
            $sessionId = $request->session_id ?: Str::uuid()->toString();

            // Create temporary directory for this session
            $sessionDir = "cast-applications/{$sessionId}";

            // Upload image
            $imageType = $request->type;
            // Normalize the type name for file storage
            $normalizedType = $imageType === 'fullBody' ? 'full_body' : $imageType;

            // Get the original file extension
            $originalExtension = $request->file('image')->getClientOriginalExtension();
            $fileName = "{$normalizedType}.{$originalExtension}";

            Log::info('Uploading image with original extension', [
                'type' => $imageType,
                'normalized_type' => $normalizedType,
                'original_extension' => $originalExtension,
                'file_name' => $fileName
            ]);

            $imagePath = $request->file('image')->storeAs($sessionDir, $fileName, 'public');

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'image_url' => url(Storage::url($imagePath)),
                'type' => $imageType,
                'message' => 'Image uploaded successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('CastApplicationController: Single image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Image upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get uploaded images for a session
     */
    public function getImages(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->session_id;
            $sessionDir = "cast-applications/{$sessionId}";

            $images = [];

            // Get all files in the session directory
            $files = Storage::disk('public')->files($sessionDir);

            foreach ($files as $file) {
                $fileName = basename($file);

                // Check for front image (front.*)
                if (preg_match('/^front\./', $fileName)) {
                    $images['front'] = url(Storage::url($file));
                }
                // Check for profile image (profile.*)
                elseif (preg_match('/^profile\./', $fileName)) {
                    $images['profile'] = url(Storage::url($file));
                }
                // Check for full body image (full_body.*)
                elseif (preg_match('/^full_body\./', $fileName)) {
                    $images['full_body'] = url(Storage::url($file));
                }
            }

            return response()->json([
                'success' => true,
                'images' => $images,
                'message' => 'Images retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('CastApplicationController: Get images failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve images: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit cast application
     */
    public function submit(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|max:20',
            'line_id' => 'required|string|max:255',
            'line_name' => 'nullable|string|max:255',
            'upload_session_id' => 'nullable|string',
            // Image fields - either files or URLs
            'front_image' => 'nullable|file|image|max:2048',
            'profile_image' => 'nullable|file|image|max:2048',
            'full_body_image' => 'nullable|file|image|max:2048',
            'front_image_url' => 'nullable|string|url',
            'profile_image_url' => 'nullable|string|url',
            'full_body_image_url' => 'nullable|string|url',
        ]);

        try {
            // Check if we have images (either files or URLs)
            $hasImages = $request->hasFile('front_image') ||
                        $request->hasFile('profile_image') ||
                        $request->hasFile('full_body_image') ||
                        $request->filled('front_image_url') ||
                        $request->filled('profile_image_url') ||
                        $request->filled('full_body_image_url');

            if (!$hasImages) {
            return response()->json([
                    'success' => false,
                    'message' => 'At least one image is required'
            ], 400);
        }

            // Create cast application record first to get ID
            $castApplication = new \App\Models\CastApplication();
            $castApplication->line_id = $request->line_id;
            $castApplication->phone_number = $request->phone_number;
            $castApplication->status = 'pending';
            $castApplication->save(); // Save first to get the ID

            // Generate hash-based folder name for this application
            $folderHash = hash('sha256', $castApplication->id . $castApplication->line_id . $castApplication->created_at);
            $applicationFolder = "cast-applications/{$folderHash}";

            // Handle images - prioritize uploaded files over URLs
            if ($request->hasFile('front_image')) {
                $path = $request->file('front_image')->storeAs($applicationFolder, 'front.jpg', 'public');
                $castApplication->front_image = $path;
            } elseif ($request->filled('front_image_url')) {
                // Copy temporary image to permanent storage
                $tempPath = $this->copyTemporaryImageToPermanent($request->front_image_url, 'front', $applicationFolder);
                $castApplication->front_image = $tempPath;
            }

            if ($request->hasFile('profile_image')) {
                $path = $request->file('profile_image')->storeAs($applicationFolder, 'profile.jpg', 'public');
                $castApplication->profile_image = $path;
            } elseif ($request->filled('profile_image_url')) {
                // Copy temporary image to permanent storage
                $tempPath = $this->copyTemporaryImageToPermanent($request->profile_image_url, 'profile', $applicationFolder);
                $castApplication->profile_image = $tempPath;
            }

            if ($request->hasFile('full_body_image')) {
                $path = $request->file('full_body_image')->storeAs($applicationFolder, 'full_body.jpg', 'public');
                $castApplication->full_body_image = $path;
            } elseif ($request->filled('full_body_image_url')) {
                // Copy temporary image to permanent storage
                $tempPath = $this->copyTemporaryImageToPermanent($request->full_body_image_url, 'full_body', $applicationFolder);
                $castApplication->full_body_image = $tempPath;
            }

            $castApplication->save(); // Save again with image paths

            // Clean up temporary images if session ID provided
            if ($request->filled('upload_session_id')) {
                $cleanupRequest = new Request();
                $cleanupRequest->merge(['session_id' => $request->upload_session_id]);
                $this->cleanupImages($cleanupRequest);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cast application submitted successfully',
                'application_id' => $castApplication->id
            ]);

        } catch (\Exception $e) {
            Log::error('CastApplicationController: Submit failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Copy temporary image to permanent storage
     */
    private function copyTemporaryImageToPermanent($imageUrl, $type, $applicationFolder)
    {
        try {
            // Extract the file path from the URL
            $parsedUrl = parse_url($imageUrl);
            $path = $parsedUrl['path'] ?? '';
            
            // Remove '/storage/' prefix if present
            if (strpos($path, '/storage/') === 0) {
                $path = substr($path, 9); // Remove '/storage/' (9 characters)
            }
            
            // Check if the temporary file exists
            if (!Storage::disk('public')->exists($path)) {
                Log::warning('Temporary image not found', ['path' => $path, 'url' => $imageUrl]);
                return null;
            }
            
            // Generate new permanent path in organized folder
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $fileName = $type . '.' . $extension;
            $newPath = "{$applicationFolder}/{$fileName}";
            
            // Copy the file to permanent storage
            Storage::disk('public')->copy($path, $newPath);
            
            Log::info('Image copied to permanent storage', [
                'from' => $path,
                'to' => $newPath,
                'type' => $type,
                'application_folder' => $applicationFolder
            ]);
            
            return $newPath;
            
        } catch (\Exception $e) {
            Log::error('Failed to copy temporary image to permanent storage', [
                'error' => $e->getMessage(),
                'url' => $imageUrl,
                'type' => $type,
                'application_folder' => $applicationFolder,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Clean up temporary images
     */
    public function cleanupImages(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $sessionId = $request->session_id;
            $sessionDir = "cast-applications/{$sessionId}";

            // Delete the entire session directory
            Storage::disk('public')->deleteDirectory($sessionDir);

            return response()->json([
                'success' => true,
                'message' => 'Images cleaned up successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('CastApplicationController: Image cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Image cleanup failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
