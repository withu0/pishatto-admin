<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNews;
use App\Models\Notification;
use App\Models\Guest;
use App\Models\Cast;
use App\Events\NotificationSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminNewsController extends Controller
{
    public function index()
    {
        $news = AdminNews::with('creator')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'target_type' => $item->target_type,
                    'status' => $item->status,
                    'published_at' => $item->published_at?->format('Y-m-d'),
                    'created_at' => $item->created_at->format('Y-m-d'),
                    'creator' => $item->creator?->name ?? 'Unknown',
                ];
            });

        // Check if the request is for notifications page
        if (request()->is('admin/notifications*')) {
            return Inertia::render('admin/notifications', [
                'news' => $news
            ]);
        }

        return Inertia::render('admin/news', [
            'news' => $news
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/news/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_type' => 'required|in:all,guest,cast',
            'status' => 'required|in:draft,published,archived',
        ]);

        $news = AdminNews::create([
            ...$validated,
            'created_by' => Auth::id(),
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        // If the news is published, send notifications to users
        if ($validated['status'] === 'published') {
            $this->sendNotificationsToUsers($news);
        }

        return redirect()->route('admin.notifications')->with('success', 'お知らせが作成されました。');
    }

    public function edit(AdminNews $news)
    {
        return Inertia::render('admin/news/edit', [
            'news' => $news
        ]);
    }

    public function update(Request $request, AdminNews $news)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'target_type' => 'required|in:all,guest,cast',
            'status' => 'required|in:draft,published,archived',
        ]);

        $wasPublished = $news->status === 'published';
        $willBePublished = $validated['status'] === 'published';

        $news->update([
            ...$validated,
            'published_at' => $willBePublished ? now() : null,
        ]);

        // If the news is being published for the first time, send notifications
        if (!$wasPublished && $willBePublished) {
            $this->sendNotificationsToUsers($news);
        }

        return redirect()->route('admin.notifications')->with('success', 'お知らせが更新されました。');
    }

    public function destroy(AdminNews $news)
    {
        $news->delete();
        return redirect()->route('admin.notifications')->with('success', 'お知らせが削除されました。');
    }

    public function publish(AdminNews $news)
    {
        $news->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Send notifications to users
        $this->sendNotificationsToUsers($news);

        return redirect()->route('admin.notifications')->with('success', 'お知らせが公開されました。');
    }

    private function sendNotificationsToUsers(AdminNews $news)
    {
        $users = collect();

        // Get users based on target type
        if ($news->target_type === 'all' || $news->target_type === 'guest') {
            $guests = Guest::all();
            foreach ($guests as $guest) {
                $users->push([
                    'id' => $guest->id,
                    'type' => 'guest'
                ]);
            }
        }

        if ($news->target_type === 'all' || $news->target_type === 'cast') {
            $casts = Cast::all();
            foreach ($casts as $cast) {
                $users->push([
                    'id' => $cast->id,
                    'type' => 'cast'
                ]);
            }
        }

        // Create notifications for each user
        foreach ($users as $user) {
            $notification = Notification::create([
                'user_id' => $user['id'],
                'user_type' => $user['type'],
                'type' => 'admin_news',
                'message' => $news->title,
                'read' => false,
            ]);

            // Broadcast the notification
            broadcast(new NotificationSent($notification))->toOthers();
        }
    }
} 