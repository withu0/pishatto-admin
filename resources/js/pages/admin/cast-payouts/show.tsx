import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { ArrowLeft, RotateCw, X, CheckCircle, Calendar, Wallet, User, DollarSign, FileText, AlertCircle, Check, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Cast {
    id: number;
    nickname: string | null;
    phone: string | null;
    line_id: string | null;
}

interface Payment {
    id: number;
    status: string;
    stripe_transfer_id: string | null;
    stripe_payout_id: string | null;
    stripe_connect_account_id: string | null;
    metadata: any;
    created_at: string;
}

interface PointTransaction {
    id: number;
    type: string;
    amount: number;
    description: string | null;
    created_at: string;
}

interface Payout {
    id: number;
    cast_id: number;
    cast: Cast | null;
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
    payment: Payment | null;
    point_transactions: PointTransaction[];
    metadata: any;
}

interface Props {
    payout: Payout;
}

export default function AdminCastPayoutShow({ payout }: Props) {
    const [note, setNote] = useState('');
    const [actionLoading, setActionLoading] = useState<string | null>(null);

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
            <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">即時振込</Badge>
        ) : (
            <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">定期振込</Badge>
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

    const handleRetry = async () => {
        if (!confirm('この振込を再試行しますか？')) return;

        setActionLoading('retry');
        try {
            const response = await fetch(`/admin/cast-payouts/${payout.id}/retry`, {
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
        } finally {
            setActionLoading(null);
        }
    };

    const handleCancel = async () => {
        if (!confirm('この振込をキャンセルしますか？')) return;

        setActionLoading('cancel');
        try {
            const response = await fetch(`/admin/cast-payouts/${payout.id}/cancel`, {
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
        } finally {
            setActionLoading(null);
        }
    };

    const handleMarkPaid = async () => {
        if (!confirm('この振込を支払済みとしてマークしますか？')) return;

        setActionLoading('mark-paid');
        try {
            const response = await fetch(`/admin/cast-payouts/${payout.id}/mark-paid`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ note }),
            });

            if (response.ok) {
                router.reload();
            } else {
                const data = await response.json();
                alert(data.message || 'マークに失敗しました。');
            }
        } catch (error) {
            console.error('Error marking payout as paid:', error);
            alert('マークに失敗しました。');
        } finally {
            setActionLoading(null);
        }
    };

    const getCastDisplayName = () => {
        if (!payout.cast) return `キャスト${payout.cast_id}`;
        return payout.cast.nickname || payout.cast.phone || `キャスト${payout.cast_id}`;
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'キャスト振込管理', href: '/admin/cast-payouts' },
            { title: `振込 #${payout.id}`, href: `/admin/cast-payouts/${payout.id}` }
        ]}>
            <Head title={`振込 #${payout.id} - キャスト振込管理`} />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit('/admin/cast-payouts')}
                        >
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                        <h1 className="text-2xl font-bold">振込詳細 #{payout.id}</h1>
                    </div>
                    <div className="flex gap-2">
                        {payout.status === 'pending_approval' && (
                            <>
                                <Button
                                    variant="default"
                                    onClick={handleApprove}
                                    disabled={actionLoading !== null}
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    <Check className="w-4 h-4 mr-2" />
                                    {actionLoading === 'approve' ? '承認中...' : '承認'}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={handleReject}
                                    disabled={actionLoading !== null}
                                    className="border-red-600 text-red-600 hover:bg-red-50"
                                >
                                    <XCircle className="w-4 h-4 mr-2" />
                                    {actionLoading === 'reject' ? '却下中...' : '却下'}
                                </Button>
                            </>
                        )}
                        {payout.status === 'failed' && (
                            <Button
                                variant="outline"
                                onClick={handleRetry}
                                disabled={actionLoading !== null}
                            >
                                <RotateCw className="w-4 h-4 mr-2" />
                                {actionLoading === 'retry' ? '再試行中...' : '再試行'}
                            </Button>
                        )}
                        {(payout.status === 'pending' || payout.status === 'scheduled') && (
                            <Button
                                variant="outline"
                                onClick={handleCancel}
                                disabled={actionLoading !== null}
                            >
                                <X className="w-4 h-4 mr-2" />
                                {actionLoading === 'cancel' ? 'キャンセル中...' : 'キャンセル'}
                            </Button>
                        )}
                        {payout.status !== 'paid' && payout.status !== 'cancelled' && payout.status !== 'pending_approval' && (
                            <Button
                                variant="outline"
                                onClick={handleMarkPaid}
                                disabled={actionLoading !== null}
                            >
                                <CheckCircle className="w-4 h-4 mr-2" />
                                {actionLoading === 'mark-paid' ? '処理中...' : '支払済みマーク'}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="w-5 h-5" />
                                基本情報
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">振込ID</span>
                                <span className="font-medium">#{payout.id}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">キャスト</span>
                                <a
                                    href={`/admin/casts/${payout.cast_id}`}
                                    className="text-primary hover:underline font-medium"
                                >
                                    {getCastDisplayName()}
                                </a>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">タイプ</span>
                                {getTypeBadge(payout.type)}
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">ステータス</span>
                                {getStatusBadge(payout.status)}
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">締め月</span>
                                <span>{payout.closing_month}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">期間</span>
                                <span>{formatDate(payout.period_start)} ～ {formatDate(payout.period_end)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">予定振込日</span>
                                <span>{formatDate(payout.scheduled_payout_date)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">支払日</span>
                                <span>{formatDate(payout.paid_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">作成日時</span>
                                <span>{formatDateTime(payout.created_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">更新日時</span>
                                <span>{formatDateTime(payout.updated_at)}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Amount Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DollarSign className="w-5 h-5" />
                                金額情報
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">総ポイント</span>
                                <span className="font-medium">{payout.total_points.toLocaleString()} pt</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">換算レート</span>
                                <span>{payout.conversion_rate} 円/pt</span>
                            </div>
                            <div className="flex justify-between border-t pt-2">
                                <span className="text-muted-foreground">総額（税込）</span>
                                <span className="font-medium">{formatCurrency(payout.gross_amount_yen)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">手数料率</span>
                                <span>{(payout.fee_rate * 100).toFixed(2)}%</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">手数料</span>
                                <span>{formatCurrency(payout.fee_amount_yen)}</span>
                            </div>
                            <div className="flex justify-between border-t pt-2">
                                <span className="font-semibold">振込額</span>
                                <span className="font-bold text-lg">{formatCurrency(payout.net_amount_yen)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">取引数</span>
                                <span>{payout.transaction_count} 件</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Stripe Information */}
                    {payout.payment && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Wallet className="w-5 h-5" />
                                    Stripe情報
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">支払ID</span>
                                    <span className="font-medium">#{payout.payment.id}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">支払ステータス</span>
                                    <Badge>{payout.payment.status}</Badge>
                                </div>
                                {payout.payment.stripe_transfer_id && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Transfer ID</span>
                                        <span className="font-mono text-sm">{payout.payment.stripe_transfer_id}</span>
                                    </div>
                                )}
                                {payout.payment.stripe_payout_id && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Payout ID</span>
                                        <span className="font-mono text-sm">{payout.payment.stripe_payout_id}</span>
                                    </div>
                                )}
                                {payout.payment.stripe_connect_account_id && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Connect Account</span>
                                        <span className="font-mono text-sm">{payout.payment.stripe_connect_account_id}</span>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">作成日時</span>
                                    <span>{formatDateTime(payout.payment.created_at)}</span>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Point Transactions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="w-5 h-5" />
                                ポイント取引 ({payout.point_transactions.length}件)
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {payout.point_transactions.length === 0 ? (
                                <p className="text-muted-foreground text-sm">取引記録がありません。</p>
                            ) : (
                                <div className="space-y-2 max-h-96 overflow-y-auto">
                                    {payout.point_transactions.map((transaction) => (
                                        <div key={transaction.id} className="flex justify-between items-start p-2 border rounded">
                                            <div className="flex-1">
                                                <div className="text-sm font-medium">#{transaction.id}</div>
                                                <div className="text-xs text-muted-foreground">{transaction.type}</div>
                                                {transaction.description && (
                                                    <div className="text-xs text-muted-foreground mt-1">{transaction.description}</div>
                                                )}
                                                <div className="text-xs text-muted-foreground mt-1">
                                                    {formatDateTime(transaction.created_at)}
                                                </div>
                                            </div>
                                            <div className="text-sm font-medium ml-4">
                                                +{transaction.amount.toLocaleString()} pt
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Metadata & Notes */}
                    {payout.metadata && Object.keys(payout.metadata).length > 0 && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <AlertCircle className="w-5 h-5" />
                                    メタデータ・備考
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre className="bg-muted p-4 rounded text-sm overflow-x-auto">
                                    {JSON.stringify(payout.metadata, null, 2)}
                                </pre>
                            </CardContent>
                        </Card>
                    )}

                    {/* Mark as Paid Form */}
                    {payout.status !== 'paid' && payout.status !== 'cancelled' && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>支払済みマーク（手動）</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Textarea
                                    placeholder="備考（任意）"
                                    value={note}
                                    onChange={e => setNote(e.target.value)}
                                    rows={3}
                                />
                                <Button
                                    onClick={handleMarkPaid}
                                    disabled={actionLoading !== null}
                                >
                                    <CheckCircle className="w-4 h-4 mr-2" />
                                    {actionLoading === 'mark-paid' ? '処理中...' : '支払済みとしてマーク'}
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}



