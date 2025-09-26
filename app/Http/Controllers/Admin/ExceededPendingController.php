<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PointTransactionService;
use Illuminate\Http\Request;

class ExceededPendingController extends Controller
{
    protected $pointService;

    public function __construct(PointTransactionService $pointService)
    {
        $this->pointService = $pointService;
    }

    /**
     * Get all exceeded pending transactions for admin view
     */
    public function index()
    {
        $transactions = $this->pointService->getExceededPendingTransactions();
        
        return response()->json([
            'transactions' => $transactions,
            'count' => $transactions->count()
        ]);
    }

    /**
     * Get exceeded pending count for dashboard
     */
    public function count()
    {
        $count = $this->pointService->getExceededPendingCount();
        
        return response()->json([
            'count' => $count
        ]);
    }

    /**
     * Manually process exceeded pending transactions (admin override)
     */
    public function processAll()
    {
        $processedCount = $this->pointService->processAutoTransferExceededPending();
        
        return response()->json([
            'message' => "Processed {$processedCount} exceeded pending transactions",
            'processed_count' => $processedCount
        ]);
    }
}

