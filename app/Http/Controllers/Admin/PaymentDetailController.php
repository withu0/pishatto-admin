<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentDetail;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PaymentDetailController extends Controller
{
    /**
     * Display a listing of payment details
     */
    public function index(Request $request)
    {
        $query = PaymentDetail::with(['cast', 'payment', 'issuer'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search') && $request->search) {
            $query->whereHas('cast', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('nickname', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('cast_id') && $request->cast_id) {
            $query->where('cast_id', $request->cast_id);
        }

        // Get paginated results
        $perPage = (int) $request->input('per_page', 10);
        $paymentDetails = $query->paginate($perPage);

        // Transform data for frontend
        $transformedDetails = $paymentDetails->getCollection()->map(function($detail) {
            return [
                'id' => $detail->id,
                'cast_id' => $detail->cast_id,
                'cast_name' => $detail->cast ? $detail->cast->name : 'Unknown Cast',
                'payment_id' => $detail->payment_id,
                'amount' => $detail->amount,
                'description' => $detail->description,
                'status' => $detail->status,
                'notes' => $detail->notes,
                'issued_at' => $detail->issued_at?->toISOString(),
                'created_at' => $detail->created_at->toISOString(),
                'updated_at' => $detail->updated_at->toISOString(),
                'issuer_name' => $detail->issuer ? $detail->issuer->name : null,
            ];
        });

        // Calculate summary statistics
        $summary = [
            'total_amount' => PaymentDetail::sum('amount'),
            'pending_count' => PaymentDetail::where('status', 'pending')->count(),
            'issued_count' => PaymentDetail::where('status', 'issued')->count(),
            'completed_count' => PaymentDetail::where('status', 'completed')->count(),
            'cancelled_count' => PaymentDetail::where('status', 'cancelled')->count(),
            'unique_casts' => PaymentDetail::distinct('cast_id')->count(),
        ];

        $paymentDetailsData = [
            'payment_details' => $transformedDetails,
            'pagination' => [
                'current_page' => $paymentDetails->currentPage(),
                'last_page' => $paymentDetails->lastPage(),
                'per_page' => $paymentDetails->perPage(),
                'total' => $paymentDetails->total(),
                'from' => $paymentDetails->firstItem(),
                'to' => $paymentDetails->lastItem(),
            ],
            'summary' => $summary,
        ];

        return Inertia::render('admin/payment-details', [
            'paymentDetails' => $paymentDetailsData,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'cast_id' => $request->cast_id,
            ],
        ]);
    }

    /**
     * Show the form for creating a new payment detail
     */
    public function create()
    {
        $casts = Cast::select('id', 'name', 'nickname')->get();
        
        return Inertia::render('admin/payment-details/create', [
            'casts' => $casts,
        ]);
    }

    /**
     * Store a newly created payment detail
     */
    public function store(Request $request)
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,issued,completed,cancelled',
        ]);

        $paymentDetail = PaymentDetail::create([
            'cast_id' => $request->cast_id,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
            'status' => $request->status ?? 'pending',
            'issued_at' => $request->status === 'issued' ? now() : null,
            'issued_by' => Auth::id(),
        ]);

        return redirect()->route('admin.payment-details.index')
            ->with('success', 'Payment detail created successfully');
    }

    /**
     * Display the specified payment detail
     */
    public function show(PaymentDetail $paymentDetail)
    {
        $paymentDetail->load(['cast', 'payment', 'issuer']);
        
        return Inertia::render('admin/payment-details/show', [
            'paymentDetail' => [
                'id' => $paymentDetail->id,
                'cast_id' => $paymentDetail->cast_id,
                'cast_name' => $paymentDetail->cast ? $paymentDetail->cast->name : 'Unknown Cast',
                'payment_id' => $paymentDetail->payment_id,
                'amount' => $paymentDetail->amount,
                'description' => $paymentDetail->description,
                'status' => $paymentDetail->status,
                'notes' => $paymentDetail->notes,
                'issued_at' => $paymentDetail->issued_at?->toISOString(),
                'created_at' => $paymentDetail->created_at->toISOString(),
                'updated_at' => $paymentDetail->updated_at->toISOString(),
                'issuer_name' => $paymentDetail->issuer ? $paymentDetail->issuer->name : null,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified payment detail
     */
    public function edit(PaymentDetail $paymentDetail)
    {
        $casts = Cast::select('id', 'name', 'nickname')->get();
        
        return Inertia::render('admin/payment-details/edit', [
            'paymentDetail' => [
                'id' => $paymentDetail->id,
                'cast_id' => $paymentDetail->cast_id,
                'payment_id' => $paymentDetail->payment_id,
                'amount' => $paymentDetail->amount,
                'description' => $paymentDetail->description,
                'status' => $paymentDetail->status,
                'notes' => $paymentDetail->notes,
                'issued_at' => $paymentDetail->issued_at?->toISOString(),
            ],
            'casts' => $casts,
        ]);
    }

    /**
     * Update the specified payment detail
     */
    public function update(Request $request, PaymentDetail $paymentDetail)
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,issued,completed,cancelled',
        ]);

        $oldStatus = $paymentDetail->status;
        
        $paymentDetail->update([
            'cast_id' => $request->cast_id,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
            'status' => $request->status,
            'issued_at' => $request->status === 'issued' && $oldStatus !== 'issued' ? now() : $paymentDetail->issued_at,
        ]);

        return redirect()->route('admin.payment-details.index')
            ->with('success', 'Payment detail updated successfully');
    }

    /**
     * Remove the specified payment detail
     */
    public function destroy(PaymentDetail $paymentDetail)
    {
        $paymentDetail->delete();

        return redirect()->route('admin.payment-details.index')
            ->with('success', 'Payment detail deleted successfully');
    }

    /**
     * Get payment details for API
     */
    public function getPaymentDetails(Request $request)
    {
        $query = PaymentDetail::with(['cast', 'payment', 'issuer'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('search') && $request->search) {
            $query->whereHas('cast', function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('nickname', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('cast_id') && $request->cast_id) {
            $query->where('cast_id', $request->cast_id);
        }

        // Get paginated results
        $perPage = (int) $request->input('per_page', 10);
        $paymentDetails = $query->paginate($perPage);

        // Transform data for frontend
        $transformedDetails = $paymentDetails->getCollection()->map(function($detail) {
            return [
                'id' => $detail->id,
                'cast_id' => $detail->cast_id,
                'cast_name' => $detail->cast ? $detail->cast->name : 'Unknown Cast',
                'payment_id' => $detail->payment_id,
                'amount' => $detail->amount,
                'description' => $detail->description,
                'status' => $detail->status,
                'notes' => $detail->notes,
                'issued_at' => $detail->issued_at?->toISOString(),
                'created_at' => $detail->created_at->toISOString(),
                'updated_at' => $detail->updated_at->toISOString(),
                'issuer_name' => $detail->issuer ? $detail->issuer->name : null,
            ];
        });

        // Calculate summary statistics
        $summary = [
            'total_amount' => PaymentDetail::sum('amount'),
            'pending_count' => PaymentDetail::where('status', 'pending')->count(),
            'issued_count' => PaymentDetail::where('status', 'issued')->count(),
            'completed_count' => PaymentDetail::where('status', 'completed')->count(),
            'cancelled_count' => PaymentDetail::where('status', 'cancelled')->count(),
            'unique_casts' => PaymentDetail::distinct('cast_id')->count(),
        ];

        return response()->json([
            'payment_details' => $transformedDetails,
            'pagination' => [
                'current_page' => $paymentDetails->currentPage(),
                'last_page' => $paymentDetails->lastPage(),
                'per_page' => $paymentDetails->perPage(),
                'total' => $paymentDetails->total(),
                'from' => $paymentDetails->firstItem(),
                'to' => $paymentDetails->lastItem(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Create a new payment detail via API
     */
    public function createPaymentDetail(Request $request)
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,issued,completed,cancelled',
        ]);

        $paymentDetail = PaymentDetail::create([
            'cast_id' => $request->cast_id,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
            'status' => $request->status ?? 'pending',
            'issued_at' => $request->status === 'issued' ? now() : null,
            'issued_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'payment_detail' => $paymentDetail->load('cast'),
            'message' => 'Payment detail created successfully'
        ]);
    }

    /**
     * Update payment detail via API
     */
    public function updatePaymentDetail(Request $request, $paymentDetailId)
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,issued,completed,cancelled',
        ]);

        $paymentDetail = PaymentDetail::findOrFail($paymentDetailId);
        $oldStatus = $paymentDetail->status;
        
        $paymentDetail->update([
            'cast_id' => $request->cast_id,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
            'status' => $request->status,
            'issued_at' => $request->status === 'issued' && $oldStatus !== 'issued' ? now() : $paymentDetail->issued_at,
        ]);

        return response()->json([
            'success' => true,
            'payment_detail' => $paymentDetail->load('cast'),
            'message' => 'Payment detail updated successfully'
        ]);
    }
} 