import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Edit, Trash2 } from 'lucide-react';

interface Gift {
    id: number;
    name: string;
    category: string;
    points: number;
    icon: string | null;
    description: string | null;
    created_at: string;
}

interface Props {
    gift: Gift;
    categories: Record<string, string>;
}

export default function ShowGift({ gift, categories }: Props) {
    const getCategoryLabel = (categoryKey: string) => {
        return categories[categoryKey] || categoryKey;
    };

    const handleDelete = () => {
        if (confirm('このギフトを削除しますか？')) {
            // This would typically use router.delete, but for now we'll just show the confirmation
            console.log('Delete gift:', gift.id);
        }
    };

    return (
        <AppLayout>
            <Head title={`ギフト詳細 - ${gift.name}`} />

            <div className="container mx-auto py-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center">
                        <Link href="/admin/gifts" className="mr-4">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-3xl font-bold">ギフト詳細</h1>
                    </div>
                    <div className="flex gap-2">
                        <Link href={`/admin/gifts/${gift.id}/edit`}>
                            <Button variant="outline">
                                <Edit className="w-4 h-4 mr-2" />
                                編集
                            </Button>
                        </Link>
                        <Button variant="destructive" onClick={handleDelete}>
                            <Trash2 className="w-4 h-4 mr-2" />
                            削除
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>ギフト情報</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500">アイコン</label>
                                        <div className="mt-1">
                                            <span className="text-4xl">{gift.icon || '🎁'}</span>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="text-sm font-medium text-gray-500">ギフト名</label>
                                        <p className="mt-1 text-lg font-semibold">{gift.name}</p>
                                    </div>

                                    <div>
                                        <label className="text-sm font-medium text-gray-500">カテゴリ</label>
                                        <div className="mt-1">
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                {getCategoryLabel(gift.category)}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <label className="text-sm font-medium text-gray-500">ポイント</label>
                                        <p className="mt-1 text-2xl font-bold text-green-600">
                                            {gift.points.toLocaleString()}
                                        </p>
                                    </div>

                                    <div>
                                        <label className="text-sm font-medium text-gray-500">作成日</label>
                                        <p className="mt-1 text-gray-900">
                                            {new Date(gift.created_at).toLocaleDateString('ja-JP', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </p>
                                    </div>

                                    <div>
                                        <label className="text-sm font-medium text-gray-500">ID</label>
                                        <p className="mt-1 text-gray-900 font-mono">#{gift.id}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>ギフト詳細</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <h4 className="font-medium text-gray-900 mb-2">カテゴリ説明</h4>
                                    <p className="text-gray-600">
                                        {gift.category === 'standard' && '標準的なギフトで、すべてのユーザーが利用できます。'}
                                        {gift.category === 'regional' && '地域限定のギフトで、特定の地域でのみ利用できます。'}
                                        {gift.category === 'grade' && 'グレードに応じたギフトで、ユーザーのレベルに応じて利用できます。'}
                                        {gift.category === 'mygift' && 'マイギフトで、ユーザーが自分で作成したギフトです。'}
                                    </p>
                                </div>

                                <div>
                                    <h4 className="font-medium text-gray-900 mb-2">ポイントについて</h4>
                                    <p className="text-gray-600">
                                        このギフトを贈るために必要なポイント数です。ユーザーはこのポイント数分のポイントを消費してギフトを贈ることができます。
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {gift.description && (
                        <Card>
                            <CardHeader>
                                <CardTitle>説明</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-gray-700 whitespace-pre-line">{gift.description}</div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
