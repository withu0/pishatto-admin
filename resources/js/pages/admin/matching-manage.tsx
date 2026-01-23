import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Edit, Trash2, Eye, MessageCircle, User, Crown } from 'lucide-react';
import { useState} from 'react';

interface Message {
    id: number;
    message?: string;
    image?: string;
    gift_id?: number;
    sender_guest_id?: number;
    sender_cast_id?: number;
    created_at: string;
    is_read: boolean;
    guest?: {
        id: number;
        nickname: string;
        avatar?: string;
    };
    cast?: {
        id: number;
        nickname: string;
        avatar?: string;
    };
    gift?: {
        id: number;
        name: string;
        icon?: string;
        points: number;
    };
}

interface Chat {
    id: number;
    guest: {
        id: number;
        nickname: string;
        avatar?: string;
    };
    cast: {
        id: number;
        nickname: string;
        avatar?: string;
    };
    reservation?: {
        id: number;
        type?: string;
        scheduled_at: string;
        location?: string;
        duration?: number;
        details?: string;
        cancelled_at?: string;
        cancellation_reason?: string;
        active?: boolean;
    };
    created_at: string;
    message_count: number;
    last_message_at?: string;
    messages?: Message[];
}

interface Props {
    chats: Chat[];
}

export default function AdminMatchingManage({ chats: initialChats }: Props) {
    const [search, setSearch] = useState('');
    const [chats, setChats] = useState<Chat[]>(initialChats);
    const [selectedChat, setSelectedChat] = useState<Chat | null>(null);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showMessageModal, setShowMessageModal] = useState(false);
    const [selectedChatMessages, setSelectedChatMessages] = useState<Message[]>([]);
    const [isLoadingMessages, setIsLoadingMessages] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editForm, setEditForm] = useState({
        guest_nickname: '',
        cast_nickname: '',
        location: '',
        duration: '',
        details: ''
    });

    const filtered = chats.filter(
        (chat) =>
            chat.guest.nickname.includes(search) ||
            chat.cast.nickname.includes(search) ||
            chat.reservation?.location?.includes(search)
    );

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    // Helper function to get the first avatar URL for casts
    const getFirstAvatar = (avatarString?: string): string | undefined => {
        if (!avatarString) return undefined;
        const avatars = avatarString.split(',').map(avatar => avatar.trim());
        return avatars[0] || undefined;
    };

    // Helper function to get proper avatar URL
    const getAvatarUrl = (avatar?: string): string | undefined => {
        if (!avatar) return undefined;
        // If it's already a full URL, return as is
        if (avatar.startsWith('http')) return avatar;
        // If it's a storage path, add /storage prefix
        if (avatar.startsWith('avatars/') || avatar.startsWith('chat_images/')) {
            return `/storage/${avatar}`;
        }
        // If it's already prefixed with /storage, return as is
        if (avatar.startsWith('/storage/')) return avatar;
        // Default case
        return `/storage/${avatar}`;
    };

    const handleView = async (chat: Chat) => {
        try {
            setIsLoadingMessages(true);
            const response = await fetch(`/admin/chats/${chat.id}`);
            const data = await response.json();

            if (response.ok && data.chat) {
                setSelectedChat(data.chat);
                setSelectedChatMessages(data.chat.messages || []);
                setShowMessageModal(true);
            } else {
                throw new Error('Failed to fetch chat details');
            }
        } catch (error) {
            console.error('Failed to fetch chat details:', error);
            alert('チャット詳細の取得に失敗しました');
        } finally {
            setIsLoadingMessages(false);
        }
    };

    const handleEdit = (chat: Chat) => {
        setSelectedChat(chat);
        setEditForm({
            guest_nickname: chat.guest.nickname,
            cast_nickname: chat.cast.nickname,
            location: chat.reservation?.location || '',
            duration: chat.reservation?.duration?.toString() || '',
            details: chat.reservation?.details || ''
        });
        setShowEditModal(true);
    };

    const handleEditSubmit = async () => {
        if (!selectedChat) return;

        try {
            const response = await fetch(`/admin/chats/${selectedChat.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(editForm)
            });

            if (response.ok) {
                // Update the local state
                setChats(prev => prev.map(chat =>
                    chat.id === selectedChat.id
                        ? {
                            ...chat,
                            guest: { ...chat.guest, nickname: editForm.guest_nickname },
                            cast: { ...chat.cast, nickname: editForm.cast_nickname },
                            reservation: chat.reservation ? {
                                ...chat.reservation,
                                location: editForm.location,
                                duration: parseInt(editForm.duration) || chat.reservation.duration,
                                details: editForm.details
                            } : undefined
                        }
                        : chat
                ));
                setShowEditModal(false);
                setSelectedChat(null);
                alert('チャット情報を更新しました');
            } else {
                throw new Error('Failed to update chat');
            }
        } catch (error) {
            console.error('Failed to update chat:', error);
            alert('チャットの更新に失敗しました');
        }
    };

    const handleDelete = async () => {
        if (!selectedChat) return;

        if (!confirm(`このチャットを削除しますか？\n${selectedChat.guest.nickname} と ${selectedChat.cast.nickname} のチャット`)) {
            return;
        }

        try {
            const response = await fetch(`/admin/chats/${selectedChat.id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                setChats(prev => prev.filter(chat => chat.id !== selectedChat.id));
                setShowDeleteModal(false);
                setSelectedChat(null);
                alert('チャットを削除しました');
            } else {
                throw new Error('Failed to delete chat');
            }
        } catch (error) {
            console.error('Failed to delete chat:', error);
            alert('削除に失敗しました');
        }
    };

    const getMessageSender = (message: Message) => {
        if (message.sender_guest_id && message.guest) {
            return {
                type: 'guest' as const,
                name: message.guest.nickname || 'Unknown Guest',
                avatar: message.guest.avatar,
            };
        } else if (message.sender_cast_id && message.cast) {
            return {
                type: 'cast' as const,
                name: message.cast.nickname || 'Unknown Cast',
                avatar: message.cast.avatar,
            };
        }
        return null;
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'マッチング履歴管理', href: '/admin/matching-manage' }]}>
            <Head title="マッチング履歴管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">マッチング履歴管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>チャット一覧</CardTitle>
                        {/* <Button size="sm" className="gap-1">
                            <Plus className="w-4 h-4" />新規作成
                        </Button> */}
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="ゲスト・キャスト・場所で検索"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="max-w-xs"
                            />
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">ゲスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">キャスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">予約タイプ</th>
                                        <th className="px-3 py-2 text-left font-semibold">予約情報</th>
                                        <th className="px-3 py-2 text-left font-semibold">メッセージ数</th>
                                        <th className="px-3 py-2 text-left font-semibold">作成日時</th>
                                        <th className="px-3 py-2 text-left font-semibold">最終メッセージ</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="text-center py-6 text-muted-foreground">
                                                該当するデータがありません
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                                                            {item.guest.avatar ? (
                                                                <img
                                                                    src={getAvatarUrl(item.guest.avatar)}
                                                                    alt={item.guest.nickname}
                                                                    className="w-8 h-8 rounded-full object-cover"
                                                                    onError={(e) => {
                                                                        const target = e.target as HTMLImageElement;
                                                                        target.style.display = 'none';
                                                                        target.nextElementSibling?.classList.remove('hidden');
                                                                    }}
                                                                />
                                                            ) : null}
                                                            <span className={`text-xs text-gray-500 ${item.guest.avatar ? 'hidden' : ''}`}>
                                                                {(item.guest.nickname || 'G').charAt(0)}
                                                            </span>
                                                        </div>
                                                        {item.guest.nickname}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                                                            {item.cast.avatar ? (
                                                                <img
                                                                    src={getAvatarUrl(getFirstAvatar(item.cast.avatar))}
                                                                    alt={item.cast.nickname}
                                                                    className="w-8 h-8 rounded-full object-cover"
                                                                    onError={(e) => {
                                                                        const target = e.target as HTMLImageElement;
                                                                        target.style.display = 'none';
                                                                        target.nextElementSibling?.classList.remove('hidden');
                                                                    }}
                                                                />
                                                            ) : null}
                                                            <span className={`text-xs text-gray-500 ${item.cast.avatar ? 'hidden' : ''}`}>
                                                                {(item.cast.nickname || 'C').charAt(0)}
                                                            </span>
                                                        </div>
                                                        {item.cast.nickname}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.reservation ? (
                                                        <div className="flex flex-col gap-1">
                                                            <div className="flex items-center gap-1">
                                                                {(item.reservation as any).type === 'free' ? (
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        <span className="mr-1">🆓</span>
                                                                        フリーコール
                                                                    </Badge>
                                                                ) : (item.reservation as any).type === 'Pishatto' ? (
                                                                    <Badge variant="default" className="text-xs">
                                                                        <span className="mr-1">💎</span>
                                                                        ピシャットコール
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant="outline" className="text-xs">
                                                                        {(item.reservation as any).type || '通常'}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                            {item.reservation.cancelled_at && (
                                                                <Badge variant="destructive" className="text-xs w-fit">
                                                                    ❌ キャンセル
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <Badge variant="outline" className="text-xs">予約なし</Badge>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.reservation ? (
                                                        <div className="text-xs space-y-1">
                                                            <div>{formatDate(item.reservation.scheduled_at)}</div>
                                                            <div className="text-gray-500">{item.reservation.location}</div>
                                                            <div className="text-gray-500">{item.reservation.duration}時間</div>
                                                            {item.reservation.cancelled_at && (
                                                                <div className="mt-1">
                                                                    <Badge variant="destructive" className="text-xs">
                                                                        ❌ キャンセル済み
                                                                    </Badge>
                                                                    {item.reservation.cancellation_reason && (
                                                                        <div className="text-red-600 text-xs mt-1">
                                                                            {item.reservation.cancellation_reason}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <Badge variant="outline">予約なし</Badge>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-1">
                                                        <MessageCircle className="w-4 h-4 text-blue-500" />
                                                        <span>{item.message_count}</span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">{formatDate(item.created_at)}</td>
                                                <td className="px-3 py-2">
                                                    {item.last_message_at ? formatDate(item.last_message_at) : 'なし'}
                                                </td>
                                                <td className="px-3 py-2 flex gap-2 items-center justify-center">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleView(item)}
                                                        disabled={isLoadingMessages}
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleEdit(item)}
                                                    >
                                                        <Edit className="w-4 h-4" />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => {
                                                            setSelectedChat(item);
                                                            setShowDeleteModal(true);
                                                        }}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Message Modal */}
            <Dialog open={showMessageModal} onOpenChange={setShowMessageModal}>
                <DialogContent className="max-w-4xl max-h-[80vh] overflow-hidden flex flex-col">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <MessageCircle className="w-5 h-5" />
                            チャットメッセージ
                            {selectedChat && (
                                <span className="text-sm font-normal text-gray-500">
                                    ({selectedChat.guest.nickname} と {selectedChat.cast.nickname})
                                </span>
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            このチャットの全メッセージを表示します。メッセージ、画像、ギフトのやり取りを確認できます。
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-y-auto p-4 space-y-4">
                        {isLoadingMessages ? (
                            <div className="text-center text-gray-500 py-8">
                                メッセージを読み込み中...
                            </div>
                        ) : selectedChatMessages.length === 0 ? (
                            <div className="text-center text-gray-500 py-8">
                                メッセージがありません
                            </div>
                        ) : (
                            selectedChatMessages.map((message) => {
                                const sender = getMessageSender(message);
                                if (!sender) return null;

                                return (
                                    <div key={message.id} className="flex gap-3">
                                        <div className="flex-shrink-0">
                                            <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                                                {sender.avatar ? (
                                                    <img
                                                        src={getAvatarUrl(sender.type === 'cast' ? getFirstAvatar(sender.avatar) : sender.avatar)}
                                                        alt={sender.name}
                                                        className="w-10 h-10 rounded-full object-cover"
                                                        onError={(e) => {
                                                            const target = e.target as HTMLImageElement;
                                                            target.style.display = 'none';
                                                            target.nextElementSibling?.classList.remove('hidden');
                                                        }}
                                                    />
                                                ) : null}
                                                <span className={`text-sm text-gray-500 ${sender.avatar ? 'hidden' : ''}`}>
                                                    {(sender.name || 'U').charAt(0)}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-medium text-sm">{sender.name}</span>
                                                <div className="flex items-center gap-1">
                                                    {sender.type === 'guest' ? (
                                                        <User className="w-3 h-3 text-blue-500" />
                                                    ) : (
                                                        <Crown className="w-3 h-3 text-yellow-500" />
                                                    )}
                                                    <span className="text-xs text-gray-500">
                                                        {formatDate(message.created_at)}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="bg-gray-50 rounded-lg p-3">
                                                {message.message && (
                                                    <p className="text-sm mb-2">{message.message}</p>
                                                )}

                                                {message.image && (
                                                    <div className="mb-2">
                                                        <img
                                                            src={getAvatarUrl(message.image)}
                                                            alt="メッセージ画像"
                                                            className="max-w-xs rounded-lg"
                                                        />
                                                    </div>
                                                )}

                                                {message.gift && (
                                                    <div className="flex items-center gap-2 p-2 bg-yellow-50 rounded border border-yellow-200">
                                                        <span className="text-yellow-600">🎁</span>
                                                        <span className="text-sm font-medium">{message.gift.name}</span>
                                                        <Badge variant="secondary" className="text-xs">
                                                            {message.gift.points}pt
                                                        </Badge>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog open={showEditModal} onOpenChange={setShowEditModal}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>チャット情報を編集</DialogTitle>
                        <DialogDescription>
                            ゲストとキャストの情報、予約情報を編集できます。
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <label className="text-sm font-medium">ゲスト名</label>
                            <Input
                                value={editForm.guest_nickname}
                                onChange={(e) => setEditForm(prev => ({ ...prev, guest_nickname: e.target.value }))}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium">キャスト名</label>
                            <Input
                                value={editForm.cast_nickname}
                                onChange={(e) => setEditForm(prev => ({ ...prev, cast_nickname: e.target.value }))}
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium">場所</label>
                            <Input
                                value={editForm.location}
                                onChange={(e) => setEditForm(prev => ({ ...prev, location: e.target.value }))}
                                className="mt-1"
                                placeholder="予約場所"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium">時間（時間）</label>
                            <Input
                                type="number"
                                value={editForm.duration}
                                onChange={(e) => setEditForm(prev => ({ ...prev, duration: e.target.value }))}
                                className="mt-1"
                                placeholder="予約時間"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium">詳細</label>
                            <textarea
                                value={editForm.details}
                                onChange={(e) => setEditForm(prev => ({ ...prev, details: e.target.value }))}
                                className="mt-1 w-full p-2 border rounded-md"
                                rows={3}
                                placeholder="予約詳細"
                            />
                        </div>
                    </div>

                    <div className="flex space-x-3 mt-6">
                        <Button
                            variant="outline"
                            onClick={() => setShowEditModal(false)}
                            className="flex-1"
                        >
                            キャンセル
                        </Button>
                        <Button
                            onClick={handleEditSubmit}
                            className="flex-1"
                        >
                            更新
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Delete Modal */}
            {showDeleteModal && selectedChat && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">
                            チャットを削除
                        </h3>
                        <p className="text-gray-600 mb-4">
                            <strong>{selectedChat.guest.nickname}</strong> と <strong>{selectedChat.cast.nickname}</strong> のチャットを削除しますか？
                        </p>
                        <div className="mb-4 p-3 bg-gray-50 rounded">
                            <p className="text-sm text-gray-600">
                                メッセージ数: {selectedChat.message_count}<br/>
                                作成日時: {formatDate(selectedChat.created_at)}
                            </p>
                        </div>
                        <div className="flex space-x-3">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setShowDeleteModal(false);
                                    setSelectedChat(null);
                                }}
                                className="flex-1"
                            >
                                キャンセル
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                                className="flex-1"
                            >
                                削除
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
