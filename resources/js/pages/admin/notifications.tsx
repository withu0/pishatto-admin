import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus } from 'lucide-react';
import { useState } from 'react';

const mockNotifications = [
    { id: 1, target: '全体', title: 'メンテナンスのお知らせ', date: '2024-07-01' },
    { id: 2, target: 'ゲスト', title: '新機能リリース', date: '2024-06-15' },
];

export default function AdminNotifications() {
    const [search, setSearch] = useState('');
    const filtered = mockNotifications.filter(
        (n) => n.title.includes(search) || n.target.includes(search)
    );
    return (
        <AppLayout breadcrumbs={[{ title: 'お知らせ配信', href: '/admin/notifications' }]}>
            <Head title="お知らせ配信" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">お知らせ配信</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>お知らせ一覧</CardTitle>
                        <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="タイトル・対象で検索"
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
                                        <th className="px-3 py-2 text-left font-semibold">対象</th>
                                        <th className="px-3 py-2 text-left font-semibold">タイトル</th>
                                        <th className="px-3 py-2 text-left font-semibold">日付</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-6 text-muted-foreground">該当するデータがありません</td>
                                        </tr>
                                    ) : (
                                        filtered.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2">{item.target}</td>
                                                <td className="px-3 py-2">{item.title}</td>
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
