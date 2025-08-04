import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Edit, ArrowLeft, User, MessageCircle, Clock, CheckCircle } from 'lucide-react';
import { useState } from 'react';

interface ConciergeMessage {
    id: number;
    user_id: number;
    user_type: 'guest' | 'cast';
    message: string;
    message_type: string;
    category: string;
    status: string;
    admin_notes?: string;
    assigned_admin_id?: number;
    resolved_at?: string;
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        nickname: string;
        phone: string;
    };
    assigned_admin?: {
        id: number;
        name: string;
    };
}

interface Props {
    message: ConciergeMessage;
}

export default function AdminConciergeShow({ message }: Props) {
    const [status, setStatus] = useState(message.status);
    const [adminNotes, setAdminNotes] = useState(message.admin_notes || '');
    const [isUpdating, setIsUpdating] = useState(false);

    const messageTypes = [
        { value: 'inquiry', label: 'お問い合わせ' },
        { value: 'support', label: 'サポート' },
        { value: 'reservation', label: '予約関連' },
        { value: 'payment', label: '支払い関連' },
        { value: 'technical', label: '技術的' },
        { value: 'general', label: '一般' },
    ];

    const categories = [
        { value: 'urgent', label: '緊急' },
        { value: 'normal', label: '通常' },
        { value: 'low', label: '低優先度' },
    ];

    const statuses = [
        { value: 'pending', label: '未対応' },
        { value: 'in_progress', label: '対応中' },
        { value: 'resolved', label: '解決済み' },
        { value: 'closed', label: 'クローズ' },
    ];

    const getStatusBadgeColor = (status: string) => {
        switch (status) {
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            case 'in_progress':
                return 'bg-blue-100 text-blue-800';
            case 'resolved':
                return 'bg-green-100 text-green-800';
            case 'closed':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getCategoryBadgeColor = (category: string) => {
        switch (category) {
            case 'urgent':
                return 'bg-red-100 text-red-800';
            case 'normal':
                return 'bg-blue-100 text-blue-800';
            case 'low':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const handleUpdateStatus = () => {
        setIsUpdating(true);
        router.put(`/admin/concierge/${message.id}/status`, {
            status,
            admin_notes: adminNotes,
        }, {
            onSuccess: () => {
                setIsUpdating(false);
            },
            onError: () => {
                setIsUpdating(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'コンシェルジュ管理', href: '/admin/concierge' },
            { title: `メッセージ #${message.id}`, href: `/admin/concierge/${message.id}` }
        ]}>
            <Head title={`コンシェルジュメッセージ #${message.id}`} />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/concierge">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">メッセージ詳細 #{message.id}</h1>
                    </div>
                    <Link href={`/admin/concierge/${message.id}/edit`}>
                        <Button className="flex items-center gap-2">
                            <Edit className="h-4 w-4" />
                            編集
                        </Button>
                    </Link>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Message Content */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageCircle className="h-5 w-5" />
                                    メッセージ内容
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <h3 className="font-semibold mb-2">メッセージ</h3>
                                        <div className="bg-gray-50 p-4 rounded-md">
                                            <p className="whitespace-pre-wrap">{message.message}</p>
                                        </div>
                                    </div>
                                    
                                    {message.admin_notes && (
                                        <div>
                                            <h3 className="font-semibold mb-2">管理メモ</h3>
                                            <div className="bg-blue-50 p-4 rounded-md">
                                                <p className="whitespace-pre-wrap">{message.admin_notes}</p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Status Update */}
                        <Card>
                            <CardHeader>
                                <CardTitle>ステータス更新</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium mb-2">ステータス</label>
                                        <Select value={status} onValueChange={setStatus}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {statuses.map((statusOption) => (
                                                    <SelectItem key={statusOption.value} value={statusOption.value}>
                                                        {statusOption.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    <div>
                                        <label className="block text-sm font-medium mb-2">管理メモ</label>
                                        <Textarea
                                            value={adminNotes}
                                            onChange={(e) => setAdminNotes(e.target.value)}
                                            placeholder="管理メモを入力..."
                                            rows={4}
                                        />
                                    </div>
                                    
                                    <Button 
                                        onClick={handleUpdateStatus}
                                        disabled={isUpdating}
                                        className="w-full"
                                    >
                                        {isUpdating ? '更新中...' : 'ステータスを更新'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* User Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    ユーザー情報
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm text-gray-600">名前</p>
                                        <p className="font-medium">{message.user?.nickname || 'Unknown'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">電話番号</p>
                                        <p className="font-medium">{message.user?.phone || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">ユーザータイプ</p>
                                        <Badge variant="outline">
                                            {message.user_type === 'guest' ? 'ゲスト' : 'キャスト'}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Message Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>メッセージ詳細</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm text-gray-600">メッセージタイプ</p>
                                        <Badge variant="outline">
                                            {messageTypes.find(t => t.value === message.message_type)?.label || message.message_type}
                                        </Badge>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">カテゴリ</p>
                                        <Badge className={getCategoryBadgeColor(message.category)}>
                                            {categories.find(c => c.value === message.category)?.label || message.category}
                                        </Badge>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">ステータス</p>
                                        <Badge className={getStatusBadgeColor(message.status)}>
                                            {statuses.find(s => s.value === message.status)?.label || message.status}
                                        </Badge>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">担当者</p>
                                        <p className="font-medium">{message.assigned_admin?.name || '未割り当て'}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Timestamps */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5" />
                                    タイムスタンプ
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm text-gray-600">作成日時</p>
                                        <p className="font-medium">
                                            {new Date(message.created_at).toLocaleString('ja-JP')}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">更新日時</p>
                                        <p className="font-medium">
                                            {new Date(message.updated_at).toLocaleString('ja-JP')}
                                        </p>
                                    </div>
                                    {message.resolved_at && (
                                        <div>
                                            <p className="text-sm text-gray-600">解決日時</p>
                                            <p className="font-medium text-green-600">
                                                {new Date(message.resolved_at).toLocaleString('ja-JP')}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>クイックアクション</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <Button 
                                        variant="outline" 
                                        className="w-full justify-start"
                                        onClick={() => {
                                            setStatus('in_progress');
                                            handleUpdateStatus();
                                        }}
                                    >
                                        <CheckCircle className="h-4 w-4 mr-2" />
                                        対応開始
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        className="w-full justify-start"
                                        onClick={() => {
                                            setStatus('resolved');
                                            handleUpdateStatus();
                                        }}
                                    >
                                        <CheckCircle className="h-4 w-4 mr-2" />
                                        解決済みにする
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        className="w-full justify-start"
                                        onClick={() => {
                                            setStatus('closed');
                                            handleUpdateStatus();
                                        }}
                                    >
                                        <CheckCircle className="h-4 w-4 mr-2" />
                                        クローズ
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
} 