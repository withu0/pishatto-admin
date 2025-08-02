import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Save, X } from 'lucide-react';
import { useState } from 'react';

interface Cast {
    id: number;
    name: string;
    nickname?: string;
}

interface PaymentDetail {
    id: number;
    cast_id: number;
    payment_id?: number;
    amount: number;
    description: string;
    status: 'pending' | 'issued' | 'completed' | 'cancelled';
    notes?: string;
    issued_at?: string;
}

interface Props {
    paymentDetail: PaymentDetail;
    casts: Cast[];
}

export default function EditPaymentDetail({ paymentDetail, casts }: Props) {
    const [loading, setLoading] = useState(false);
    
    const { data, setData, put, processing, errors } = useForm({
        cast_id: paymentDetail.cast_id.toString(),
        amount: paymentDetail.amount.toString(),
        description: paymentDetail.description,
        notes: paymentDetail.notes || '',
        status: paymentDetail.status,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        
        put(`/admin/payment-details/${paymentDetail.id}`, {
            onSuccess: () => {
                setLoading(false);
            },
            onError: () => {
                setLoading(false);
            },
        });
    };

    const handleCancel = () => {
        router.visit('/admin/payment-details');
    };

    return (
        <AppLayout breadcrumbs={[
            { title: '支払明細管理', href: '/admin/payment-details' },
            { title: '編集', href: `/admin/payment-details/${paymentDetail.id}/edit` }
        ]}>
            <Head title="支払明細編集" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-2xl font-bold">支払明細編集</h1>
                    <Button variant="outline" onClick={handleCancel}>
                        <ArrowLeft className="w-4 h-4 mr-2" />
                        戻る
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>支払明細情報</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="cast_id">キャスト *</Label>
                                    <Select
                                        value={data.cast_id}
                                        onValueChange={(value) => setData('cast_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="キャストを選択してください" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {casts.map((cast) => (
                                                <SelectItem key={cast.id} value={cast.id.toString()}>
                                                    {cast.name} {cast.nickname && `(${cast.nickname})`}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.cast_id && (
                                        <p className="text-sm text-red-600">{errors.cast_id}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="amount">金額 *</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        placeholder="0"
                                        min="1"
                                    />
                                    {errors.amount && (
                                        <p className="text-sm text-red-600">{errors.amount}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">明細内容 *</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="例: 6月分給与、ボーナス等"
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-600">{errors.description}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">備考</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="追加の備考があれば入力してください"
                                    rows={3}
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600">{errors.notes}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="status">ステータス *</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) => setData('status', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pending">保留中</SelectItem>
                                        <SelectItem value="issued">発行済み</SelectItem>
                                        <SelectItem value="completed">完了</SelectItem>
                                        <SelectItem value="cancelled">キャンセル</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && (
                                    <p className="text-sm text-red-600">{errors.status}</p>
                                )}
                            </div>

                            <div className="flex justify-end gap-4 pt-6">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleCancel}
                                    disabled={loading}
                                >
                                    <X className="w-4 h-4 mr-2" />
                                    キャンセル
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={loading || processing}
                                >
                                    <Save className="w-4 h-4 mr-2" />
                                    {loading ? '更新中...' : '更新'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 