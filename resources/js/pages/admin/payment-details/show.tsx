import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Edit, Trash2, User, DollarSign, FileText, Calendar, Clock, CheckCircle, XCircle } from 'lucide-react';

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

interface Props {
    paymentDetail: PaymentDetail;
}

export default function ShowPaymentDetail({ paymentDetail }: Props) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('ja-JP');
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
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

    const handleEdit = () => {
        router.visit(`/admin/payment-details/${paymentDetail.id}/edit`);
    };

    const handleDelete = async () => {
        if (confirm('この支払明細を削除しますか？')) {
            try {
                const response = await fetch(`/admin/payment-details/${paymentDetail.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });
                
                if (response.ok) {
                    router.visit('/admin/payment-details');
                } else {
                    console.error('Failed to delete payment detail');
                }
            } catch (error) {
                console.error('Error deleting payment detail:', error);
            }
        }
    };

    const handleBack = () => {
        router.visit('/admin/payment-details');
    };

    return (
        <AppLayout breadcrumbs={[
            { title: '支払明細管理', href: '/admin/payment-details' },
            { title: '詳細', href: `/admin/payment-details/${paymentDetail.id}` }
        ]}>
            <Head title="支払明細詳細" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold">支払明細詳細</h1>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={handleBack}>
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                        <Button variant="outline" onClick={handleEdit}>
                            <Edit className="w-4 h-4 mr-2" />
                            編集
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            <Trash2 className="w-4 h-4 mr-2" />
                            削除
                        </Button>
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
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">ID</label>
                                    <p className="text-lg font-semibold">#{paymentDetail.id}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">ステータス</label>
                                    <div className="flex items-center gap-2 mt-1">
                                        {getStatusIcon(paymentDetail.status)}
                                        {getStatusBadge(paymentDetail.status)}
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">キャスト</label>
                                <div className="flex items-center gap-2 mt-1">
                                    <User className="w-4 h-4 text-muted-foreground" />
                                    <p className="text-lg font-semibold">{paymentDetail.cast_name}</p>
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">金額</label>
                                <div className="flex items-center gap-2 mt-1">
                                    <DollarSign className="w-4 h-4 text-green-600" />
                                    <p className="text-2xl font-bold text-green-600">¥{paymentDetail.amount.toLocaleString()}</p>
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">明細内容</label>
                                <p className="text-lg mt-1">{paymentDetail.description}</p>
                            </div>

                            {paymentDetail.notes && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">備考</label>
                                    <p className="text-sm mt-1 text-muted-foreground">{paymentDetail.notes}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Timestamps and Additional Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="w-5 h-5" />
                                日時情報
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {paymentDetail.issued_at && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">発行日</label>
                                    <p className="text-lg mt-1">{formatDateTime(paymentDetail.issued_at)}</p>
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">作成日</label>
                                <p className="text-lg mt-1">{formatDateTime(paymentDetail.created_at)}</p>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-muted-foreground">更新日</label>
                                <p className="text-lg mt-1">{formatDateTime(paymentDetail.updated_at)}</p>
                            </div>

                            {paymentDetail.issuer_name && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">発行者</label>
                                    <p className="text-lg mt-1">{paymentDetail.issuer_name}</p>
                                </div>
                            )}

                            {paymentDetail.payment_id && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">関連支払ID</label>
                                    <p className="text-lg mt-1">#{paymentDetail.payment_id}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Status Timeline */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>ステータス履歴</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center gap-4">
                                <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <CheckCircle className="w-4 h-4 text-green-600" />
                                </div>
                                <div>
                                    <p className="font-medium">作成</p>
                                    <p className="text-sm text-muted-foreground">{formatDateTime(paymentDetail.created_at)}</p>
                                </div>
                            </div>

                            {paymentDetail.issued_at && (
                                <div className="flex items-center gap-4">
                                    <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <FileText className="w-4 h-4 text-blue-600" />
                                    </div>
                                    <div>
                                        <p className="font-medium">発行</p>
                                        <p className="text-sm text-muted-foreground">{formatDateTime(paymentDetail.issued_at)}</p>
                                    </div>
                                </div>
                            )}

                            <div className="flex items-center gap-4">
                                <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                    <Clock className="w-4 h-4 text-gray-600" />
                                </div>
                                <div>
                                    <p className="font-medium">最終更新</p>
                                    <p className="text-sm text-muted-foreground">{formatDateTime(paymentDetail.updated_at)}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 