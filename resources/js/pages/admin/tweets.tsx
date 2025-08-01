import AppLayout from '@/layouts/app-layout';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus, Eye } from 'lucide-react';
import { useState } from 'react';

interface Tweet {
    id: number;
    userType: string;
    user: string;
    content: string;
    date: string;
}

interface Guest {
    id: number;
    nickname: string;
    phone: string;
}

interface Cast {
    id: number;
    nickname: string;
    phone: string;
}

interface PageProps {
    tweets: Tweet[];
    guests: Guest[];
    casts: Cast[];
    [key: string]: any;
}

export default function AdminTweets() {
    const { tweets, guests, casts } = usePage<PageProps>().props;
    const [search, setSearch] = useState('');
    const [isDeleting, setIsDeleting] = useState<number | null>(null);
    
    const filtered = tweets.filter(
        (t) => t.user.includes(search) || t.content.includes(search) || t.userType.includes(search)
    );

    const handleDelete = async (tweetId: number) => {
        if (confirm('このつぶやきを削除しますか？')) {
            setIsDeleting(tweetId);
            try {
                await router.delete(`/api/tweets/${tweetId}`);
                router.reload();
            } catch (error) {
                console.error('Error deleting tweet:', error);
                alert('削除に失敗しました。');
            } finally {
                setIsDeleting(null);
            }
        }
    };
    return (
        <AppLayout breadcrumbs={[{ title: 'つぶやき管理', href: '/admin/tweets' }]}>
            <Head title="つぶやき管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">つぶやき管理</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>つぶやき一覧</CardTitle>
                        <Link href={route('admin.tweets.create')}>
                            <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder="ユーザー・内容・種別で検索"
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
                                        <th className="px-3 py-2 text-left font-semibold">種別</th>
                                        <th className="px-3 py-2 text-left font-semibold">ユーザー</th>
                                        <th className="px-3 py-2 text-left font-semibold">内容</th>
                                        <th className="px-3 py-2 text-left font-semibold">日付</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="text-center py-6 text-muted-foreground">
                                                {tweets.length === 0 ? 'つぶやきデータがありません' : '該当するデータがありません'}
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{idx + 1}</td>
                                                <td className="px-3 py-2">{item.userType}</td>
                                                <td className="px-3 py-2">{item.user}</td>
                                                <td className="px-3 py-2">{item.content}</td>
                                                <td className="px-3 py-2">{item.date}</td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Link href={route('admin.tweets.show', item.id)}>
                                                        <Button size="sm" variant="outline"><Eye className="w-4 h-4" />詳細</Button>
                                                    </Link>
                                                    <Link href={route('admin.tweets.edit', item.id)}>
                                                        <Button size="sm" variant="outline"><Edit className="w-4 h-4" />編集</Button>
                                                    </Link>
                                                    <Button 
                                                        size="sm" 
                                                        variant="destructive" 
                                                        onClick={() => handleDelete(item.id)}
                                                        disabled={isDeleting === item.id}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                        {isDeleting === item.id ? '削除中...' : '削除'}
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
