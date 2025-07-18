import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus } from 'lucide-react';
import { useState } from 'react';

const mockMatching = [
    { id: 1, guest: '山田 太郎', cast: '高橋 美咲', status: 'マッチング済', date: '2024-07-01' },
    { id: 2, guest: '佐藤 花子', cast: '田中 直樹', status: 'キャンセル', date: '2024-06-15' },
];

export default function AdminMatchingManage() {
    const [search, setSearch] = useState('');
    const filtered = mockMatching.filter(
        (m) => m.guest.includes(search) || m.cast.includes(search)
    );
    return (
        <AppLayout breadcrumbs={[{ title: 'マッチング管理', href: '/admin/matching-manage' }]}>
            <Head title="マッチング管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">マッチング管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>マッチング管理一覧</CardTitle>
                        <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="ゲスト・キャストで検索"
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
                                        <th className="px-3 py-2 text-left font-semibold">ゲスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">キャスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">状態</th>
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
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2">{item.guest}</td>
                                                <td className="px-3 py-2">{item.cast}</td>
                                                <td className="px-3 py-2">{item.status}</td>
                                                <td className="px-3 py-2">{item.date}</td>
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
