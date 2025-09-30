<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PointTransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExceededPendingController extends Controller
{
    protected $pointTransactionService;

    public function __construct(PointTransactionService $pointTransactionService)
    {
        $this->pointTransactionService = $pointTransactionService;
    }

    /**
     * Get all point transactions except pending type
     */
    public function index(): JsonResponse
    {
        try {
            $transactions = $this->pointTransactionService->getAllPointTransactionsExceptPending();
            
            return response()->json([
                'success' => true,
                'data' => $transactions,
                'count' => $transactions->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch point transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get point transactions count (except pending type)
     */
    public function count(): JsonResponse
    {
        try {
            $count = $this->pointTransactionService->getAllPointTransactionsExceptPendingCount();
            
            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get point transactions count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually process all exceeded pending transactions (admin override)
     */
    public function processAll(): JsonResponse
    {
        try {
            $processedCount = $this->pointTransactionService->processAutoTransferExceededPending();
            
            return response()->json([
                'success' => true,
                'message' => "Processed {$processedCount} exceeded pending transactions",
                'processed_count' => $processedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process exceeded pending transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}