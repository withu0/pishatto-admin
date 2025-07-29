<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Guest;

class IdentityVerificationController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|max:5120', // max 5MB
        ]);

        // Find the guest record for this user
        $guest = Guest::where('id', $request->user_id)->firstOrFail();
        $path = $request->file('file')->store('identity_verification', 'public');
        $guest->identity_verification = $path;
        $guest->identity_verification_completed = 'pending';
        $guest->save();

        return response()->json(['success' => true, 'path' => $path]);
    }

    public function approve(Request $request, $guestId)
    {
        $guest = Guest::findOrFail($guestId);
        $guest->identity_verification_completed = 'success';
        $guest->save();
        return response()->json(['success' => true]);
    }

    public function reject(Request $request, $guestId)
    {
        $guest = Guest::findOrFail($guestId);
        $guest->identity_verification_completed = 'failed';
        $guest->save();
        return response()->json(['success' => true]);
    }
} 