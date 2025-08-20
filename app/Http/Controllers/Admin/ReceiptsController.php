<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\Guest;
use App\Models\Cast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ReceiptsController extends Controller
{
    /**
     * Display the receipts management page
     */
    public function index()
    {
        $receipts = Receipt::with(['guestUser', 'castUser'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($receipt) {
                $userName = 'Unknown';
                if ($receipt->user_type === 'guest' && $receipt->guestUser) {
                    $userName = $receipt->guestUser->nickname ?? $receipt->guestUser->phone ?? 'Unknown Guest';
                } elseif ($receipt->user_type === 'cast' && $receipt->castUser) {
                    $userName = $receipt->castUser->nickname ?? $receipt->castUser->phone ?? 'Unknown Cast';
                }

                return [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'user_name' => $userName,
                    'user_type' => $receipt->user_type,
                    'recipient_name' => $receipt->recipient_name,
                    'amount' => $receipt->amount,
                    'total_amount' => $receipt->total_amount,
                    'purpose' => $receipt->purpose,
                    'status' => $receipt->status,
                    'issued_at' => $receipt->issued_at ? $receipt->issued_at->format('Y-m-d H:i') : null,
                    'created_at' => $receipt->created_at ? $receipt->created_at->format('Y-m-d H:i') : null,
                ];
            });

        return Inertia::render('admin/receipts', [
            'receipts' => $receipts
        ]);
    }

    /**
     * Get receipts data for API
     */
    public function getReceiptsData()
    {
        $receipts = Receipt::with(['guestUser', 'castUser'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($receipt) {
                $userName = 'Unknown';
                if ($receipt->user_type === 'guest' && $receipt->guestUser) {
                    $userName = $receipt->guestUser->nickname ?? $receipt->guestUser->phone ?? 'Unknown Guest';
                } elseif ($receipt->user_type === 'cast' && $receipt->castUser) {
                    $userName = $receipt->castUser->nickname ?? $receipt->castUser->phone ?? 'Unknown Cast';
                }

                return [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'user_name' => $userName,
                    'user_type' => $receipt->user_type,
                    'recipient_name' => $receipt->recipient_name,
                    'amount' => $receipt->amount,
                    'total_amount' => $receipt->total_amount,
                    'purpose' => $receipt->purpose,
                    'status' => $receipt->status,
                    'issued_at' => $receipt->issued_at ? $receipt->issued_at->format('Y-m-d H:i') : null,
                    'created_at' => $receipt->created_at ? $receipt->created_at->format('Y-m-d H:i') : null,
                ];
            });

        return response()->json(['receipts' => $receipts]);
    }

    /**
     * Show a specific receipt
     */
    public function show($id)
    {
        try {
            $receipt = Receipt::with(['guestUser', 'castUser', 'payment'])->findOrFail($id);
            
            $userName = 'Unknown';
            if ($receipt->user_type === 'guest' && $receipt->guestUser) {
                $userName = $receipt->guestUser->nickname ?? $receipt->guestUser->phone ?? 'Unknown Guest';
            } elseif ($receipt->user_type === 'cast' && $receipt->castUser) {
                $userName = $receipt->castUser->nickname ?? $receipt->castUser->phone ?? 'Unknown Cast';
            }

            $receiptData = [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'user_name' => $userName,
                'user_type' => $receipt->user_type,
                'user_id' => $receipt->user_id,
                'recipient_name' => $receipt->recipient_name,
                'amount' => $receipt->amount,
                'tax_amount' => $receipt->tax_amount,
                'tax_rate' => $receipt->tax_rate,
                'total_amount' => $receipt->total_amount,
                'purpose' => $receipt->purpose,
                'status' => $receipt->status,
                'issued_at' => $receipt->issued_at ? $receipt->issued_at->format('Y-m-d H:i') : null,
                'created_at' => $receipt->created_at ? $receipt->created_at->format('Y-m-d H:i') : null,
                'company_name' => $receipt->company_name,
                'company_address' => $receipt->company_address,
                'company_phone' => $receipt->company_phone,
                'registration_number' => $receipt->registration_number,
                'pdf_url' => $receipt->pdf_url,
                'html_content' => $receipt->html_content,
            ];

            return response()->json([
                'success' => true,
                'receipt' => $receiptData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => '領収書が見つかりません'
            ], 404);
        }
    }

    /**
     * Store a new receipt
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_type' => 'required|in:guest,cast',
            'user_id' => 'required|integer',
            'payment_id' => 'nullable|integer|exists:payments,id',
            'recipient_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'purpose' => 'required|string|max:255',
            'transaction_created_at' => 'nullable|date',
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string',
            'company_phone' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
        ]);

        try {
            // Generate unique receipt number
            $receiptNumber = 'R' . date('Ymd') . str_pad(Receipt::whereDate('created_at', today())->count() + 1, 6, '0', STR_PAD_LEFT);
            
            $taxRate = 10.00; // 10% tax rate
            $taxAmount = $request->amount * ($taxRate / 100);
            $totalAmount = $request->amount + $taxAmount;

            $receipt = Receipt::create([
                'receipt_number' => $receiptNumber,
                'user_type' => $request->user_type,
                'user_id' => $request->user_id,
                'payment_id' => $request->payment_id,
                'recipient_name' => $request->recipient_name,
                'amount' => $request->amount,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'total_amount' => $totalAmount,
                'purpose' => $request->purpose,
                'transaction_created_at' => $request->transaction_created_at,
                'issued_at' => now(),
                'company_name' => $request->company_name ?? '株式会社キネカ',
                'company_address' => $request->company_address ?? '〒106-0032 東京都港区六本木4丁目8-7六本木三河台ビル',
                'company_phone' => $request->company_phone ?? 'TEL: 03-5860-6178',
                'registration_number' => $request->registration_number ?? '登録番号:T3010401129426',
                'html_content' => $this->generateReceiptHtml($receiptNumber, $request->recipient_name, $request->amount, $taxAmount, $totalAmount, $request->purpose),
            ]);

            return response()->json([
                'success' => true,
                'receipt' => $receipt,
                'message' => '領収書が正常に作成されました'
            ]);

        } catch (\Exception $e) {
            Log::error('Receipt creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '領収書の作成に失敗しました'
            ], 500);
        }
    }

    /**
     * Update a receipt
     */
    public function update(Request $request, $id)
    {
        try {
            $receipt = Receipt::findOrFail($id);

            $request->validate([
                'recipient_name' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'purpose' => 'required|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'company_address' => 'nullable|string',
                'company_phone' => 'nullable|string|max:255',
                'registration_number' => 'nullable|string|max:255',
                'status' => 'nullable|in:draft,issued,cancelled',
            ]);

            $taxRate = $receipt->tax_rate;
            $taxAmount = $request->amount * ($taxRate / 100);
            $totalAmount = $request->amount + $taxAmount;

            $receipt->update([
                'recipient_name' => $request->recipient_name,
                'amount' => $request->amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'purpose' => $request->purpose,
                'company_name' => $request->company_name ?? $receipt->company_name,
                'company_address' => $request->company_address ?? $receipt->company_address,
                'company_phone' => $request->company_phone ?? $receipt->company_phone,
                'registration_number' => $request->registration_number ?? $receipt->registration_number,
                'status' => $request->status ?? $receipt->status,
                'html_content' => $this->generateReceiptHtml($receipt->receipt_number, $request->recipient_name, $request->amount, $taxAmount, $totalAmount, $request->purpose),
            ]);

            return response()->json([
                'success' => true,
                'receipt' => $receipt,
                'message' => '領収書が正常に更新されました'
            ]);

        } catch (\Exception $e) {
            Log::error('Receipt update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '領収書の更新に失敗しました'
            ], 500);
        }
    }

    /**
     * Delete a receipt
     */
    public function destroy($id)
    {
        try {
            $receipt = Receipt::findOrFail($id);
            $receipt->delete();

            return response()->json([
                'success' => true,
                'message' => '領収書が正常に削除されました'
            ]);

        } catch (\Exception $e) {
            Log::error('Receipt deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => '領収書の削除に失敗しました'
            ], 500);
        }
    }

    /**
     * Generate receipt HTML content
     */
    private function generateReceiptHtml($receiptNumber, $recipientName, $amount, $taxAmount, $totalAmount, $purpose)
    {
        $issuedDate = now()->format('Y年m月d日');
        $formattedAmount = number_format($amount);
        $formattedTaxAmount = number_format($taxAmount);
        $formattedTotalAmount = number_format($totalAmount);

        return "
        <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='margin: 0; color: #333;'>領収書</h1>
                <p style='margin: 5px 0; color: #666;'>Receipt</p>
            </div>
            
            <div style='margin-bottom: 20px;'>
                <p><strong>領収書番号:</strong> {$receiptNumber}</p>
                <p><strong>発行日:</strong> {$issuedDate}</p>
            </div>
            
            <div style='margin-bottom: 30px;'>
                <p><strong>宛名:</strong> {$recipientName}</p>
                <p><strong>目的:</strong> {$purpose}</p>
            </div>
            
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
                <tr style='border-bottom: 1px solid #ddd;'>
                    <td style='padding: 10px;'><strong>項目</strong></td>
                    <td style='padding: 10px; text-align: right;'><strong>金額</strong></td>
                </tr>
                <tr style='border-bottom: 1px solid #ddd;'>
                    <td style='padding: 10px;'>税抜金額</td>
                    <td style='padding: 10px; text-align: right;'>¥{$formattedAmount}</td>
                </tr>
                <tr style='border-bottom: 1px solid #ddd;'>
                    <td style='padding: 10px;'>消費税 (10%)</td>
                    <td style='padding: 10px; text-align: right;'>¥{$formattedTaxAmount}</td>
                </tr>
                <tr style='border-bottom: 2px solid #333;'>
                    <td style='padding: 10px;'><strong>合計</strong></td>
                    <td style='padding: 10px; text-align: right;'><strong>¥{$formattedTotalAmount}</strong></td>
                </tr>
            </table>
            
            <div style='margin-top: 50px; text-align: center;'>
                <p style='margin: 5px 0;'><strong>株式会社キネカ</strong></p>
                <p style='margin: 5px 0; color: #666;'>〒106-0032 東京都港区六本木4丁目8-7六本木三河台ビル</p>
                <p style='margin: 5px 0; color: #666;'>TEL: 03-5860-6178</p>
                <p style='margin: 5px 0; color: #666;'>登録番号:T3010401129426</p>
            </div>
        </div>";
    }
} 