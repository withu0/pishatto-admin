import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus, Eye, Calendar, User, DollarSign, FileText, Clock, CheckCircle, XCircle } from 'lucide-react';
import { useState, useEffect } from 'react';

interface PaymentDetail {
    id: number;
    cast_id: number;
    cast_name: string;
    payment_id?: number;
    amount: number;
    description: string;
    status: 'pending' | 'issued' | 'completed' | 'cancelled';
    notes?: string;
    issued_at?: string;
    created_at: string;
    updated_at: string;
    issuer_name?: string;
}

interface PaymentDetailsData {
    payment_details: PaymentDetail[];
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
        pending_count: number;
        issued_count: number;
        completed_count: number;
        cancelled_count: number;
        unique_casts: number;
    };
}

interface Props {
    paymentDetails?: PaymentDetailsData;
    filters?: {
        search?: string;
        status?: string;
        cast_id?: string;
    };
}

export default function AdminPaymentDetails({ paymentDetails: initialPaymentDetails, filters: initialFilters }: Props) {
    const [paymentDetails, setPaymentDetails] = useState<PaymentDetailsData | null>(initialPaymentDetails || null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState(initialFilters?.search || '');
    const [statusFilter, setStatusFilter] = useState<string>(initialFilters?.status || 'all');
    const [castFilter, setCastFilter] = useState<string>(initialFilters?.cast_id || 'all');
    
    const fetchPaymentDetails = async (params: any = {}) => {
        setLoading(true);
        try {
            const queryParams = new URLSearchParams();
            if (params.search) queryParams.append('search', params.search);
            if (params.status && params.status !== 'all') queryParams.append('status', params.status);
            if (params.cast_id && params.cast_id !== 'all') queryParams.append('cast_id', params.cast_id);
            if (params.page) queryParams.append('page', params.page.toString());
            if (params.per_page) queryParams.append('per_page', params.per_page.toString());

            const response = await fetch(`/api/admin/payment-details?${queryParams.toString()}`);
            if (response.ok) {
                const data = await response.json();
                setPaymentDetails(data);
            } else {
                console.error('Failed to fetch payment details');
            }
        } catch (error) {
            console.error('Error fetching payment details:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!initialPaymentDetails) {
            fetchPaymentDetails();
        }
    }, []);

    const handleSearch = () => {
        fetchPaymentDetails({
            search,
            status: statusFilter,
            cast_id: castFilter
        });
    };

    const handleFilterChange = (type: 'status' | 'cast_id', value: string) => {
        if (type === 'status') {
            setStatusFilter(value);
        } else {
            setCastFilter(value);
        }
        fetchPaymentDetails({
            search,
            status: type === 'status' ? value : statusFilter,
            cast_id: type === 'cast_id' ? value : castFilter
        });
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            pending: 'bg-yellow-100 text-yellow-800',
            issued: 'bg-blue-100 text-blue-800',
            completed: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800'
        };
        return <Badge className={variants[status as keyof typeof variants]}>{status}</Badge>;
    };

    const getStatusIcon = (status: string) => {
        const icons = {
            pending: <Clock className="w-4 h-4" />,
            issued: <FileText className="w-4 h-4" />,
            completed: <CheckCircle className="w-4 h-4" />,
            cancelled: <XCircle className="w-4 h-4" />
        };
        return icons[status as keyof typeof icons] || <Clock className="w-4 h-4" />;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('ja-JP');
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const handleCreate = () => {
        router.visit('/admin/payment-details/create');
    };

    const handleView = (id: number) => {
        router.visit(`/admin/payment-details/${id}`);
    };

    const handleEdit = (id: number) => {
        router.visit(`/admin/payment-details/${id}/edit`);
    };

    const handleDelete = async (id: number) => {
        if (confirm('この支払明細を削除しますか？')) {
            try {
                const response = await fetch(`/admin/payment-details/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });
                
                if (response.ok) {
                    // Refresh the data
                    fetchPaymentDetails({
                        search,
                        status: statusFilter,
                        cast_id: castFilter
                    });
                } else {
                    console.error('Failed to delete payment detail');
                }
            } catch (error) {
                console.error('Error deleting payment detail:', error);
            }
        }
    };

    if (!paymentDetails) {
        return (
            <AppLayout breadcrumbs={[{ title: '支払明細管理', href: '/admin/payment-details' }]}>
                <Head title="支払明細管理" />
                <div className="p-6">
                    <div className="flex items-center justify-center h-64">
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                            <p className="text-muted-foreground">データを読み込み中...</p>
                        </div>
                        <div className="mb-4 flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">表示件数</span>
                            <select 
                                value={String(paymentDetails.pagination.per_page || 10)}
                                onChange={e => fetchPaymentDetails({
                                    search,
                                    status: statusFilter,
                                    cast_id: castFilter,
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
        <AppLayout breadcrumbs={[{ title: '支払明細管理', href: '/admin/payment-details' }]}>
            <Head title="支払明細管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">支払明細管理</h1>
                
                {/* Summary Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">総明細額</p>
                                    <p className="text-2xl font-bold">¥{paymentDetails.summary.total_amount.toLocaleString()}</p>
                                </div>
                                <DollarSign className="w-8 h-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">発行済み</p>
                                    <p className="text-2xl font-bold">{paymentDetails.summary.issued_count}件</p>
                                </div>
                                <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <FileText className="w-4 h-4 text-blue-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">保留中</p>
                                    <p className="text-2xl font-bold">{paymentDetails.summary.pending_count}件</p>
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
                                    <p className="text-2xl font-bold">{paymentDetails.summary.unique_casts}人</p>
                                </div>
                                <div className="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <User className="w-4 h-4 text-purple-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>支払明細一覧</CardTitle>
                        <Button size="sm" className="gap-1" onClick={handleCreate}>
                            <Plus className="w-4 h-4" />新規明細
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2 flex-wrap">
                            <Input
                                placeholder="キャスト・明細で検索"
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
                                <option value="issued">発行済み</option>
                                <option value="completed">完了</option>
                                <option value="cancelled">キャンセル</option>
                            </select>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">キャスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">金額</th>
                                        <th className="px-3 py-2 text-left font-semibold">明細</th>
                                        <th className="px-3 py-2 text-left font-semibold">ステータス</th>
                                        <th className="px-3 py-2 text-left font-semibold">発行日</th>
                                        <th className="px-3 py-2 text-left font-semibold">発行者</th>
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
                                    ) : paymentDetails.payment_details.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="text-center py-6 text-muted-foreground">該当するデータがありません</td>
                                        </tr>
                                    ) : (
                                        paymentDetails.payment_details.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{paymentDetails.pagination.from + idx}</td>
                                                <td className="px-3 py-2 font-medium">{item.cast_name}</td>
                                                <td className="px-3 py-2 font-bold">{item.amount.toLocaleString()}円</td>
                                                <td className="px-3 py-2">{item.description}</td>
                                                <td className="px-3 py-2 flex items-center gap-1">
                                                    {getStatusIcon(item.status)}
                                                    {getStatusBadge(item.status)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.issued_at ? formatDate(item.issued_at) : '-'}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {item.issuer_name || '-'}
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
                        {paymentDetails.pagination.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4">
                                <div className="text-sm text-muted-foreground">
                                    表示 {paymentDetails.pagination.from}-{paymentDetails.pagination.to} / {paymentDetails.pagination.total} 件
                                </div>
                                <div className="flex gap-2 flex-wrap">
                                    {Array.from({ length: paymentDetails.pagination.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            size="sm"
                                            variant={page === paymentDetails.pagination.current_page ? 'default' : 'outline'}
                                            disabled={loading || page === paymentDetails.pagination.current_page}
                                            onClick={() => fetchPaymentDetails({
                                                page,
                                                search,
                                                status: statusFilter,
                                                cast_id: castFilter,
                                                per_page: paymentDetails.pagination.per_page || 10
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
