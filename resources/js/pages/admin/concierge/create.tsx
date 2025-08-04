import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ArrowLeft, Save, User, MessageCircle } from 'lucide-react';
import { useState } from 'react';

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
    guests: Guest[];
    casts: Cast[];
}

export default function AdminConciergeCreate({ guests, casts }: Props) {
    const [formData, setFormData] = useState({
        user_id: '',
        user_type: 'guest',
        message: '',
        message_type: 'general',
        category: 'normal',
        status: 'pending',
        admin_notes: '',
        assigned_admin_id: '',
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

        router.post('/admin/concierge', submitData, {
            onSuccess: () => {
                setIsSubmitting(false);
                // Reset form
                setFormData({
                    user_id: '',
                    user_type: 'guest',
                    message: '',
                    message_type: 'general',
                    category: 'normal',
                    status: 'pending',
                    admin_notes: '',
                    assigned_admin_id: '',
                });
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    const getFilteredUsers = () => {
        return formData.user_type === 'guest' ? guests : casts;
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'コンシェルジュ管理', href: '/admin/concierge' },
            { title: '新規作成', href: '/admin/concierge/create' }
        ]}>
            <Head title="新規コンシェルジュメッセージ作成" />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/concierge">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">新規メッセージ作成</h1>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Form */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageCircle className="h-5 w-5" />
                                    新規メッセージ作成
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {/* User Information */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                            {isSubmitting ? '作成中...' : '作成'}
                                        </Button>
                                        <Link href="/admin/concierge">
                                            <Button variant="outline">キャンセル</Button>
                                        </Link>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* User Lists */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    ユーザー一覧
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div>
                                        <h4 className="font-medium mb-2">
                                            {formData.user_type === 'guest' ? 'ゲスト' : 'キャスト'}
                                        </h4>
                                        <div className="max-h-60 overflow-y-auto space-y-1">
                                            {getFilteredUsers().map((user) => (
                                                <div 
                                                    key={user.id} 
                                                    className="text-sm p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100"
                                                    onClick={() => handleInputChange('user_id', user.id.toString())}
                                                >
                                                    <p className="font-medium">{user.nickname}</p>
                                                    <p className="text-gray-500">ID: {user.id}</p>
                                                    <p className="text-gray-500">{user.phone}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Help */}
                        <Card>
                            <CardHeader>
                                <CardTitle>ヘルプ</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3 text-sm">
                                    <div>
                                        <h4 className="font-medium">メッセージタイプ</h4>
                                        <ul className="text-gray-600 space-y-1">
                                            <li>• お問い合わせ: 一般的な質問</li>
                                            <li>• サポート: 技術的なサポート</li>
                                            <li>• 予約関連: 予約に関する質問</li>
                                            <li>• 支払い関連: 支払いに関する質問</li>
                                            <li>• 技術的: 技術的な問題</li>
                                            <li>• 一般: その他の質問</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h4 className="font-medium">カテゴリ</h4>
                                        <ul className="text-gray-600 space-y-1">
                                            <li>• 緊急: すぐに対応が必要</li>
                                            <li>• 通常: 通常の優先度</li>
                                            <li>• 低優先度: 時間に余裕がある</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h4 className="font-medium">ステータス</h4>
                                        <ul className="text-gray-600 space-y-1">
                                            <li>• 未対応: まだ対応していない</li>
                                            <li>• 対応中: 現在対応中</li>
                                            <li>• 解決済み: 解決完了</li>
                                            <li>• クローズ: 終了</li>
                                        </ul>
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