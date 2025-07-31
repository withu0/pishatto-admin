import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus } from 'lucide-react';
import { useState } from 'react';

interface AdminNews {
    id: number;
    title: string;
    target_type: string;
    status: string;
    published_at: string | null;
    created_at: string;
    creator: string;
}

interface Props {
    news: AdminNews[];
}

export default function AdminNotifications({ news }: Props) {
    const [search, setSearch] = useState('');
    const filtered = news.filter(
        (n) => n.title.includes(search) || n.target_type.includes(search)
    );
    return (
        <AppLayout breadcrumbs={[{ title: 'お知らせ配信', href: '/admin/notifications' }]}>
            <Head title="お知らせ配信" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">お知らせ配信</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>お知らせ一覧</CardTitle>
                        <Link href="/admin/news/create">
                            <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                        </Link>
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
                                        <th className="px-3 py-2 text-left font-semibold">ステータス</th>
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
                                                <td className="px-3 py-2">{item.target_type === 'all' ? '全体' : item.target_type === 'guest' ? 'ゲスト' : 'キャスト'}</td>
                                                <td className="px-3 py-2">{item.title}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`px-2 py-1 text-xs rounded-full ${
                                                        item.status === 'published' ? 'bg-green-100 text-green-800' :
                                                        item.status === 'draft' ? 'bg-yellow-100 text-yellow-800' :
                                                        'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {item.status === 'published' ? '公開済み' : 
                                                         item.status === 'draft' ? '下書き' : 'アーカイブ'}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">{item.published_at || item.created_at}</td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Link href={`/admin/news/${item.id}/edit`}>
                                                        <Button size="sm" variant="outline"><Edit className="w-4 h-4" />編集</Button>
                                                    </Link>
                                                    <Button 
                                                        size="sm" 
                                                        variant="destructive"
                                                        onClick={() => {
                                                            if (confirm('このお知らせを削除しますか？')) {
                                                                router.delete(`/admin/news/${item.id}`);
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 className="w-4 h-4" />削除
                                                    </Button>
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
