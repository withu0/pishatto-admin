import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Eye, Search, RotateCw, X, CheckCircle, Calendar, Wallet, Check, XCircle } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';

interface Payout {
    id: number;
    cast_id: number;
    cast_name: string;
    type: 'scheduled' | 'instant';
    closing_month: string;
    period_start: string;
    period_end: string;
    total_points: number;
    conversion_rate: number;
    gross_amount_yen: number;
    fee_rate: number;
    fee_amount_yen: number;
    net_amount_yen: number;
    transaction_count: number;
    status: 'pending' | 'pending_approval' | 'scheduled' | 'processing' | 'paid' | 'failed' | 'cancelled';
    scheduled_payout_date: string | null;
    paid_at: string | null;
    created_at: string;
    updated_at: string;
    payment_id: number | null;
    stripe_transfer_id: string | null;
    stripe_payout_id: string | null;
    metadata: any;
}

interface Props {
    payouts: {
        data: Payout[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        status?: string;
        type?: string;
        cast_id?: string;
        date_from?: string;
        date_to?: string;
        scheduled_date_from?: string;
        scheduled_date_to?: string;
        per_page?: number;
    };
}

export default function AdminCastPayouts({ payouts, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState<string>(filters.status || 'all');
    const [typeFilter, setTypeFilter] = useState<string>(filters.type || 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/cast-payouts', {
            search: debouncedSearch,
            status: statusFilter,
            type: typeFilter,
            date_from: dateFrom,
            date_to: dateTo,
            per_page: filters.per_page || 20,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch, statusFilter, typeFilter, dateFrom, dateTo]);

    const getStatusBadge = (status: string) => {
        const variants: Record<string, string> = {
            pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            pending_approval: 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
            scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            processing: 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
            paid: 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            failed: 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
        };
        const labels: Record<string, string> = {
            pending: '保留中',
            pending_approval: '承認待ち',
            scheduled: '予定済み',
            processing: '処理中',
            paid: '支払済み',
            failed: '失敗',
            cancelled: 'キャンセル',
        };
        return (
            <Badge className={variants[status] || variants.pending}>
                {labels[status] || status}
            </Badge>
        );
    };

    const getTypeBadge = (type: string) => {
        return type === 'instant' ? (
            <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">即時</Badge>
        ) : (
            <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">定期</Badge>
        );
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('ja-JP');
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const formatCurrency = (amount: number) => {
        return `¥${amount.toLocaleString()}`;
    };

    const handleRetry = async (payoutId: number) => {
        if (!confirm('この振込を再試行しますか？')) return;

        try {
            const response = await fetch(`/admin/cast-payouts/${payoutId}/retry`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                router.reload();
            } else {
                const data = await response.json();
                alert(data.message || '再試行に失敗しました。');
            }
        } catch (error) {
            console.error('Error retrying payout:', error);
            alert('再試行に失敗しました。');
        }
    };

    const handleCancel = async (payoutId: number) => {
        if (!confirm('この振込をキャンセルしますか？')) return;

        try {
            const response = await fetch(`/admin/cast-payouts/${payoutId}/cancel`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                router.reload();
            } else {
                const data = await response.json();
                alert(data.message || 'キャンセルに失敗しました。');
            }
        } catch (error) {
            console.error('Error cancelling payout:', error);
            alert('キャンセルに失敗しました。');
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'キャスト振込管理', href: '/admin/cast-payouts' }]}>
            <Head title="キャスト振込管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">キャスト振込管理</h1>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>振込一覧 ({payouts.total}件)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-4 flex items-center gap-2 flex-wrap">
                            <div className="relative flex-1 max-w-xs">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="キャスト名・ID・振込IDで検索"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                            <Select value={statusFilter} onValueChange={setStatusFilter}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="ステータス" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">すべて</SelectItem>
                                    <SelectItem value="pending_approval">承認待ち</SelectItem>
                                    <SelectItem value="pending">保留中</SelectItem>
                                    <SelectItem value="scheduled">予定済み</SelectItem>
                                    <SelectItem value="processing">処理中</SelectItem>
                                    <SelectItem value="paid">支払済み</SelectItem>
                                    <SelectItem value="failed">失敗</SelectItem>
                                    <SelectItem value="cancelled">キャンセル</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={typeFilter} onValueChange={setTypeFilter}>
                                <SelectTrigger className="w-[120px]">
                                    <SelectValue placeholder="タイプ" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">すべて</SelectItem>
                                    <SelectItem value="scheduled">定期</SelectItem>
                                    <SelectItem value="instant">即時</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                type="date"
                                placeholder="開始日"
                                value={dateFrom}
                                onChange={e => setDateFrom(e.target.value)}
                                className="w-[150px]"
                            />
                            <Input
                                type="date"
                                placeholder="終了日"
                                value={dateTo}
                                onChange={e => setDateTo(e.target.value)}
                                className="w-[150px]"
                            />
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2 text-sm font-medium">ID</th>
                                        <th className="text-left p-2 text-sm font-medium">キャスト</th>
                                        <th className="text-left p-2 text-sm font-medium">タイプ</th>
                                        <th className="text-right p-2 text-sm font-medium">ポイント</th>
                                        <th className="text-right p-2 text-sm font-medium">総額</th>
                                        <th className="text-right p-2 text-sm font-medium">手数料</th>
                                        <th className="text-right p-2 text-sm font-medium">振込額</th>
                                        <th className="text-left p-2 text-sm font-medium">ステータス</th>
                                        <th className="text-left p-2 text-sm font-medium">予定日</th>
                                        <th className="text-left p-2 text-sm font-medium">作成日</th>
                                        <th className="text-center p-2 text-sm font-medium">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payouts.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={11} className="text-center p-8 text-muted-foreground">
                                                振込記録が見つかりませんでした。
                                            </td>
                                        </tr>
                                    ) : (
                                        payouts.data.map((payout) => (
                                            <tr key={payout.id} className="border-b hover:bg-muted/50">
                                                <td className="p-2 text-sm">{payout.id}</td>
                                                <td className="p-2 text-sm">
                                                    <a
                                                        href={`/admin/casts/${payout.cast_id}`}
                                                        className="text-primary hover:underline"
                                                    >
                                                        {payout.cast_name}
                                                    </a>
                                                </td>
                                                <td className="p-2">{getTypeBadge(payout.type)}</td>
                                                <td className="p-2 text-right text-sm">{payout.total_points.toLocaleString()}pt</td>
                                                <td className="p-2 text-right text-sm">{formatCurrency(payout.gross_amount_yen)}</td>
                                                <td className="p-2 text-right text-sm">{formatCurrency(payout.fee_amount_yen)}</td>
                                                <td className="p-2 text-right text-sm font-medium">{formatCurrency(payout.net_amount_yen)}</td>
                                                <td className="p-2">{getStatusBadge(payout.status)}</td>
                                                <td className="p-2 text-sm">{formatDate(payout.scheduled_payout_date)}</td>
                                                <td className="p-2 text-sm">{formatDate(payout.created_at)}</td>
                                                <td className="p-2">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => router.visit(`/admin/cast-payouts/${payout.id}`)}
                                                        >
                                                            <Eye className="w-4 h-4" />
                                                        </Button>
                                                        {payout.status === 'pending_approval' && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleApprove(payout.id)}
                                                                    title="承認"
                                                                    className="text-green-600 hover:text-green-700"
                                                                >
                                                                    <Check className="w-4 h-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => handleReject(payout.id)}
                                                                    title="却下"
                                                                    className="text-red-600 hover:text-red-700"
                                                                >
                                                                    <XCircle className="w-4 h-4" />
                                                                </Button>
                                                            </>
                                                        )}
                                                        {payout.status === 'failed' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleRetry(payout.id)}
                                                                title="再試行"
                                                            >
                                                                <RotateCw className="w-4 h-4" />
                                                            </Button>
                                                        )}
                                                        {(payout.status === 'pending' || payout.status === 'scheduled') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => handleCancel(payout.id)}
                                                                title="キャンセル"
                                                            >
                                                                <X className="w-4 h-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {payouts.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {payouts.from} - {payouts.to} / {payouts.total}件
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => router.get('/admin/cast-payouts', {
                                            ...filters,
                                            page: payouts.current_page - 1,
                                        })}
                                        disabled={payouts.current_page === 1}
                                    >
                                        前へ
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => router.get('/admin/cast-payouts', {
                                            ...filters,
                                            page: payouts.current_page + 1,
                                        })}
                                        disabled={payouts.current_page === payouts.last_page}
                                    >
                                        次へ
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}



