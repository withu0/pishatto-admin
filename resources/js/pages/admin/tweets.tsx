import AppLayout from '@/layouts/app-layout';
import { Head, usePage, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Edit, Trash2, Plus, Eye } from 'lucide-react';
import { useState, useCallback } from 'react';

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
    const [activeTab, setActiveTab] = useState<'ゲスト' | 'キャスト'>('ゲスト');
    
    const filteredTweets = tweets.filter(tweet => {
        console.log("TWEET", tweet);
        const matchesSearch = tweet.user.includes(search) || tweet.content.includes(search);
        const matchesTab = tweet.userType === activeTab;
        return matchesSearch && matchesTab;
    });

    const handleTabChange = useCallback((tab: 'ゲスト' | 'キャスト') => {
        setActiveTab(tab);
        setSearch(''); // Reset search when changing tabs
    }, []);

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

    console.log("FILTERED TWEETS", filteredTweets);

    return (
        <AppLayout breadcrumbs={[{ title: 'つぶやき管理', href: '/admin/tweets' }]}>
            <Head title="つぶやき管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">つぶやき管理</h1>
                
                {/* Tabs: Guest / Cast */}
                <div className="mb-6">
                    <div className="inline-flex rounded-md border p-1 bg-white">
                        <button
                            type="button"
                            className={`relative px-4 py-2 rounded-sm text-sm font-medium ${activeTab === 'ゲスト' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                            onClick={() => handleTabChange('ゲスト')}
                        >
                            ゲスト
                        </button>
                        <button
                            type="button"
                            className={`relative px-4 py-2 rounded-sm text-sm font-medium ${activeTab === 'キャスト' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                            onClick={() => handleTabChange('キャスト')}
                        >
                            キャスト
                        </button>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>{activeTab === 'ゲスト' ? 'ゲスト' : 'キャスト'}つぶやき一覧</CardTitle>
                        <Link href={route('admin.tweets.create')}>
                            <Button size="sm" className="gap-1"><Plus className="w-4 h-4" />新規登録</Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-2">
                            <Input
                                placeholder={`${activeTab === 'ゲスト' ? 'ゲスト' : 'キャスト'}のユーザー・内容で検索`}
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
                                        <th className="px-3 py-2 text-left font-semibold">ユーザー</th>
                                        <th className="px-3 py-2 text-left font-semibold">内容</th>
                                        <th className="px-3 py-2 text-left font-semibold">日付</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredTweets.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-6 text-muted-foreground">
                                                {tweets.filter(t => t.userType === activeTab).length === 0
                                                    ? `${activeTab === 'ゲスト' ? 'ゲスト' : 'キャスト'}のつぶやきデータがありません` 
                                                    : '該当するデータがありません'
                                                }
                                            </td>
                                        </tr>
                                    ) : (   
                                        filteredTweets.map((item, idx) => (
                                            <tr key={item.id} className="border-t">
                                                <td className="px-3 py-2">{idx + 1}</td>
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
