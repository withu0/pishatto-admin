import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import { Edit, Trash2, Image as ImageIcon, Gift, X } from 'lucide-react';
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

interface Gift {
    id: number;
    name: string;
    icon?: string;
    points: number;
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

interface Message {
    id: number;
    guest: string;
    cast: string;
    content: string;
    image?: string;
    gift?: Gift;
    date: string;
}

interface RawMessage {
    id: number;
    chat_id: number;
    sender_guest_id?: number;
    sender_cast_id?: number;
    message?: string;
    image?: string;
    gift_id?: number;
    created_at: string;
    guest?: Guest;
    cast?: Cast;
    gift?: Gift;
    chat?: {
        guest?: Guest;
        cast?: Cast;
    };
}

interface PaginatedMessages {
    data: Message[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number;
    to?: number;
}

interface Props {
    messages: PaginatedMessages;
    guests: Guest[];
    casts: Cast[];
    gifts: Gift[];
    rawMessages: RawMessage[];
}

interface MessageFormData {
    chat_id: string;
    sender_guest_id: string;
    sender_cast_id: string;
    message: string;
    gift_id: string;
    image: File | null;
}

export default function AdminMessages({ messages, guests, casts, gifts, rawMessages }: Props) {
    const [search, setSearch] = useState('');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editingMessage, setEditingMessage] = useState<Message | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [existingImage, setExistingImage] = useState<string | null>(null);
    const [formData, setFormData] = useState<MessageFormData>({
        chat_id: '',
        sender_guest_id: '',
        sender_cast_id: '',
        message: '',
        gift_id: '',
        image: null
    });

    const filtered = messages.data.filter(
        (m) => m.guest.includes(search) || m.cast.includes(search) || m.content.includes(search)
    );

        const renderTextContent = (content: string) => {
        // Debug: Log what we're receiving
        console.log('renderTextContent received:', content);
        console.log('Content length:', content.length);
        console.log('Starts with proposal?', content.startsWith('{"type":"proposal"'));
        
        try {
            const parsed = JSON.parse(content);
            console.log('Parsed successfully:', parsed);
            
            if (parsed && parsed.type === 'proposal') {
                
                const dateText = parsed.date ? new Date(parsed.date).toLocaleDateString('ja-JP') : '-';
                const durationText = parsed.duration || '-';
                const peopleText = parsed.people || '-';
                const totalPointsText = parsed.totalPoints || '-';
                const extensionPointsText = parsed.extensionPoints || '-';
                const guestIdText = parsed.guestId ? `G-${parsed.guestId}` : '-';
                
                return (
                    <div className="flex items-center gap-4 text-sm">
                        <div className="flex items-center gap-1">
                            <span className="text-orange-500">⭐</span>
                            <span className="text-gray-600">P:</span>
                            <span className="font-medium">{totalPointsText}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <span className="text-gray-500">📅</span>
                            <span className="text-gray-600">D:</span>
                            <span className="font-medium">{dateText}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <span className="text-gray-500">⏰</span>
                            <span className="text-gray-600">T:</span>
                            <span className="font-medium">{durationText}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <span className="text-blue-500">👥</span>
                            <span className="text-gray-600">G:</span>
                            <span className="font-medium">{peopleText}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <span className="text-gray-500">↩️</span>
                            <span className="text-gray-600">E:</span>
                            <span className="font-medium">+{extensionPointsText}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <span className="text-blue-500 bg-blue-500 text-white px-1 py-0.5 rounded text-xs font-bold">GU</span>
                            <span className="text-gray-600">ID:</span>
                            <span className="font-medium">{guestIdText}</span>
                        </div>
                    </div>
                );
            }
        } catch {
            // not JSON, fall back to plain text
        }
        return content;
    };

    const resetForm = () => {
        setFormData({
            chat_id: '',
            sender_guest_id: '',
            sender_cast_id: '',
            message: '',
            gift_id: '',
            image: null
        });
        setExistingImage(null);
    };

    const handleCreate = async () => {
        setIsLoading(true);
        try {
            const formDataToSend = new FormData();
            formDataToSend.append('chat_id', formData.chat_id);
            if (formData.sender_guest_id) formDataToSend.append('sender_guest_id', formData.sender_guest_id);
            if (formData.sender_cast_id) formDataToSend.append('sender_cast_id', formData.sender_cast_id);
            if (formData.message) formDataToSend.append('message', formData.message);
            if (formData.gift_id) formDataToSend.append('gift_id', formData.gift_id);
            if (formData.image) formDataToSend.append('image', formData.image);

            const response = await fetch('/admin/messages', {
                method: 'POST',
                body: formDataToSend,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });

            const result = await response.json();
            
            if (result.success) {
                toast.success(result.message);
                setIsCreateModalOpen(false);
                resetForm();
                router.reload();
            } else {
                toast.error('エラーが発生しました。');
            }
        } catch (error) {
            console.error('Create error:', error);
            toast.error('エラーが発生しました。');
        } finally {
            setIsLoading(false);
        }
    };

    const handleEdit = async () => {
        if (!editingMessage) return;
        
        setIsLoading(true);
        try {
            const formDataToSend = new FormData();
            formDataToSend.append('chat_id', formData.chat_id);
            if (formData.sender_guest_id) formDataToSend.append('sender_guest_id', formData.sender_guest_id);
            if (formData.sender_cast_id) formDataToSend.append('sender_cast_id', formData.sender_cast_id);
            if (formData.message) formDataToSend.append('message', formData.message);
            if (formData.gift_id) formDataToSend.append('gift_id', formData.gift_id);
            if (formData.image) formDataToSend.append('image', formData.image);
            // If there was an existing image but no new image is selected, we need to indicate image removal
            if (existingImage && !formData.image) {
                formDataToSend.append('remove_image', '1');
            }
            formDataToSend.append('_method', 'PUT');



            const response = await fetch(`/admin/messages/${editingMessage.id}`, {
                method: 'POST',
                body: formDataToSend,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Response error text:', errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                toast.success(result.message);
                setIsEditModalOpen(false);
                setEditingMessage(null);
                resetForm();
                router.reload();
            } else {
                if (result.errors) {
                    // Handle validation errors
                    const errorMessages = Object.values(result.errors).flat().join(', ');
                    toast.error(`バリデーションエラー: ${errorMessages}`);
                } else {
                    toast.error(result.message || 'エラーが発生しました。');
                }
            }
        } catch (error) {
            console.error('Edit error:', error);
            if (error instanceof Error) {
                toast.error(`エラーが発生しました: ${error.message}`);
            } else {
                toast.error('エラーが発生しました。');
            }
        } finally {
            setIsLoading(false);
        }
    };

    const handleDelete = async (messageId: number) => {
        try {
            const response = await fetch(`/admin/messages/${messageId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                }
            });

            const result = await response.json();
            
            if (result.success) {
                toast.success(result.message);
                router.reload();
            } else {
                toast.error('エラーが発生しました。');
            }
        } catch (error) {
            console.error('Delete error:', error);
            toast.error('エラーが発生しました。');
        }
    };

    const openEditModal = (message: Message) => {
        setEditingMessage(message);
        
        // Find the raw message data for this message
        const rawMessage = rawMessages.find(rm => rm.id === message.id);
        
        if (rawMessage) {
            setFormData({
                chat_id: rawMessage.chat_id.toString(),
                sender_guest_id: rawMessage.sender_guest_id?.toString() || '',
                sender_cast_id: rawMessage.sender_cast_id?.toString() || '',
                message: rawMessage.message || '',
                gift_id: rawMessage.gift_id?.toString() || '',
                image: null // We'll show the existing image preview
            });
            // Set existing image if available
            setExistingImage(rawMessage.image ? `/storage/${rawMessage.image}` : null);
        } else {
            // Fallback to basic data
            setFormData({
                chat_id: '1',
                sender_guest_id: '',
                sender_cast_id: '',
                message: message.content,
                gift_id: message.gift?.id.toString() || '',
                image: null
            });
            setExistingImage(message.image || null);
        }
        setIsEditModalOpen(true);
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setFormData(prev => ({ ...prev, image: e.target.files![0] }));
        }
    };

    const removeImage = () => {
        setFormData(prev => ({ ...prev, image: null }));
        setExistingImage(null);
    };
    
    return (
        <AppLayout breadcrumbs={[{ title: 'メッセージ管理', href: '/admin/messages' }]}>
            <Head title="メッセージ管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">メッセージ管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>メッセージ一覧</CardTitle>
                        <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                            {/* <DialogTrigger asChild>
                                <Button size="sm" className="gap-1" onClick={() => resetForm()}>
                                    <Plus className="w-4 h-4" />新規登録
                                </Button>
                            </DialogTrigger> */}
                            <DialogContent className="max-w-md">
                                <DialogHeader>
                                    <DialogTitle>新規メッセージ作成</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-4">
                                    <div>
                                        <Label htmlFor="chat_id">チャットID</Label>
                                        <Input
                                            id="chat_id"
                                            value={formData.chat_id}
                                            onChange={(e) => setFormData(prev => ({ ...prev, chat_id: e.target.value }))}
                                            placeholder="チャットIDを入力"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="sender_guest_id">ゲスト</Label>
                                        <Select value={formData.sender_guest_id} onValueChange={(value) => setFormData(prev => ({ ...prev, sender_guest_id: value }))}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="ゲストを選択" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {guests.map((guest) => (
                                                    <SelectItem key={guest.id} value={guest.id.toString()}>
                                                        {guest.nickname || guest.phone}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label htmlFor="sender_cast_id">キャスト</Label>
                                        <Select value={formData.sender_cast_id} onValueChange={(value) => setFormData(prev => ({ ...prev, sender_cast_id: value }))}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="キャストを選択" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {casts.map((cast) => (
                                                    <SelectItem key={cast.id} value={cast.id.toString()}>
                                                        {cast.nickname || cast.phone}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label htmlFor="message">メッセージ</Label>
                                        <Textarea
                                            id="message"
                                            value={formData.message}
                                            onChange={(e) => setFormData(prev => ({ ...prev, message: e.target.value }))}
                                            placeholder="メッセージを入力"
                                            rows={3}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="gift_id">ギフト</Label>
                                        <Select value={formData.gift_id} onValueChange={(value) => setFormData(prev => ({ ...prev, gift_id: value }))}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="ギフトを選択" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {gifts.map((gift) => (
                                                    <SelectItem key={gift.id} value={gift.id.toString()}>
                                                        {gift.icon} {gift.name} ({gift.points}ポイント)
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label htmlFor="image">画像</Label>
                                        <div className="flex items-center gap-2">
                                            <Input
                                                id="image"
                                                type="file"
                                                accept="image/*"
                                                onChange={handleImageChange}
                                            />
                                            {formData.image && (
                                                <Button type="button" variant="outline" size="sm" onClick={removeImage}>
                                                    <X className="w-4 h-4" />
                                                </Button>
                                            )}
                                        </div>
                                        {formData.image && (
                                            <p className="text-sm text-muted-foreground">
                                                選択されたファイル: {formData.image?.name}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-2 pt-4">
                                        <Button onClick={handleCreate} disabled={isLoading} className="flex-1">
                                            {isLoading ? '作成中...' : '作成'}
                                        </Button>
                                        <Button variant="outline" onClick={() => setIsCreateModalOpen(false)} className="flex-1">
                                            キャンセル
                                        </Button>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-4">
                            <Input
                                placeholder="ゲスト・キャスト・内容で検索"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="max-w-xs"
                            />
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">表示件数</span>
                                <Select
                                    value={String(messages.per_page || 10)}
                                    onValueChange={(value) =>
                                        router.get('/admin/messages', { page: 1, per_page: Number(value) })
                                    }
                                >
                                    <SelectTrigger className="w-[100px]">
                                        <SelectValue placeholder="件数" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="10">10</SelectItem>
                                        <SelectItem value="20">20</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">ゲスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">キャスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">内容</th>
                                        <th className="px-3 py-2 text-left font-semibold">日付</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-6 text-muted-foreground">該当するデータがありません</td>
                                        </tr>
                                    ) : (
                                        filtered.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{((messages.from || 0) + idx)}</td>
                                                <td className="px-3 py-2">{item.guest}</td>
                                                <td className="px-3 py-2">{item.cast}</td>
                                                <td className="px-3 py-2">
                                                    {item.image ? (
                                                        <div className="flex items-center gap-2">
                                                            <ImageIcon className="w-4 h-4 text-blue-500" />
                                                            <span className="text-blue-600 cursor-pointer hover:underline" 
                                                                  onClick={() => window.open(item.image, '_blank')}>
                                                                {item.content}
                                                            </span>
                                                        </div>
                                                    ) : item.gift ? (
                                                        <div className="flex items-center gap-2">
                                                            <Gift className="w-4 h-4 text-purple-500" />
                                                            <div className="flex items-center gap-2">
                                                                {item.gift.icon && (
                                                                    <span className="text-2xl">
                                                                        {item.gift.icon}
                                                                    </span>
                                                                )}
                                                                <span className="text-purple-600">
                                                                    ({item.gift.points} ポイント)
                                                                </span>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        renderTextContent(item.content)
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">{item.date}</td>
                                                <td className="px-3 py-2 flex gap-2 justify-center">
                                                    <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                                                        <DialogTrigger asChild>
                                                            <Button 
                                                                size="sm" 
                                                                variant="outline"
                                                                onClick={() => openEditModal(item)}
                                                            >
                                                                <Edit className="w-4 h-4" />編集
                                                            </Button>
                                                        </DialogTrigger>
                                                        <DialogContent className="max-w-md">
                                                            <DialogHeader>
                                                                <DialogTitle>メッセージ編集</DialogTitle>
                                                            </DialogHeader>
                                                            <div className="space-y-4">
                                                                <div>
                                                                    <Label htmlFor="edit_chat_id">チャットID</Label>
                                                                    <Input
                                                                        id="edit_chat_id"
                                                                        value={formData.chat_id}
                                                                        onChange={(e) => setFormData(prev => ({ ...prev, chat_id: e.target.value }))}
                                                                        placeholder="チャットIDを入力"
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <Label htmlFor="edit_sender_guest_id">ゲスト</Label>
                                                                    <Select value={formData.sender_guest_id} onValueChange={(value) => setFormData(prev => ({ ...prev, sender_guest_id: value }))}>
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="ゲストを選択" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {guests.map((guest) => (
                                                                                <SelectItem key={guest.id} value={guest.id.toString()}>
                                                                                    {guest.nickname || guest.phone}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div>
                                                                    <Label htmlFor="edit_sender_cast_id">キャスト</Label>
                                                                    <Select value={formData.sender_cast_id} onValueChange={(value) => setFormData(prev => ({ ...prev, sender_cast_id: value }))}>
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="キャストを選択" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {casts.map((cast) => (
                                                                                <SelectItem key={cast.id} value={cast.id.toString()}>
                                                                                    {cast.nickname || cast.phone}
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div>
                                                                    <Label htmlFor="edit_message">メッセージ</Label>
                                                                    <Textarea
                                                                        id="edit_message"
                                                                        value={formData.message}
                                                                        onChange={(e) => setFormData(prev => ({ ...prev, message: e.target.value }))}
                                                                        placeholder="メッセージを入力"
                                                                        rows={3}
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <Label htmlFor="edit_gift_id">ギフト</Label>
                                                                    <Select value={formData.gift_id} onValueChange={(value) => setFormData(prev => ({ ...prev, gift_id: value }))}>
                                                                        <SelectTrigger>
                                                                            <SelectValue placeholder="ギフトを選択" />
                                                                        </SelectTrigger>
                                                                        <SelectContent>
                                                                            {gifts.map((gift) => (
                                                                                <SelectItem key={gift.id} value={gift.id.toString()}>
                                                                                    {gift.icon} {gift.name} ({gift.points}ポイント)
                                                                                </SelectItem>
                                                                            ))}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div>
                                                                    <Label htmlFor="edit_image">画像</Label>
                                                                    {existingImage && (
                                                                        <div className="mb-2">
                                                                            <p className="text-sm text-muted-foreground mb-2">現在の画像:</p>
                                                                            <img 
                                                                                src={existingImage} 
                                                                                alt="現在の画像" 
                                                                                className="max-w-xs h-auto rounded border"
                                                                                onClick={() => window.open(existingImage, '_blank')}
                                                                                style={{ cursor: 'pointer' }}
                                                                            />
                                                                        </div>
                                                                    )}
                                                                    <div className="flex items-center gap-2">
                                                                        <Input
                                                                            id="edit_image"
                                                                            type="file"
                                                                            accept="image/*"
                                                                            onChange={handleImageChange}
                                                                        />
                                                                        {(formData.image || existingImage) && (
                                                                            <Button type="button" variant="outline" size="sm" onClick={removeImage}>
                                                                                <X className="w-4 h-4" />
                                                                            </Button>
                                                                        )}
                                                                    </div>
                                                                    {formData.image && (
                                                                        <p className="text-sm text-muted-foreground">
                                                                            選択されたファイル: {formData.image?.name}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                                <div className="flex gap-2 pt-4">
                                                                    <Button onClick={handleEdit} disabled={isLoading} className="flex-1">
                                                                        {isLoading ? '更新中...' : '更新'}
                                                                    </Button>
                                                                    <Button variant="outline" onClick={() => setIsEditModalOpen(false)} className="flex-1">
                                                                        キャンセル
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </DialogContent>
                                                    </Dialog>
                                                    <AlertDialog>
                                                        <AlertDialogTrigger asChild>
                                                            <Button size="sm" variant="destructive">
                                                                <Trash2 className="w-4 h-4" />削除
                                                            </Button>
                                                        </AlertDialogTrigger>
                                                        <AlertDialogContent>
                                                            <AlertDialogHeader>
                                                                <AlertDialogTitle>削除の確認</AlertDialogTitle>
                                                                <AlertDialogDescription>
                                                                    このメッセージを削除しますか？この操作は取り消せません。
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>キャンセル</AlertDialogCancel>
                                                                <AlertDialogAction onClick={() => handleDelete(item.id)}>
                                                                    削除
                                                                </AlertDialogAction>
                                                            </AlertDialogFooter>
                                                        </AlertDialogContent>
                                                    </AlertDialog>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {/* Pagination (numbered) */}
                        {messages.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {messages.from} - {messages.to} / {messages.total} 件
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {Array.from({ length: messages.last_page }, (_, i) => i + 1).map((pageNum) => (
                                        <Button
                                            key={pageNum}
                                            size="sm"
                                            variant={pageNum === messages.current_page ? 'default' : 'outline'}
                                            disabled={pageNum === messages.current_page}
                                            onClick={() => router.get('/admin/messages', {
                                                page: pageNum,
                                                per_page: messages.per_page || 20,
                                            })}
                                        >
                                            {pageNum}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
