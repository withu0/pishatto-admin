<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tweet;

class TweetController extends Controller
{
    // List all tweets
    public function index()
    {
        $tweets = Tweet::with(['guest', 'cast'])->orderBy('created_at', 'desc')->get();
        return response()->json(['tweets' => $tweets]);
    }

    // List tweets by user (guest or cast)
    public function userTweets($userType, $userId)
    {
        if ($userType === 'guest') {
            $tweets = Tweet::with('guest')->where('guest_id', $userId)->orderBy('created_at', 'desc')->get();
        } else {
            $tweets = Tweet::with('cast')->where('cast_id', $userId)->orderBy('created_at', 'desc')->get();
        }
        return response()->json(['tweets' => $tweets]);
    }

    // Create a tweet
    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:280',
            'guest_id' => 'nullable|exists:guests,id',
            'cast_id' => 'nullable|exists:casts,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);
        $validated['created_at'] = now();

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('tweet_images', $fileName, 'public');
            $validated['image'] = 'tweet_images/' . $fileName;
        }

        $tweet = Tweet::create($validated);
        $tweet->load(['guest', 'cast']); // Eager load relationships
        // Broadcast the tweet in real-time
        event(new \App\Events\TweetCreated($tweet));
        return response()->json(['tweet' => $tweet], 201);
    }

    // Delete a tweet
    public function destroy($id)
    {
        $tweet = Tweet::findOrFail($id);
        $tweet->delete();
        return response()->json(['success' => true]);
    }
} 