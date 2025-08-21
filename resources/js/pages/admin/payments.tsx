import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus, Eye, Calendar, User, DollarSign, CreditCard, Clock } from 'lucide-react';
import { useState, useEffect } from 'react';

interface Payment {
    id: number;
    cast_id: number;
    cast_name: string;
    amount: number;
    status: 'pending' | 'paid' | 'failed' | 'refunded';
    payment_method: 'card' | 'convenience_store' | 'bank_transfer' | 'linepay' | 'other';
    description?: string;
    paid_at?: string;
    created_at: string;
    updated_at: string;
    payjp_charge_id?: string;
    metadata?: any;
}

interface PaymentsData {
    payments: Payment[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    summary: {
        total_amount: number;
        paid_count: number;
        pending_count: number;
        failed_count: number;
        refunded_count: number;
        unique_casts: number;
    };
}

interface Props {
    payments?: PaymentsData;
    filters?: {
        search?: string;
        status?: string;
        payment_method?: string;
    };
}

export default function AdminPayments({ payments: initialPayments, filters: initialFilters }: Props) {
    const [payments, setPayments] = useState<PaymentsData | null>(initialPayments || null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState(initialFilters?.search || '');
    const [statusFilter, setStatusFilter] = useState<string>(initialFilters?.status || 'all');
    const [paymentMethodFilter, setPaymentMethodFilter] = useState<string>(initialFilters?.payment_method || 'all');
    
    const fetchPayments = async (params: any = {}) => {
        setLoading(true);
        try {
            const queryParams = new URLSearchParams();
            if (params.search) queryParams.append('search', params.search);
            if (params.status && params.status !== 'all') queryParams.append('status', params.status);
            if (params.payment_method && params.payment_method !== 'all') queryParams.append('payment_method', params.payment_method);
            if (params.page) queryParams.append('page', params.page.toString());
            if (params.per_page) queryParams.append('per_page', params.per_page.toString());

            const response = await fetch(`/api/admin/payments/cast?${queryParams.toString()}`);
            if (response.ok) {
                const data = await response.json();
                setPayments(data);
            } else {
                console.error('Failed to fetch payments');
            }
        } catch (error) {
            console.error('Error fetching payments:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!initialPayments) {
            fetchPayments();
        }
    }, []);

    const handleSearch = () => {
        fetchPayments({
            search,
            status: statusFilter,
            payment_method: paymentMethodFilter
        });
    };

    const handleFilterChange = (type: 'status' | 'payment_method', value: string) => {
        if (type === 'status') {
            setStatusFilter(value);
        } else {
            setPaymentMethodFilter(value);
        }
        fetchPayments({
            search,
            status: type === 'status' ? value : statusFilter,
            payment_method: type === 'payment_method' ? value : paymentMethodFilter
        });
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            pending: 'bg-yellow-100 text-yellow-800',
            paid: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
            refunded: 'bg-gray-100 text-gray-800'
        };
        return <Badge className={variants[status as keyof typeof variants]}>{status}</Badge>;
    };

    const getPaymentMethodIcon = (method: string) => {
        const icons = {
            card: <CreditCard className="w-4 h-4" />,
            bank_transfer: <DollarSign className="w-4 h-4" />,
            linepay: <DollarSign className="w-4 h-4" />,
            convenience_store: <DollarSign className="w-4 h-4" />,
            other: <DollarSign className="w-4 h-4" />
        };
        return icons[method as keyof typeof icons] || <DollarSign className="w-4 h-4" />;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('ja-JP');
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const handleView = (id: number) => {
        // For now, just show an alert. In a real app, you'd navigate to a detail page
        alert(`Payment ${id} details would be shown here`);
    };

    const handleEdit = (id: number) => {
        // For now, just show an alert. In a real app, you'd navigate to an edit page
        alert(`Payment ${id} edit form would be shown here`);
    };

    const handleDelete = async (id: number) => {
        if (confirm('この支払を削除しますか？')) {
            try {
                const response = await fetch(`/api/admin/payments/cast/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });
                
                if (response.ok) {
                    // Refresh the data
                    fetchPayments({
                        search,
                        status: statusFilter,
                        payment_method: paymentMethodFilter
                    });
                } else {
                    console.error('Failed to delete payment');
                }
            } catch (error) {
                console.error('Error deleting payment:', error);
            }
        }
    };

    if (!payments) {
        return (
            <AppLayout breadcrumbs={[{ title: '支払管理', href: '/admin/payments' }]}>
                <Head title="支払管理" />
                <div className="p-6">
                    <div className="flex items-center justify-center h-64">
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                            <p className="text-muted-foreground">データを読み込み中...</p>
                        </div>
                        <div className="mb-4 flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">表示件数</span>
                            <select 
                                value={String(payments.pagination.per_page || 10)}
                                onChange={e => fetchPayments({
                                    search,
                                    status: statusFilter,
                                    payment_method: paymentMethodFilter,
                                    page: 1,
                                    per_page: Number(e.target.value)
                                })}
                                className="px-3 py-2 border rounded-md text-sm"
                                disabled={loading}
                            >
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={[{ title: '支払管理', href: '/admin/payments' }]}>
            <Head title="支払管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">支払管理</h1>
                
                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">総支払額</p>
                                    <p className="text-2xl font-bold">¥{payments.summary.total_amount.toLocaleString()}</p>
                                </div>
                                <DollarSign className="w-8 h-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">支払済み</p>
                                    <p className="text-2xl font-bold">{payments.summary.paid_count}件</p>
                                </div>
                                <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <DollarSign className="w-4 h-4 text-green-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">保留中</p>
                                    <p className="text-2xl font-bold">{payments.summary.pending_count}件</p>
                                </div>
                                <div className="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <Clock className="w-4 h-4 text-yellow-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">キャスト数</p>
                                    <p className="text-2xl font-bold">{payments.summary.unique_casts}人</p>
                                </div>
                                <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <User className="w-4 h-4 text-blue-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>支払一覧</CardTitle>
                        <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規支払</Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2 flex-wrap">
                            <Input
                                placeholder="キャストで検索"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="max-w-xs"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        handleSearch();
                                    }
                                }}
                            />
                            <Button 
                                size="sm" 
                                onClick={handleSearch}
                                disabled={loading}
                            >
                                {loading ? '検索中...' : '検索'}
                            </Button>
                            <select 
                                value={statusFilter}
                                onChange={e => handleFilterChange('status', e.target.value)}
                                className="px-3 py-2 border rounded-md text-sm"
                                disabled={loading}
                            >
                                <option value="all">全ステータス</option>
                                <option value="pending">保留中</option>
                                <option value="paid">支払済み</option>
                                <option value="failed">失敗</option>
                                <option value="refunded">返金済み</option>
                            </select>
                            <select 
                                value={paymentMethodFilter}
                                onChange={e => handleFilterChange('payment_method', e.target.value)}
                                className="px-3 py-2 border rounded-md text-sm"
                                disabled={loading}
                            >
                                <option value="all">全支払方法</option>
                                <option value="card">カード</option>
                                <option value="bank_transfer">銀行振込</option>
                                <option value="linepay">LINE Pay</option>
                                <option value="convenience_store">コンビニ</option>
                                <option value="other">その他</option>
                            </select>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">キャスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">金額</th>
                                        <th className="px-3 py-2 text-left font-semibold">ステータス</th>
                                        <th className="px-3 py-2 text-left font-semibold">支払方法</th>
                                        <th className="px-3 py-2 text-left font-semibold">支払日</th>
                                        <th className="px-3 py-2 text-left font-semibold">説明</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {loading ? (
                                        <tr>
                                            <td colSpan={8} className="text-center py-6">
                                                <div className="flex items-center justify-center">
                                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary mr-2"></div>
                                                    読み込み中...
                                                </div>
                                            </td>
                                        </tr>
                                    ) : payments.payments.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="text-center py-6 text-muted-foreground">該当するデータがありません</td>
                                        </tr>
                                    ) : (
                                        payments.payments.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{payments.pagination.from + idx}</td>
                                                <td className="px-3 py-2 font-medium">{item.cast_name}</td>
                                                <td className="px-3 py-2 font-bold">{item.amount.toLocaleString()}円</td>
                                                <td className="px-3 py-2">{getStatusBadge(item.status)}</td>
                                                <td className="px-3 py-2 flex items-center gap-1">
                                                    {getPaymentMethodIcon(item.payment_method)}
                                                    <span className="capitalize">{item.payment_method.replace('_', ' ')}</span>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.paid_at ? formatDate(item.paid_at) : '-'}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {item.description || '-'}
                                                </td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Button size="sm" variant="outline" onClick={() => handleView(item.id)}>
                                                        <Eye className="w-4 h-4" />詳細
                                                    </Button>
                                                    <Button size="sm" variant="outline" onClick={() => handleEdit(item.id)}>
                                                        <Edit className="w-4 h-4" />編集
                                                    </Button>
                                                    <Button size="sm" variant="destructive" onClick={() => handleDelete(item.id)}>
                                                        <Trash2 className="w-4 h-4" />削除
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        
                        {/* Pagination (numbered) */}
                        {payments.pagination.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4">
                                <div className="text-sm text-muted-foreground">
                                    表示 {payments.pagination.from}-{payments.pagination.to} / {payments.pagination.total} 件
                                </div>
                                <div className="flex gap-2 flex-wrap">
                                    {Array.from({ length: payments.pagination.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            size="sm"
                                            variant={page === payments.pagination.current_page ? 'default' : 'outline'}
                                            disabled={loading || page === payments.pagination.current_page}
                                            onClick={() => fetchPayments({
                                                page,
                                                search,
                                                status: statusFilter,
                                                payment_method: paymentMethodFilter,
                                                per_page: payments.pagination.per_page || 10
                                            })}
                                        >
                                            {page}
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
