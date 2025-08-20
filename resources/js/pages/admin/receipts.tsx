import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus, Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';

interface Receipt {
    id: number;
    receipt_number: string;
    user_name: string;
    user_type: 'guest' | 'cast';
    recipient_name: string;
    amount: number;
    total_amount: number;
    purpose: string;
    status: 'draft' | 'issued' | 'cancelled';
    issued_at: string;
    created_at: string;
}

interface ReceiptFormData {
    user_type: 'guest' | 'cast';
    user_id: number;
    payment_id?: number;
    recipient_name: string;
    amount: number;
    purpose: string;
    company_name?: string;
    company_address?: string;
    company_phone?: string;
    registration_number?: string;
}

export default function AdminReceipts({ receipts: initialReceipts }: { receipts: Receipt[] }) {
    const [receipts, setReceipts] = useState<Receipt[]>(initialReceipts || []);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [selectedReceipt, setSelectedReceipt] = useState<Receipt | null>(null);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [formData, setFormData] = useState<ReceiptFormData>({
        user_type: 'guest',
        user_id: 0,
        recipient_name: '',
        amount: 0,
        purpose: '',
    });

    const formatYen = (value: number | string) => {
        const num = typeof value === 'number' ? value : parseFloat(String(value));
        if (isNaN(num)) return '￥0';
        try {
            return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 }).format(num);
        } catch {
            return `￥${Number(num || 0).toLocaleString('ja-JP')}`;
        }
    };

    const filtered = receipts.filter(
        (r) => r.user_name.toLowerCase().includes(search.toLowerCase()) ||
               r.recipient_name.toLowerCase().includes(search.toLowerCase()) ||
               r.receipt_number.toLowerCase().includes(search.toLowerCase())
    );

    const fetchReceipts = async () => {
        setLoading(true);
        try {
            const response = await fetch('/api/admin/receipts');
            const data = await response.json();
            if (data.receipts) {
                setReceipts(data.receipts);
            }
        } catch (error) {
            console.error('Failed to fetch receipts:', error);
            toast.error('領収書の取得に失敗しました');
        } finally {
            setLoading(false);
        }
    };

    const handleView = async (receipt: Receipt) => {
        try {
            const response = await fetch(`/api/admin/receipts/${receipt.id}`);
            const data = await response.json();
            if (data.success) {
                setSelectedReceipt(data.receipt);
                setIsViewModalOpen(true);
            }
        } catch (error) {
            console.error('Failed to fetch receipt details:', error);
            toast.error('領収書の詳細取得に失敗しました');
        }
    };

    const handleEdit = (receipt: Receipt) => {
        setSelectedReceipt(receipt);
        setFormData({
            user_type: receipt.user_type,
            user_id: 0, // This would need to be fetched from the receipt details
            recipient_name: receipt.recipient_name,
            amount: receipt.amount,
            purpose: receipt.purpose,
        });
        setIsEditModalOpen(true);
    };

    const handleDelete = async (receipt: Receipt) => {
        if (!confirm('この領収書を削除しますか？')) return;

        try {
            const response = await fetch(`/api/admin/receipts/${receipt.id}`, {
                method: 'DELETE',
            });
            const data = await response.json();
            
            if (data.success) {
                toast.success('領収書が削除されました');
                fetchReceipts();
            } else {
                toast.error(data.error || '削除に失敗しました');
            }
        } catch (error) {
            console.error('Failed to delete receipt:', error);
            toast.error('削除に失敗しました');
        }
    };

    const handleCreate = async () => {
        try {
            const response = await fetch('/api/admin/receipts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(formData),
            });
            const data = await response.json();
            
            if (data.success) {
                toast.success('領収書が作成されました');
                setIsCreateModalOpen(false);
                setFormData({
                    user_type: 'guest',
                    user_id: 0,
                    recipient_name: '',
                    amount: 0,
                    purpose: '',
                });
                fetchReceipts();
            } else {
                toast.error(data.error || '作成に失敗しました');
            }
        } catch (error) {
            console.error('Failed to create receipt:', error);
            toast.error('作成に失敗しました');
        }
    };

    const handleUpdate = async () => {
        if (!selectedReceipt) return;

        try {
            const response = await fetch(`/api/admin/receipts/${selectedReceipt.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(formData),
            });
            const data = await response.json();
            
            if (data.success) {
                toast.success('領収書が更新されました');
                setIsEditModalOpen(false);
                fetchReceipts();
            } else {
                toast.error(data.error || '更新に失敗しました');
            }
        } catch (error) {
            console.error('Failed to update receipt:', error);
            toast.error('更新に失敗しました');
        }
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            draft: { label: '下書き', variant: 'secondary' as const },
            issued: { label: '発行済み', variant: 'default' as const },
            cancelled: { label: 'キャンセル', variant: 'destructive' as const },
        };
        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.draft;
        return <Badge variant={config.variant}>{config.label}</Badge>;
    };

    return (
        <AppLayout breadcrumbs={[{ title: '領収書管理', href: '/admin/receipts' }]}>
            <Head title="領収書管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">領収書管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>領収書一覧</CardTitle>
                        <Button 
                            size="sm" 
                            className="gap-1"
                            onClick={() => setIsCreateModalOpen(true)}
                        >
                            <Plus className="w-4 h-4" />新規登録
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <div className="relative flex-1 max-w-xs">
                                <Search className="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                <Input
                                    placeholder="検索..."
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">領収書番号</th>
                                        <th className="px-3 py-2 text-left font-semibold">宛名</th>
                                        <th className="px-3 py-2 text-left font-semibold">ユーザー</th>
                                        <th className="px-3 py-2 text-left font-semibold">金額</th>
                                        <th className="px-3 py-2 text-left font-semibold">但し書き</th>
                                        <th className="px-3 py-2 text-left font-semibold">ステータス</th>
                                        <th className="px-3 py-2 text-left font-semibold">発行日</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {loading ? (
                                        <tr>
                                            <td colSpan={9} className="text-center py-6 text-muted-foreground">読み込み中...</td>
                                        </tr>
                                    ) : filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="text-center py-6 text-muted-foreground">該当するデータがありません</td>
                                        </tr>
                                    ) : (
                                        filtered.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2 font-mono text-xs">{item.receipt_number}</td>
                                                <td className="px-3 py-2">{item.recipient_name}</td>
                                                <td className="px-3 py-2">
                                                    <div>
                                                        <div>{item.user_name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {item.user_type === 'guest' ? 'ゲスト' : 'キャスト'}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">{formatYen(item.total_amount)}</td>
                                                <td className="px-3 py-2">{item.purpose}</td>
                                                <td className="px-3 py-2">{getStatusBadge(item.status)}</td>
                                                <td className="px-3 py-2">{item.issued_at}</td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Button 
                                                        size="sm" 
                                                        variant="outline"
                                                        onClick={() => handleView(item)}
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
                                                        onClick={() => handleDelete(item)}
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

            {/* View Modal */}
            <Dialog open={isViewModalOpen} onOpenChange={setIsViewModalOpen}>
            <DialogContent className="max-h-[90vh] overflow-y-auto" style={{ width: '1100px', maxWidth: '95vw' }}>
                    <DialogHeader>
                        <DialogTitle>領収書プレビュー</DialogTitle>
                    </DialogHeader>
                    {selectedReceipt && (
                        <div className="bg-white border-2 border-gray-300 rounded-lg p-8 shadow-lg w-[1050px] max-w-full mx-auto">
                            {/* Receipt Header */}
                            <div className="text-center mb-6">
                                <h1 className="text-2xl font-bold text-gray-800 mb-4">領収書</h1>
                                <div className="text-right text-sm text-gray-600">
                                    <div>No. {selectedReceipt.receipt_number}</div>
                                    <div>{selectedReceipt.issued_at}</div>
                                </div>
                            </div>

                            {/* Recipient */}
                            <div className="mb-6">
                                <div className="text-lg text-gray-800 mb-2">{selectedReceipt.recipient_name} 様</div>
                                <div className="border-b border-gray-300 h-8"></div>
                            </div>

                            {/* Total Amount */
                            }
                            <div className="text-center mb-6">
                                <div className="border-2 border-gray-800 p-4 mx-8">
                                    <div className="text-3xl font-bold text-gray-800">
                                        {formatYen(selectedReceipt.total_amount)}
                                    </div>
                                </div>
                            </div>

                            {/* Purpose */}
                            <div className="text-center mb-6">
                                <div className="text-sm text-gray-700">
                                    但し {selectedReceipt.purpose} として
                                </div>
                            </div>

                            {/* Confirmation */}
                            <div className="text-center mb-8">
                                <div className="text-sm text-gray-700">
                                    上記正に、領収致しました。
                                </div>
                            </div>

                            {/* Breakdown and Company Info - Horizontal Layout */}
                            <div className="flex justify-between items-end">
                                {/* Left Section - Breakdown */}
                                <div className="space-y-3">
                                    <div className="border border-dashed border-gray-400 p-3 text-center">
                                        <div className="text-sm text-gray-600">電子領収書につき印紙不要</div>
                                    </div>
                                    <div className="text-sm text-gray-700">
                                        <div className="font-bold mb-2">内訳</div>
                                        <div>税抜き金額 {formatYen(selectedReceipt.amount)}</div>
                                        <div>消費税額 {formatYen((selectedReceipt.total_amount - selectedReceipt.amount) || 0)}</div>
                                        <div>消費税率 {selectedReceipt.total_amount > selectedReceipt.amount ? '10' : '0'}%</div>
                                    </div>
                                </div>

                                {/* Right Section - Company Info */}
                                <div className="text-right space-y-2">
                                    <div className="inline-flex items-center justify-center border-[3px] border-red-600 rounded-full text-red-600 w-24 h-24 text-center ml-auto">
                                        <div>
                                            <div className="text-xs leading-tight">株式会社</div>
                                            <div className="text-sm font-bold leading-tight">Pishatto</div>
                                            <div className="text-xs leading-tight">印</div>
                                        </div>
                                    </div>
                                    <div className="text-sm text-gray-700">
                                        <div className="font-bold">株式会社Pishatto</div>
                                        <div>〒106-0032</div>
                                        <div>東京都港区六本木4丁目8-7</div>
                                        <div>六本木三河台ビル</div>
                                    </div>
                                </div>
                            </div>

                            {/* Additional Admin Info */}
                            <div className="mt-6 pt-4 border-t border-gray-200">
                                <div className="grid grid-cols-2 gap-4 text-sm text-gray-600">
                                    <div>
                                        <span className="font-semibold">発行者:</span> {selectedReceipt.user_name}
                                        <span className="ml-2">({selectedReceipt.user_type === 'guest' ? 'ゲスト' : 'キャスト'})</span>
                                    </div>
                                    <div>
                                        <span className="font-semibold">ステータス:</span> {getStatusBadge(selectedReceipt.status)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Create Modal */}
            <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>新規領収書作成</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="user_type">ユーザータイプ</Label>
                                <Select 
                                    value={formData.user_type} 
                                    onValueChange={(value: 'guest' | 'cast') => setFormData({...formData, user_type: value})}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="guest">ゲスト</SelectItem>
                                        <SelectItem value="cast">キャスト</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="user_id">ユーザーID</Label>
                                <Input
                                    id="user_id"
                                    type="number"
                                    value={formData.user_id}
                                    onChange={e => setFormData({...formData, user_id: parseInt(e.target.value)})}
                                />
                            </div>
                        </div>
                        <div>
                            <Label htmlFor="recipient_name">宛名</Label>
                            <Input
                                id="recipient_name"
                                value={formData.recipient_name}
                                onChange={e => setFormData({...formData, recipient_name: e.target.value})}
                            />
                        </div>
                        <div>
                            <Label htmlFor="amount">金額</Label>
                            <Input
                                id="amount"
                                type="number"
                                value={formData.amount}
                                onChange={e => setFormData({...formData, amount: parseInt(e.target.value)})}
                            />
                        </div>
                        <div>
                            <Label htmlFor="purpose">但し書き</Label>
                            <Textarea
                                id="purpose"
                                value={formData.purpose}
                                onChange={e => setFormData({...formData, purpose: e.target.value})}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" onClick={() => setIsCreateModalOpen(false)}>
                                キャンセル
                            </Button>
                            <Button onClick={handleCreate}>
                                作成
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>領収書編集</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="edit_recipient_name">宛名</Label>
                            <Input
                                id="edit_recipient_name"
                                value={formData.recipient_name}
                                onChange={e => setFormData({...formData, recipient_name: e.target.value})}
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit_amount">金額</Label>
                            <Input
                                id="edit_amount"
                                type="number"
                                value={formData.amount}
                                onChange={e => setFormData({...formData, amount: parseInt(e.target.value)})}
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit_purpose">但し書き</Label>
                            <Textarea
                                id="edit_purpose"
                                value={formData.purpose}
                                onChange={e => setFormData({...formData, purpose: e.target.value})}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" onClick={() => setIsEditModalOpen(false)}>
                                キャンセル
                            </Button>
                            <Button onClick={handleUpdate}>
                                更新
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
