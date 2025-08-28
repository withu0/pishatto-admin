<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tweet;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class TweetsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);

        $paginator = Tweet::with(['guest', 'cast'])
            ->latest()
            ->paginate($perPage);

        $transformed = $paginator->getCollection()->map(function ($tweet) {
            $userType = '';
            $user = '';
            
            if ($tweet->guest_id && $tweet->guest) {
                $userType = 'ゲスト';
                $user = $tweet->guest->nickname ?? $tweet->guest->phone ?? 'Unknown';
            } elseif ($tweet->cast_id && $tweet->cast) {
                $userType = 'キャスト';
                $user = $tweet->cast->nickname ?? $tweet->cast->phone ?? 'Unknown';
            }

            return [
                'id' => $tweet->id,
                'userType' => $userType,
                'user' => $user,
                'content' => $tweet->content,
                'date' => $tweet->created_at ? (is_string($tweet->created_at) ? $tweet->created_at : $tweet->created_at->format('Y-m-d H:i')) : 'Unknown',
            ];
        });

        $paginator->setCollection($transformed);

        return Inertia::render('admin/tweets', [
            'tweets' => $paginator,
            'guests' => Guest::select('id', 'nickname', 'phone')->get(),
            'casts' => Cast::select('id', 'nickname', 'phone')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/tweets/create', [
            'guests' => Guest::select('id', 'nickname', 'phone')->get(),
            'casts' => Cast::select('id', 'nickname', 'phone')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'nullable|string|max:280',
            'guest_id' => 'nullable|exists:guests,id',
            'cast_id' => 'nullable|exists:casts,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);
        
        // Ensure at least one of content or image is provided
        if (empty($validated['content']) && !$request->hasFile('image')) {
            return redirect()->back()->withErrors([
                'content' => 'Either content or image must be provided.'
            ])->withInput();
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('tweet_images', $fileName, 'public');
            $validated['image'] = 'tweet_images/' . $fileName;
        }

        $tweet = Tweet::create($validated);
        $tweet->load(['guest', 'cast']);

        return redirect()->route('admin.tweets.index')->with('success', 'つぶやきが作成されました。');
    }

    public function show(Tweet $tweet)
    {
        $tweet->load(['guest', 'cast']);
        
        $userType = '';
        $user = '';
        
        if ($tweet->guest_id && $tweet->guest) {
            $userType = 'ゲスト';
            $user = $tweet->guest->nickname ?? $tweet->guest->phone ?? 'Unknown';
        } elseif ($tweet->cast_id && $tweet->cast) {
            $userType = 'キャスト';
            $user = $tweet->cast->nickname ?? $tweet->cast->phone ?? 'Unknown';
        }

        return Inertia::render('admin/tweets/show', [
            'tweet' => [
                'id' => $tweet->id,
                'userType' => $userType,
                'user' => $user,
                'content' => $tweet->content,
                'image' => $tweet->image,
                'date' => $tweet->created_at ? (is_string($tweet->created_at) ? $tweet->created_at : $tweet->created_at->format('Y-m-d H:i')) : 'Unknown',
            ],
            'guests' => Guest::select('id', 'nickname', 'phone')->get(),
            'casts' => Cast::select('id', 'nickname', 'phone')->get(),
        ]);
    }

    public function edit(Tweet $tweet)
    {
        $tweet->load(['guest', 'cast']);
        
        $userType = '';
        $user = '';
        
        if ($tweet->guest_id && $tweet->guest) {
            $userType = 'ゲスト';
            $user = $tweet->guest->nickname ?? $tweet->guest->phone ?? 'Unknown';
        } elseif ($tweet->cast_id && $tweet->cast) {
            $userType = 'キャスト';
            $user = $tweet->cast->nickname ?? $tweet->cast->phone ?? 'Unknown';
        }

        return Inertia::render('admin/tweets/edit', [
            'tweet' => [
                'id' => $tweet->id,
                'userType' => $userType,
                'user' => $user,
                'content' => $tweet->content,
                'image' => $tweet->image,
                'guest_id' => $tweet->guest_id,
                'cast_id' => $tweet->cast_id,
                'date' => $tweet->created_at ? (is_string($tweet->created_at) ? $tweet->created_at : $tweet->created_at->format('Y-m-d H:i')) : 'Unknown',
            ],
            'guests' => Guest::select('id', 'nickname', 'phone')->get(),
            'casts' => Cast::select('id', 'nickname', 'phone')->get(),
        ]);
    }

    public function update(Request $request, Tweet $tweet)
    {
        $validated = $request->validate([
            'content' => 'nullable|string|max:280',
            'guest_id' => 'nullable|exists:guests,id',
            'cast_id' => 'nullable|exists:casts,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);
        
        // Ensure at least one of content or image is provided
        if (empty($validated['content']) && !$request->hasFile('image') && !$tweet->image) {
            return redirect()->back()->withErrors([
                'content' => 'Either content or image must be provided.'
            ])->withInput();
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($tweet->image) {
                Storage::disk('public')->delete($tweet->image);
            }
            
            $file = $request->file('image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('tweet_images', $fileName, 'public');
            $validated['image'] = 'tweet_images/' . $fileName;
        }

        $tweet->update($validated);
        $tweet->load(['guest', 'cast']);

        return redirect()->route('admin.tweets.index')->with('success', 'つぶやきが更新されました。');
    }

    public function destroy(Tweet $tweet)
    {
        // Delete image if exists
        if ($tweet->image) {
            Storage::disk('public')->delete($tweet->image);
        }
        
        $tweet->delete();
        return redirect()->route('admin.tweets.index')->with('success', 'つぶやきが削除されました。');
    }

    public function getTweetsData()
    {
        $tweets = Tweet::latest()
            ->get()
            ->map(function ($tweet) {
                $user = null;
                if ($tweet->guest_id && $tweet->guest) {
                    $user = $tweet->guest->nickname ?? $tweet->guest->phone;
                } elseif ($tweet->cast_id && $tweet->cast) {
                    $user = $tweet->cast->nickname ?? $tweet->cast->phone;
                }

                return [
                    'id' => $tweet->id,
                    'user' => $user ?? 'Unknown',
                    'content' => substr($tweet->content, 0, 100) . (strlen($tweet->content) > 100 ? '...' : ''),
                    'likes' => $tweet->likes_count ?? 0,
                    'date' => $tweet->created_at ? (is_string($tweet->created_at) ? $tweet->created_at : $tweet->created_at->format('Y-m-d H:i')) : 'Unknown',
                ];
            });

        return response()->json(['tweets' => $tweets]);
    }
} 