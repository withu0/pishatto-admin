import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Edit, Trash2, Calendar, User } from 'lucide-react';
import { useState } from 'react';

interface Tweet {
    id: number;
    userType: string;
    user: string;
    content: string;
    image: string | null;
    date: string;
}

interface PageProps {
    tweet: Tweet;
    [key: string]: any;
}

export default function ShowTweet() {
    const { tweet } = usePage<PageProps>().props;
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        if (confirm('このつぶやきを削除しますか？')) {
            setIsDeleting(true);
            router.delete(route('admin.tweets.destroy', tweet.id));
        }
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'つぶやき管理', href: route('admin.tweets.index') },
            { title: '詳細', href: route('admin.tweets.show', tweet.id) }
        ]}>
            <Head title="つぶやき詳細" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-4">
                        <Link href={route('admin.tweets.index')}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">つぶやき詳細</h1>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('admin.tweets.edit', tweet.id)}>
                            <Button size="sm">
                                <Edit className="w-4 h-4 mr-2" />
                                編集
                            </Button>
                        </Link>
                        <Button 
                            size="sm" 
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            <Trash2 className="w-4 h-4 mr-2" />
                            {isDeleting ? '削除中...' : '削除'}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>つぶやき内容</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <Badge variant={tweet.userType === 'ゲスト' ? 'default' : 'secondary'}>
                                        {tweet.userType}
                                    </Badge>
                                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <User className="w-4 h-4" />
                                        {tweet.user}
                                    </div>
                                </div>
                                
                                <div className="prose max-w-none">
                                    <p className="text-lg leading-relaxed whitespace-pre-wrap">
                                        {tweet.content}
                                    </p>
                                </div>

                                {tweet.image && (
                                    <div className="mt-4">
                                        <img 
                                            src={`/storage/${tweet.image}`} 
                                            alt="Tweet image" 
                                            className="max-w-full rounded-lg border"
                                        />
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle>詳細情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <Calendar className="w-4 h-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">作成日時</span>
                                </div>
                                <p className="text-sm">{tweet.date}</p>
                                
                                <div className="pt-4 border-t">
                                    <div className="flex items-center gap-2 mb-2">
                                        <User className="w-4 h-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">投稿者</span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={tweet.userType === 'ゲスト' ? 'default' : 'secondary'}>
                                            {tweet.userType}
                                        </Badge>
                                        <span className="text-sm">{tweet.user}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
} 