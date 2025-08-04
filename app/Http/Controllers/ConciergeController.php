<?php

namespace App\Http\Controllers;

use App\Models\ConciergeMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ConciergeController extends Controller
{
    /**
     * Get concierge messages for a user
     */
    public function getMessages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->input('user_id');
        $userType = $request->input('user_type');

        // Get messages for the user with real data
        $messages = ConciergeMessage::forUser($userId, $userType)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'text' => $message->message,
                    'is_concierge' => $message->is_concierge,
                    'message_type' => $message->message_type,
                    'category' => $message->category,
                    'status' => $message->status,
                    'admin_notes' => $message->admin_notes,
                    'timestamp' => $message->created_at->format('H:i'),
                    'created_at' => $message->created_at->toISOString(),
                    'metadata' => $message->metadata,
                ];
            });

        // Get unread count
        $unreadCount = ConciergeMessage::forUser($userId, $userType)
            ->concierge()
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messages,
                'unread_count' => $unreadCount,
            ]
        ]);
    }

    /**
     * Send a message to concierge
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->input('user_id');
        $userType = $request->input('user_type');
        $messageText = $request->input('message');

        // Determine message type and category based on content
        $messageType = $this->determineMessageType($messageText);
        $category = $this->determineCategory($messageText);

        // Create user message with metadata
        $userMessage = ConciergeMessage::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'message' => $messageText,
            'is_concierge' => false,
            'is_read' => true, // Mark as read since user sent it
            'message_type' => $messageType,
            'category' => $category,
            'status' => 'pending',
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
            'metadata' => [
                'source' => 'user_message',
                'keywords' => $this->extractKeywords($messageText),
                'sentiment' => $this->analyzeSentiment($messageText),
            ],
        ]);

        // Create automatic concierge response with metadata
        $conciergeResponse = $this->generateConciergeResponse($messageText);
        
        $conciergeMessage = ConciergeMessage::create([
            'user_id' => $userId,
            'user_type' => $userType,
            'message' => $conciergeResponse,
            'is_concierge' => true,
            'is_read' => false,
            'message_type' => $messageType,
            'category' => $category,
            'status' => 'in_progress',
            'user_agent' => $request->header('User-Agent'),
            'ip_address' => $request->ip(),
            'metadata' => [
                'source' => 'auto_response',
                'response_type' => 'automatic',
                'keywords' => $this->extractKeywords($conciergeResponse),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user_message' => [
                    'id' => $userMessage->id,
                    'text' => $userMessage->message,
                    'is_concierge' => false,
                    'message_type' => $userMessage->message_type,
                    'category' => $userMessage->category,
                    'status' => $userMessage->status,
                    'timestamp' => $userMessage->created_at->format('H:i'),
                    'created_at' => $userMessage->created_at->toISOString(),
                ],
                'concierge_message' => [
                    'id' => $conciergeMessage->id,
                    'text' => $conciergeMessage->message,
                    'is_concierge' => true,
                    'message_type' => $conciergeMessage->message_type,
                    'category' => $conciergeMessage->category,
                    'status' => $conciergeMessage->status,
                    'timestamp' => $conciergeMessage->created_at->format('H:i'),
                    'created_at' => $conciergeMessage->created_at->toISOString(),
                ],
            ]
        ]);
    }

    /**
     * Mark concierge messages as read
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'user_type' => 'required|in:guest,cast',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $userId = $request->input('user_id');
        $userType = $request->input('user_type');

        // Mark all unread concierge messages as read
        ConciergeMessage::forUser($userId, $userType)
            ->concierge()
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read'
        ]);
    }

    /**
     * Get concierge info (welcome message, etc.)
     */
    public function getInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'welcome_message' => [
                    'title' => 'pishatto',
                    'subtitle' => 'ようこそ',
                    'content' => [
                        'はじめまして。',
                        'pishattoコンシェルジュの',
                        'パッとくんと申します。',
                        '「お問い合わせ窓口」として',
                        '24時間体制でサポートさせていただきます。お困りの際は',
                        'お気軽にご連絡ください。',
                        '↓合流方法は2種類✨'
                    ]
                ],
                'concierge_info' => [
                    'name' => 'pishattoコンシェルジュ',
                    'age' => '11歳',
                    'avatar' => 'concierge-avatar.png'
                ]
            ]
        ]);
    }

    /**
     * Determine message type based on content
     */
    private function determineMessageType(string $message): string
    {
        $message = strtolower($message);
        
        if (strpos($message, '予約') !== false || strpos($message, 'reservation') !== false || strpos($message, 'booking') !== false) {
            return 'reservation';
        }
        
        if (strpos($message, '支払い') !== false || strpos($message, 'payment') !== false || strpos($message, '決済') !== false || strpos($message, '料金') !== false) {
            return 'payment';
        }
        
        if (strpos($message, 'トラブル') !== false || strpos($message, '問題') !== false || strpos($message, '困') !== false || strpos($message, 'エラー') !== false) {
            return 'support';
        }
        
        if (strpos($message, 'サービス') !== false || strpos($message, 'service') !== false || strpos($message, '機能') !== false) {
            return 'inquiry';
        }
        
        if (strpos($message, '技術') !== false || strpos($message, 'technical') !== false || strpos($message, 'バグ') !== false) {
            return 'technical';
        }
        
        return 'general';
    }

    /**
     * Determine category based on content
     */
    private function determineCategory(string $message): string
    {
        $message = strtolower($message);
        
        // Check for urgent keywords
        if (strpos($message, '緊急') !== false || strpos($message, 'urgent') !== false || 
            strpos($message, 'すぐ') !== false || strpos($message, '今すぐ') !== false ||
            strpos($message, 'トラブル') !== false || strpos($message, '問題') !== false) {
            return 'urgent';
        }
        
        // Check for low priority keywords
        if (strpos($message, '質問') !== false || strpos($message, 'お聞き') !== false || 
            strpos($message, '教えて') !== false) {
            return 'low';
        }
        
        return 'normal';
    }

    /**
     * Extract keywords from message
     */
    private function extractKeywords(string $message): array
    {
        $keywords = [];
        $message = strtolower($message);
        
        $keywordPatterns = [
            'reservation' => ['予約', 'reservation', 'booking'],
            'payment' => ['支払い', 'payment', '決済', '料金'],
            'support' => ['サポート', 'support', 'ヘルプ', 'help'],
            'technical' => ['技術', 'technical', 'バグ', 'bug'],
            'urgent' => ['緊急', 'urgent', 'すぐ', '今すぐ'],
        ];
        
        foreach ($keywordPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($message, $pattern) !== false) {
                    $keywords[] = $category;
                    break;
                }
            }
        }
        
        return array_unique($keywords);
    }

    /**
     * Analyze sentiment of message
     */
    private function analyzeSentiment(string $message): string
    {
        $message = strtolower($message);
        
        $positiveWords = ['ありがとう', 'thank', '良い', 'good', '素晴らしい', 'great'];
        $negativeWords = ['困', '問題', 'トラブル', '問題', 'bad', '悪い', '嫌'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (strpos($message, $word) !== false) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (strpos($message, $word) !== false) {
                $negativeCount++;
            }
        }
        
        if ($positiveCount > $negativeCount) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }

    /**
     * Generate automatic concierge response based on user message
     */
    private function generateConciergeResponse(string $userMessage): string
    {
        $message = strtolower($userMessage);
        
        // Simple keyword-based responses
        if (strpos($message, '予約') !== false || strpos($message, 'reservation') !== false) {
            return '予約についてのご質問ですね。予約の変更・キャンセルは24時間前まで可能です。詳細はお気軽にお聞かせください。';
        }
        
        if (strpos($message, '支払い') !== false || strpos($message, 'payment') !== false || strpos($message, '料金') !== false) {
            return '支払いについてのご質問ですね。クレジットカード、銀行振込、コンビニ決済に対応しています。';
        }
        
        if (strpos($message, 'トラブル') !== false || strpos($message, '問題') !== false || strpos($message, '困') !== false) {
            return 'トラブルが発生してしまい、申し訳ございません。詳しい状況をお聞かせください。すぐに対応いたします。';
        }
        
        if (strpos($message, 'サービス') !== false || strpos($message, 'service') !== false) {
            return 'サービスについてのご質問ですね。当店では様々なサービスをご提供しております。詳しくはお気軽にお聞かせください。';
        }
        
        // Default response
        return 'ありがとうございます。すぐに対応いたします。何か他にご質問がございましたら、お気軽にお聞かせください。';
    }
} 