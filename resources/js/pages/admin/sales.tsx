import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Edit, Trash2, Plus, Eye, Calendar, User, DollarSign } from 'lucide-react';
import { useState, useEffect } from 'react';

interface Sale {
    id: number;
    guest: string;
    amount: number;
    date: string;
    payment_method?: string;
    notes?: string;
    created_at: string;
    updated_at: string;
}

interface Guest {
    id: number;
    name: string;
}

interface SalesData {
    data: Sale[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    sales: SalesData;
    filters: {
        search?: string;
    };
}

export default function AdminSales({ sales, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedSale, setSelectedSale] = useState<Sale | null>(null);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isViewDialogOpen, setIsViewDialogOpen] = useState(false);
    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [guests, setGuests] = useState<Guest[]>([]);
    const [formData, setFormData] = useState({
        guest_id: '',
        amount: '',
        payment_method: '',
        notes: ''
    });

    // Fetch guests for the create form
    useEffect(() => {
        fetch('/admin/sales/guests')
            .then(response => response.json())
            .then(data => setGuests(data))
            .catch(error => console.error('Error fetching guests:', error));
    }, []);

    const handleCreate = () => {
        setFormData({
            guest_id: '',
            amount: '',
            payment_method: '',
            notes: ''
        });
        setIsCreateDialogOpen(true);
    };

    const handleEdit = (sale: Sale) => {
        setSelectedSale(sale);
        setFormData({
            guest_id: '',
            amount: sale.amount.toString(),
            payment_method: sale.payment_method || '',
            notes: sale.notes || ''
        });
        setIsEditDialogOpen(true);
    };

    const handleView = (sale: Sale) => {
        setSelectedSale(sale);
        setIsViewDialogOpen(true);
    };

    const handleDelete = (saleId: number) => {
        router.delete(`/admin/sales/${saleId}`);
    };

    const handleSubmit = (isEdit: boolean = false) => {
        if (isEdit && selectedSale) {
            router.put(`/admin/sales/${selectedSale.id}`, formData);
            setIsEditDialogOpen(false);
        } else {
            router.post('/admin/sales', formData);
            setIsCreateDialogOpen(false);
        }

        setFormData({
            guest_id: '',
            amount: '',
            payment_method: '',
            notes: ''
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('ja-JP');
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    return (
        <AppLayout breadcrumbs={[{ title: '売上管理', href: '/admin/sales' }]}>
            <Head title="売上管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">売上管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>売上一覧</CardTitle>
                        <Button size="sm" className="gap-1" onClick={handleCreate}>
                            <Plus className="w-4 h-4" />新規登録
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="ゲストで検索"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="max-w-xs"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        router.get('/admin/sales', { search }, {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                            />
                            <Button 
                                size="sm" 
                                onClick={() => router.get('/admin/sales', { search }, {
                                    preserveState: true,
                                    preserveScroll: true,
                                })}
                            >
                                検索
                            </Button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">ゲスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">金額</th>
                                        <th className="px-3 py-2 text-left font-semibold">日付</th>
                                        <th className="px-3 py-2 text-left font-semibold">支払方法</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sales.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-6 text-muted-foreground">該当するデータがありません</td>
                                        </tr>
                                    ) : (
                                        sales.data.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{sales.from + idx}</td>
                                                <td className="px-3 py-2">{item.guest}</td>
                                                <td className="px-3 py-2">{item.amount.toLocaleString()}円</td>
                                                <td className="px-3 py-2">{formatDate(item.date)}</td>
                                                <td className="px-3 py-2">{item.payment_method || '-'}</td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Button 
                                                        size="sm" 
                                                        variant="outline"
                                                        onClick={() => handleView(item)}
                                                    >
                                                        <Eye className="w-4 h-4" />詳細
                                                    </Button>
                                                    <Button 
                                                        size="sm" 
                                                        variant="outline"
                                                        onClick={() => handleEdit(item)}
                                                    >
                                                        <Edit className="w-4 h-4" />編集
                                                    </Button>
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
                                                                    この売上記録を削除してもよろしいですか？この操作は元に戻せません。
                                                                </AlertDialogDescription>
                                                            </AlertDialogHeader>
                                                            <AlertDialogFooter>
                                                                <AlertDialogCancel>キャンセル</AlertDialogCancel>
                                                                <AlertDialogAction
                                                                    onClick={() => handleDelete(item.id)}
                                                                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                                >
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
                        
                        {/* Pagination */}
                        {sales.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4">
                                <div className="text-sm text-muted-foreground">
                                    表示 {sales.from}-{sales.to} / {sales.total} 件
                                </div>
                                <div className="flex gap-2">
                                    {sales.current_page > 1 && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.get('/admin/sales', { 
                                                page: sales.current_page - 1,
                                                search 
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            })}
                                        >
                                            前へ
                                        </Button>
                                    )}
                                    {sales.current_page < sales.last_page && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.get('/admin/sales', { 
                                                page: sales.current_page + 1,
                                                search 
                                            }, {
                                                preserveState: true,
                                                preserveScroll: true,
                                            })}
                                        >
                                            次へ
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>新規売上登録</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="guest">ゲスト *</Label>
                            <Select value={formData.guest_id} onValueChange={(value) => setFormData({...formData, guest_id: value})}>
                                <SelectTrigger>
                                    <SelectValue placeholder="ゲストを選択" />
                                </SelectTrigger>
                                <SelectContent>
                                    {guests.map((guest) => (
                                        <SelectItem key={guest.id} value={guest.id.toString()}>
                                            {guest.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="amount">金額 *</Label>
                            <Input
                                id="amount"
                                type="number"
                                value={formData.amount}
                                onChange={(e) => setFormData({...formData, amount: e.target.value})}
                                placeholder="金額を入力"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment_method">支払方法</Label>
                            <Input
                                id="payment_method"
                                value={formData.payment_method}
                                onChange={(e) => setFormData({...formData, payment_method: e.target.value})}
                                placeholder="支払方法を入力"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="notes">備考</Label>
                            <Input
                                id="notes"
                                value={formData.notes}
                                onChange={(e) => setFormData({...formData, notes: e.target.value})}
                                placeholder="備考を入力"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                            キャンセル
                        </Button>
                        <Button onClick={() => handleSubmit(false)}>
                            登録
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>売上編集</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-amount">金額 *</Label>
                            <Input
                                id="edit-amount"
                                type="number"
                                value={formData.amount}
                                onChange={(e) => setFormData({...formData, amount: e.target.value})}
                                placeholder="金額を入力"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-payment_method">支払方法</Label>
                            <Input
                                id="edit-payment_method"
                                value={formData.payment_method}
                                onChange={(e) => setFormData({...formData, payment_method: e.target.value})}
                                placeholder="支払方法を入力"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-notes">備考</Label>
                            <Input
                                id="edit-notes"
                                value={formData.notes}
                                onChange={(e) => setFormData({...formData, notes: e.target.value})}
                                placeholder="備考を入力"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            キャンセル
                        </Button>
                        <Button onClick={() => handleSubmit(true)}>
                            更新
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* View Dialog */}
            <Dialog open={isViewDialogOpen} onOpenChange={setIsViewDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>売上詳細</DialogTitle>
                    </DialogHeader>
                    {selectedSale && (
                        <div className="grid gap-4 py-4">
                            <div className="flex items-center gap-2">
                                <User className="w-4 h-4" />
                                <span className="font-semibold">ゲスト:</span>
                                <span>{selectedSale.guest}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <DollarSign className="w-4 h-4" />
                                <span className="font-semibold">金額:</span>
                                <span>{selectedSale.amount.toLocaleString()}円</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Calendar className="w-4 h-4" />
                                <span className="font-semibold">日付:</span>
                                <span>{formatDate(selectedSale.date)}</span>
                            </div>
                            {selectedSale.payment_method && (
                                <div className="flex items-center gap-2">
                                    <span className="font-semibold">支払方法:</span>
                                    <span>{selectedSale.payment_method}</span>
                                </div>
                            )}
                            {selectedSale.notes && (
                                <div className="flex items-start gap-2">
                                    <span className="font-semibold">備考:</span>
                                    <span className="text-sm text-muted-foreground">{selectedSale.notes}</span>
                                </div>
                            )}
                            <div className="pt-4 border-t">
                                <div className="text-xs text-muted-foreground">
                                    <div>作成日時: {formatDateTime(selectedSale.created_at)}</div>
                                    <div>更新日時: {formatDateTime(selectedSale.updated_at)}</div>
                                </div>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button onClick={() => setIsViewDialogOpen(false)}>
                            閉じる
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
