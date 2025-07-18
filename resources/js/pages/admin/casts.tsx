import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus } from 'lucide-react';
import { useState } from 'react';

const mockCasts = [
    { id: 1, name: '高橋 美咲', email: 'misaki@example.com', status: '有効', joined: '2024-07-01' },
    { id: 2, name: '田中 直樹', email: 'naoki@example.com', status: '無効', joined: '2024-06-15' },
    { id: 3, name: '小林 さくら', email: 'sakura@example.com', status: '有効', joined: '2024-05-20' },
];

export default function AdminCasts() {
    const [search, setSearch] = useState('');
    const filteredCasts = mockCasts.filter(
        (c) => c.name.includes(search) || c.email.includes(search)
    );
    return (
        <AppLayout breadcrumbs={[{ title: 'キャスト一覧', href: '/admin/casts' }]}>
            <Head title="キャスト一覧" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">キャスト一覧</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>キャスト一覧</CardTitle>
                        <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="名前・メールアドレスで検索"
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
                                        <th className="px-3 py-2 text-left font-semibold">名前</th>
                                        <th className="px-3 py-2 text-left font-semibold">メールアドレス</th>
                                        <th className="px-3 py-2 text-left font-semibold">状態</th>
                                        <th className="px-3 py-2 text-left font-semibold">登録日</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredCasts.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-6 text-muted-foreground">該当するキャストがいません</td>
                                        </tr>
                                    ) : (
                                        filteredCasts.map((cast, idx) => (
                                            <tr key={cast.id} className="border-t">
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2 flex items-center gap-2">
                                                    <Avatar>
                                                        <AvatarFallback>{cast.name[0]}</AvatarFallback>
                                                    </Avatar>
                                                    {cast.name}
                                                </td>
                                                <td className="px-3 py-2">{cast.email}</td>
                                                <td className="px-3 py-2">
                                                    <Badge variant={cast.status === '有効' ? 'default' : 'outline'}>{cast.status}</Badge>
                                                </td>
                                                <td className="px-3 py-2">{cast.joined}</td>
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
