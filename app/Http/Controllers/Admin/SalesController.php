<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['guest'])
            ->where('user_type', 'guest')
            ->latest();

        // Search by guest name
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('guest', function ($q) use ($search) {
                $q->where('nickname', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sales = $query->paginate(20)->through(function ($payment) {
            $guest = null;
            if ($payment->guest) {
                $guest = $payment->guest->nickname ?? $payment->guest->phone ?? "ゲスト{$payment->user_id}";
            }

            return [
                'id' => $payment->id,
                'guest' => $guest ?? 'Unknown',
                'amount' => $payment->amount,
                'date' => $payment->paid_at ? $payment->paid_at->format('Y-m-d') : ($payment->created_at ? $payment->created_at->format('Y-m-d') : 'Unknown'),
                'payment_method' => $payment->payment_method ?? 'クレジットカード',
                'notes' => $payment->description ?? null,
                'created_at' => $payment->created_at ? $payment->created_at->toISOString() : null,
                'updated_at' => $payment->updated_at ? $payment->updated_at->toISOString() : null,
            ];
        });

        return Inertia::render('admin/sales', [
            'sales' => $sales,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|exists:guests,id',
            'amount' => 'required|integer|min:1',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);

        $payment = Payment::create([
            'user_id' => $request->guest_id,
            'user_type' => 'guest',
            'amount' => $request->amount,
            'status' => 'paid',
            'payment_method' => $request->payment_method ?? 'クレジットカード',
            'description' => $request->notes,
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('success', '売上を登録しました。');
    }

    public function update(Request $request, Payment $payment)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:255',
        ]);

        $payment->update([
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'description' => $request->notes,
        ]);

        return redirect()->back()->with('success', '売上を更新しました。');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return redirect()->back()->with('success', '売上を削除しました。');
    }

    public function getGuests()
    {
        $guests = Guest::select('id', 'nickname', 'phone')
            ->whereNotNull('nickname')
            ->orWhereNotNull('phone')
            ->get()
            ->map(function ($guest) {
                return [
                    'id' => $guest->id,
                    'name' => $guest->nickname ?? $guest->phone ?? "ゲスト{$guest->id}",
                ];
            });

        return response()->json($guests);
    }
} 