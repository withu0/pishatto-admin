import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus } from 'lucide-react';
import { useState } from 'react';

const mockPaymentDetails = [
    { id: 1, cast: '高橋 美咲', amount: 4000, detail: '6月分', issued: '2024-07-02' },
    { id: 2, cast: '田中 直樹', amount: 2500, detail: '6月分', issued: '2024-06-20' },
];

export default function AdminPaymentDetails() {
    const [search, setSearch] = useState('');
    const filtered = mockPaymentDetails.filter(
        (p) => p.cast.includes(search) || p.detail.includes(search)
    );
    return (
        <AppLayout breadcrumbs={[{ title: '支払明細管理', href: '/admin/payment-details' }]}>
            <Head title="支払明細管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">支払明細管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>支払明細一覧</CardTitle>
                        <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="キャスト・明細で検索"
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
                                        <th className="px-3 py-2 text-left font-semibold">キャスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">金額</th>
                                        <th className="px-3 py-2 text-left font-semibold">明細</th>
                                        <th className="px-3 py-2 text-left font-semibold">発行日</th>
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
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2">{item.cast}</td>
                                                <td className="px-3 py-2">{item.amount.toLocaleString()}円</td>
                                                <td className="px-3 py-2">{item.detail}</td>
                                                <td className="px-3 py-2">{item.issued}</td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Button size="sm" variant="outline"><Edit className="w-4 h-4" />編集</Button>
                                                    <Button size="sm" variant="destructive"><Trash2 className="w-4 h-4" />削除</Button>
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
        </AppLayout>
    );
}
