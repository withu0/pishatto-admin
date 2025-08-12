import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Edit, Trash2, Plus, Eye, MessageCircle, User, Crown, Search, Filter } from 'lucide-react';
import { useState, useEffect } from 'react';
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

interface Stats {
    total: number;
    pending: number;
    urgent: number;
    resolved: number;
}

interface Filters {
    status?: string;
    message_type?: string;
    category?: string;
    search?: string;
    user_type?: string;
}

interface Props {
    messages: {
        data: ConciergeMessage[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: Stats;
    filters: Filters;
    newCounts: { guest: number; cast: number };
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function AdminConciergeIndex({ messages, stats, filters, newCounts, flash }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [typeFilter, setTypeFilter] = useState(filters.message_type || 'all');
    const [categoryFilter, setCategoryFilter] = useState(filters.category || 'all');
    const [userTypeFilter, setUserTypeFilter] = useState(filters.user_type || 'all');
    const [activeTab, setActiveTab] = useState<'guest' | 'cast'>(filters.user_type === 'cast' ? 'cast' : 'guest');
    const [showGuestDot, setShowGuestDot] = useState((newCounts?.guest || 0) > 0);
    const [showCastDot, setShowCastDot] = useState((newCounts?.cast || 0) > 0);
    const STORAGE_KEY = 'concierge:lastSeenCounts';
    const [lastSeenCounts, setLastSeenCounts] = useState<{ guest: number; cast: number }>(() => {
        try {
            const saved = typeof window !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null;
            if (saved) {
                const parsed = JSON.parse(saved);
                if (typeof parsed?.guest === 'number' && typeof parsed?.cast === 'number') {
                    return parsed;
                }
            }
        } catch {}
        return { guest: 0, cast: 0 };
    });
    const [selectedMessage, setSelectedMessage] = useState<ConciergeMessage | null>(null);
    const [showViewDialog, setShowViewDialog] = useState(false);
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [newMessage, setNewMessage] = useState({
        user_id: '',
        user_type: 'guest',
        message: '',
        message_type: 'general',
        category: 'normal',
        status: 'pending',
        admin_notes: '',
    });

    // Show flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // Persist last seen counts when they change
    useEffect(() => {
        try {
            if (typeof window !== 'undefined') {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(lastSeenCounts));
            }
        } catch {}
    }, [lastSeenCounts]);

    // Derive dot visibility from current counts vs last seen counts
    useEffect(() => {
        const guestCount = newCounts?.guest || 0;
        const castCount = newCounts?.cast || 0;
        const guestHasUnseen = guestCount > lastSeenCounts.guest;
        const castHasUnseen = castCount > lastSeenCounts.cast;
        setShowGuestDot(guestHasUnseen);
        setShowCastDot(castHasUnseen);
        try {
            if (typeof window !== 'undefined') {
                const hasUnseen = guestHasUnseen || castHasUnseen;
                localStorage.setItem('concierge:hasUnseen', hasUnseen ? '1' : '0');
                window.dispatchEvent(new CustomEvent('concierge:hasUnseenChanged', { detail: hasUnseen }));
            }
        } catch {}
    }, [newCounts, lastSeenCounts]);

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

    const applyFilters = () => {
        const filters: any = { search, user_type: activeTab };
        
        if (statusFilter && statusFilter !== 'all') {
            filters.status = statusFilter;
        }
        if (typeFilter && typeFilter !== 'all') {
            filters.message_type = typeFilter;
        }
        if (categoryFilter && categoryFilter !== 'all') {
            filters.category = categoryFilter;
        }
        // user_type is enforced by tab
        router.get('/admin/concierge', filters, { preserveState: true, preserveScroll: true, replace: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('all');
        setTypeFilter('all');
        setCategoryFilter('all');
        setUserTypeFilter(activeTab);
        router.get('/admin/concierge', { user_type: activeTab }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const handleViewMessage = (message: ConciergeMessage) => {
        setSelectedMessage(message);
        setShowViewDialog(true);
    };

    const handleCreateMessage = () => {
        setShowCreateDialog(true);
    };

    const handleSubmitCreate = () => {
        router.post('/admin/concierge', newMessage, {
            onSuccess: () => {
                setShowCreateDialog(false);
                setNewMessage({
                    user_id: '',
                    user_type: 'guest',
                    message: '',
                    message_type: 'general',
                    category: 'normal',
                    status: 'pending',
                    admin_notes: '',
                });
            },
        });
    };

    const handleUpdateStatus = (messageId: number, status: string) => {
        router.put(`/admin/concierge/${messageId}/status`, { status });
    };

    const handleDeleteMessage = (messageId: number) => {
        if (confirm('このメッセージを削除しますか？')) {
            router.delete(`/admin/concierge/${messageId}`, {
                onSuccess: () => {
                    // Success is handled by redirect
                },
                onError: (errors) => {
                    console.error('Delete error:', errors);
                },
            });
        }
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
        <AppLayout breadcrumbs={[{ title: 'コンシェルジュ管理', href: '/admin/concierge' }]}>
            <Head title="コンシェルジュ管理" />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">コンシェルジュ管理</h1>
                    {/* <Button onClick={handleCreateMessage} className="flex items-center gap-2">
                        <Plus className="h-4 w-4" />
                        新規作成
                    </Button> */}
                </div>

                {/* Tabs: Guest / Cast */}
                <div className="mb-4">
                    <div className="inline-flex rounded-md border p-1 bg-white">
                        <button
                            type="button"
                            className={`relative px-4 py-2 rounded-sm text-sm font-medium ${activeTab === 'guest' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                            onClick={() => {
                                setActiveTab('guest');
                                setUserTypeFilter('guest');
                                if (showGuestDot) {
                                    setLastSeenCounts((prev) => ({ ...prev, guest: newCounts?.guest || 0 }));
                                    setShowGuestDot(false);
                                }
                                router.get(
                                    '/admin/concierge',
                                    {
                                        user_type: 'guest',
                                        search,
                                        status: statusFilter !== 'all' ? statusFilter : undefined,
                                        message_type: typeFilter !== 'all' ? typeFilter : undefined,
                                        category: categoryFilter !== 'all' ? categoryFilter : undefined,
                                    },
                                    { preserveState: true, preserveScroll: true, replace: true }
                                );
                            }}
                        >
                            ゲスト
                            {showGuestDot && (
                                <span className="absolute -top-1 -right-1 inline-flex h-2 w-2 rounded-full bg-red-500" />
                            )}
                        </button>
                        <button
                            type="button"
                            className={`relative px-4 py-2 rounded-sm text-sm font-medium ${activeTab === 'cast' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                            onClick={() => {
                                setActiveTab('cast');
                                setUserTypeFilter('cast');
                                if (showCastDot) {
                                    setLastSeenCounts((prev) => ({ ...prev, cast: newCounts?.cast || 0 }));
                                    setShowCastDot(false);
                                }
                                router.get(
                                    '/admin/concierge',
                                    {
                                        user_type: 'cast',
                                        search,
                                        status: statusFilter !== 'all' ? statusFilter : undefined,
                                        message_type: typeFilter !== 'all' ? typeFilter : undefined,
                                        category: categoryFilter !== 'all' ? categoryFilter : undefined,
                                    },
                                    { preserveState: true, preserveScroll: true, replace: true }
                                );
                            }}
                        >
                            キャスト
                            {showCastDot && (
                                <span className="absolute -top-1 -right-1 inline-flex h-2 w-2 rounded-full bg-red-500" />
                            )}
                        </button>
                    </div>
                </div>

                {/* Statistics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">総メッセージ数</p>
                                    <p className="text-2xl font-bold">{stats.total}</p>
                                </div>
                                <MessageCircle className="h-8 w-8 text-blue-500" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">未対応</p>
                                    <p className="text-2xl font-bold text-yellow-600">{stats.pending}</p>
                                </div>
                                <Crown className="h-8 w-8 text-yellow-500" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">緊急</p>
                                    <p className="text-2xl font-bold text-red-600">{stats.urgent}</p>
                                </div>
                                <Crown className="h-8 w-8 text-red-500" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-gray-600">解決済み</p>
                                    <p className="text-2xl font-bold text-green-600">{stats.resolved}</p>
                                </div>
                                <Crown className="h-8 w-8 text-green-500" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="h-5 w-5" />
                            フィルター
                        </CardTitle>
                    </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <Input
                                    placeholder="検索..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full"
                                />
                            </div>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="ステータス" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">すべて</SelectItem>
                                    {statuses.map((status) => (
                                        <SelectItem key={status.value} value={status.value}>
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={typeFilter} onValueChange={setTypeFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="メッセージタイプ" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">すべて</SelectItem>
                                    {messageTypes.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                                <SelectTrigger>
                                    <SelectValue placeholder="カテゴリ" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">すべて</SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem key={category.value} value={category.value}>
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                    {/* User type is controlled by the tabs above */}
                            <div className="flex gap-2">
                                <Button onClick={applyFilters} className="flex-1">
                                    適用
                                </Button>
                                <Button onClick={clearFilters} variant="outline">
                                    クリア
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Messages Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>メッセージ一覧</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">ID</th>
                                        <th className="text-left p-2">ユーザー</th>
                                        <th className="text-left p-2">メッセージ</th>
                                        <th className="text-left p-2">タイプ</th>
                                        <th className="text-left p-2">カテゴリ</th>
                                        <th className="text-left p-2">ステータス</th>
                                        <th className="text-left p-2">作成日</th>
                                        <th className="text-left p-2">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {messages.data.map((message) => (
                                        <tr key={message.id} className="border-b hover:bg-gray-50">
                                            <td className="p-2">{message.id}</td>
                                            <td className="p-2">
                                                <div className="flex items-center gap-2">
                                                    <User className="h-4 w-4" />
                                                    <span>{message.user?.nickname || 'Unknown'}</span>
                                                    <Badge variant="outline">
                                                        {message.user_type === 'guest' ? 'ゲスト' : 'キャスト'}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                <div className="max-w-xs truncate">
                                                    {message.message}
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                <Badge variant="outline">
                                                    {messageTypes.find(t => t.value === message.message_type)?.label || message.message_type}
                                                </Badge>
                                            </td>
                                            <td className="p-2">
                                                <Badge className={getCategoryBadgeColor(message.category)}>
                                                    {categories.find(c => c.value === message.category)?.label || message.category}
                                                </Badge>
                                            </td>
                                            <td className="p-2">
                                                <Badge className={getStatusBadgeColor(message.status)}>
                                                    {statuses.find(s => s.value === message.status)?.label || message.status}
                                                </Badge>
                                            </td>
                                            <td className="p-2">
                                                {new Date(message.created_at).toLocaleDateString('ja-JP')}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleViewMessage(message)}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    <Link href={`/admin/concierge/${message.id}/edit`}>
                                                        <Button size="sm" variant="outline">
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleDeleteMessage(message.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {messages.last_page > 1 && (
                            <div className="flex justify-center mt-4">
                                <div className="flex gap-2">
                                    {Array.from({ length: messages.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            variant={page === messages.current_page ? "default" : "outline"}
                                            size="sm"
                                            onClick={() =>
                                                router.get(
                                                    '/admin/concierge',
                                                    {
                                                        page,
                                                        user_type: activeTab,
                                                        search,
                                                        status: statusFilter !== 'all' ? statusFilter : undefined,
                                                        message_type: typeFilter !== 'all' ? typeFilter : undefined,
                                                        category: categoryFilter !== 'all' ? categoryFilter : undefined,
                                                    },
                                                    { preserveState: true, preserveScroll: true, replace: true }
                                                )
                                            }
                                        >
                                            {page}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* View Message Dialog */}
                <Dialog open={showViewDialog} onOpenChange={setShowViewDialog}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>メッセージ詳細</DialogTitle>
                        </DialogHeader>
                        {selectedMessage && (
                            <div className="space-y-4">
                                <div>
                                    <h3 className="font-semibold">ユーザー情報</h3>
                                    <p>名前: {selectedMessage.user?.nickname || 'Unknown'}</p>
                                    <p>タイプ: {selectedMessage.user_type === 'guest' ? 'ゲスト' : 'キャスト'}</p>
                                </div>
                                <div>
                                    <h3 className="font-semibold">メッセージ</h3>
                                    <p className="whitespace-pre-wrap">{selectedMessage.message}</p>
                                </div>
                                <div>
                                    <h3 className="font-semibold">管理メモ</h3>
                                    <p className="whitespace-pre-wrap">{selectedMessage.admin_notes || 'なし'}</p>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h3 className="font-semibold">タイプ</h3>
                                        <Badge variant="outline">
                                            {messageTypes.find(t => t.value === selectedMessage.message_type)?.label || selectedMessage.message_type}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">カテゴリ</h3>
                                        <Badge className={getCategoryBadgeColor(selectedMessage.category)}>
                                            {categories.find(c => c.value === selectedMessage.category)?.label || selectedMessage.category}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">ステータス</h3>
                                        <Badge className={getStatusBadgeColor(selectedMessage.status)}>
                                            {statuses.find(s => s.value === selectedMessage.status)?.label || selectedMessage.status}
                                        </Badge>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">担当者</h3>
                                        <p>{selectedMessage.assigned_admin?.name || '未割り当て'}</p>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Link href={`/admin/concierge/${selectedMessage.id}/edit`}>
                                        <Button>編集</Button>
                                    </Link>
                                    <Button
                                        variant="outline"
                                        onClick={() => setShowViewDialog(false)}
                                    >
                                        閉じる
                                    </Button>
                                </div>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>

                {/* Create Message Dialog */}
                <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>新規メッセージ作成</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-2">ユーザーID</label>
                                <Input
                                    value={newMessage.user_id}
                                    onChange={(e) => setNewMessage({ ...newMessage, user_id: e.target.value })}
                                    placeholder="ユーザーIDを入力"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-2">ユーザータイプ</label>
                                <Select value={newMessage.user_type} onValueChange={(value) => setNewMessage({ ...newMessage, user_type: value as 'guest' | 'cast' })}>
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
                                <label className="block text-sm font-medium mb-2">メッセージ</label>
                                <textarea
                                    value={newMessage.message}
                                    onChange={(e) => setNewMessage({ ...newMessage, message: e.target.value })}
                                    className="w-full p-2 border rounded-md"
                                    rows={4}
                                    placeholder="メッセージを入力"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-2">メッセージタイプ</label>
                                    <Select value={newMessage.message_type} onValueChange={(value) => setNewMessage({ ...newMessage, message_type: value })}>
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
                                    <Select value={newMessage.category} onValueChange={(value) => setNewMessage({ ...newMessage, category: value })}>
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
                            <div>
                                <label className="block text-sm font-medium mb-2">管理メモ</label>
                                <textarea
                                    value={newMessage.admin_notes}
                                    onChange={(e) => setNewMessage({ ...newMessage, admin_notes: e.target.value })}
                                    className="w-full p-2 border rounded-md"
                                    rows={3}
                                    placeholder="管理メモを入力（オプション）"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={handleSubmitCreate}>作成</Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowCreateDialog(false)}
                                >
                                    キャンセル
                                </Button>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
} 