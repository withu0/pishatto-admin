import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ArrowLeft, Save, User, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

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

interface Guest {
    id: number;
    nickname: string;
    phone: string;
}

interface Cast {
    id: number;
    nickname: string;
    phone: string;
}

interface Props {
    message: ConciergeMessage;
    guests: Guest[];
    casts: Cast[];
}

export default function AdminConciergeEdit({ message, guests, casts }: Props) {
    const [formData, setFormData] = useState({
        user_id: message.user_id.toString(),
        user_type: message.user_type,
        message: message.message,
        message_type: message.message_type,
        category: message.category,
        status: message.status,
        admin_notes: message.admin_notes || '',
        assigned_admin_id: message.assigned_admin_id?.toString() || '',
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

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

    const userTypes = [
        { value: 'guest', label: 'ゲスト' },
        { value: 'cast', label: 'キャスト' },
    ];

    const handleInputChange = (field: string, value: string) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        const submitData = {
            ...formData,
            user_id: parseInt(formData.user_id),
            assigned_admin_id: formData.assigned_admin_id ? parseInt(formData.assigned_admin_id) : null,
        };

        router.put(`/admin/concierge/${message.id}`, submitData, {
            onSuccess: () => {
                setIsSubmitting(false);
            },
            onError: (errors) => {
                setIsSubmitting(false);
                console.error('Update error:', errors);
            },
        });
    };

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

    return (
        <AppLayout breadcrumbs={[
            { title: 'コンシェルジュ管理', href: '/admin/concierge' },
            { title: `メッセージ #${message.id}`, href: `/admin/concierge/${message.id}` },
            { title: '編集', href: `/admin/concierge/${message.id}/edit` }
        ]}>
            <Head title={`コンシェルジュメッセージ編集 #${message.id}`} />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div className="flex items-center gap-4">
                        <Link href={`/admin/concierge/${message.id}`}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">メッセージ編集 #{message.id}</h1>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Form */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageCircle className="h-5 w-5" />
                                    メッセージ編集
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {/* User Information */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium mb-2">ユーザーID</label>
                                            <Input
                                                value={formData.user_id}
                                                onChange={(e) => handleInputChange('user_id', e.target.value)}
                                                placeholder="ユーザーID"
                                                type="number"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-2">ユーザータイプ</label>
                                            <Select value={formData.user_type} onValueChange={(value) => handleInputChange('user_type', value)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {userTypes.map((type) => (
                                                        <SelectItem key={type.value} value={type.value}>
                                                            {type.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {/* Message Content */}
                                    <div>
                                        <label className="block text-sm font-medium mb-2">メッセージ</label>
                                        <Textarea
                                            value={formData.message}
                                            onChange={(e) => handleInputChange('message', e.target.value)}
                                            placeholder="メッセージを入力"
                                            rows={6}
                                            required
                                        />
                                    </div>

                                    {/* Message Type and Category */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium mb-2">メッセージタイプ</label>
                                            <Select value={formData.message_type} onValueChange={(value) => handleInputChange('message_type', value)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {messageTypes.map((type) => (
                                                        <SelectItem key={type.value} value={type.value}>
                                                            {type.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-2">カテゴリ</label>
                                            <Select value={formData.category} onValueChange={(value) => handleInputChange('category', value)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categories.map((category) => (
                                                        <SelectItem key={category.value} value={category.value}>
                                                            {category.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    {/* Status and Assigned Admin */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium mb-2">ステータス</label>
                                            <Select value={formData.status} onValueChange={(value) => handleInputChange('status', value)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {statuses.map((status) => (
                                                        <SelectItem key={status.value} value={status.value}>
                                                            {status.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium mb-2">担当者ID</label>
                                            <Input
                                                value={formData.assigned_admin_id}
                                                onChange={(e) => handleInputChange('assigned_admin_id', e.target.value)}
                                                placeholder="担当者ID（オプション）"
                                                type="number"
                                            />
                                        </div>
                                    </div>

                                    {/* Admin Notes */}
                                    <div>
                                        <label className="block text-sm font-medium mb-2">管理メモ</label>
                                        <Textarea
                                            value={formData.admin_notes}
                                            onChange={(e) => handleInputChange('admin_notes', e.target.value)}
                                            placeholder="管理メモを入力（オプション）"
                                            rows={4}
                                        />
                                    </div>

                                    {/* Submit Button */}
                                    <div className="flex gap-4">
                                        <Button 
                                            type="submit" 
                                            disabled={isSubmitting}
                                            className="flex items-center gap-2"
                                        >
                                            <Save className="h-4 w-4" />
                                            {isSubmitting ? '保存中...' : '保存'}
                                        </Button>
                                        <Link href={`/admin/concierge/${message.id}`}>
                                            <Button variant="outline">キャンセル</Button>
                                        </Link>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Current Message Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    現在の情報
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div>
                                        <p className="text-sm text-gray-600">ユーザー</p>
                                        <p className="font-medium">{message.user?.nickname || 'Unknown'}</p>
                                        <p className="text-sm text-gray-500">{message.user?.phone || 'N/A'}</p>
                                    </div>
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

                        {/* User Lists */}
                        <Card>
                            <CardHeader>
                                <CardTitle>ユーザー一覧</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <h4 className="font-medium mb-2">ゲスト</h4>
                                        <div className="max-h-40 overflow-y-auto space-y-1">
                                            {guests.map((guest) => (
                                                <div key={guest.id} className="text-sm p-2 bg-gray-50 rounded">
                                                    <p className="font-medium">{guest.nickname}</p>
                                                    <p className="text-gray-500">ID: {guest.id}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className="font-medium mb-2">キャスト</h4>
                                        <div className="max-h-40 overflow-y-auto space-y-1">
                                            {casts.map((cast) => (
                                                <div key={cast.id} className="text-sm p-2 bg-gray-50 rounded">
                                                    <p className="font-medium">{cast.nickname}</p>
                                                    <p className="text-gray-500">ID: {cast.id}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
} 