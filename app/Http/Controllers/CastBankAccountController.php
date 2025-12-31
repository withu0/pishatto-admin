<?php

namespace App\Http\Controllers;

use App\Http\Requests\CastBankAccountRequest;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CastBankAccountController extends Controller
{
    /**
     * Get bank account information for a cast
     */
    public function show(int $castId)
    {
        $cast = Cast::findOrFail($castId);

        // Check if cast has bank account information
        if (!$cast->bank_name && !$cast->branch_name && !$cast->account_number) {
            return response()->json([
                'success' => true,
                'bank_account' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'bank_account' => [
                'bank_name' => $cast->bank_name,
                'branch_name' => $cast->branch_name,
                'account_type' => $cast->account_type,
                'account_number' => $cast->account_number,
                'account_holder_name' => $cast->account_holder_name,
            ],
        ]);
    }

    /**
     * Create or update bank account information for a cast
     */
    public function store(CastBankAccountRequest $request, int $castId)
    {
        try {
            $cast = Cast::findOrFail($castId);

            $cast->bank_name = $request->input('bank_name');
            $cast->branch_name = $request->input('branch_name');
            $cast->account_type = $request->input('account_type');
            $cast->account_number = $request->input('account_number');
            $cast->account_holder_name = $request->input('account_holder_name');
            $cast->save();

            Log::info('Bank account information saved for cast', [
                'cast_id' => $castId,
                'bank_name' => $cast->bank_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => '銀行口座情報が保存されました。',
                'bank_account' => [
                    'bank_name' => $cast->bank_name,
                    'branch_name' => $cast->branch_name,
                    'account_type' => $cast->account_type,
                    'account_number' => $cast->account_number,
                    'account_holder_name' => $cast->account_holder_name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save bank account information', [
                'cast_id' => $castId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '銀行口座情報の保存に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete bank account information for a cast
     */
    public function destroy(int $castId)
    {
        try {
            $cast = Cast::findOrFail($castId);

            $cast->bank_name = null;
            $cast->branch_name = null;
            $cast->account_type = null;
            $cast->account_number = null;
            $cast->account_holder_name = null;
            $cast->save();

            Log::info('Bank account information deleted for cast', [
                'cast_id' => $castId,
            ]);

            return response()->json([
                'success' => true,
                'message' => '銀行口座情報が削除されました。',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete bank account information', [
                'cast_id' => $castId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '銀行口座情報の削除に失敗しました。',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
