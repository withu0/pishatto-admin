import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Edit, Trash2 } from 'lucide-react';

interface Badge {
    id: number;
    name: string;
    icon?: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    badge: Badge;
}

export default function ShowBadge({ badge }: Props) {
    const handleDelete = () => {
        if (confirm('このバッジを削除してもよろしいですか？')) {
            // This would typically use router.delete, but we'll handle it in the parent component
            window.location.href = `/admin/badges/${badge.id}`;
        }
    };

    return (
        <AppLayout>
            <Head title={`バッジ詳細 - ${badge.name}`} />

            <div className="container mx-auto py-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center">
                        <Link href="/admin/badges" className="mr-4">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-3xl font-bold">バッジ詳細</h1>
                    </div>
                    <div className="flex space-x-2">
                        <Link href={`/admin/badges/${badge.id}/edit`}>
                            <Button variant="outline">
                                <Edit className="w-4 h-4 mr-2" />
                                編集
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            onClick={handleDelete}
                            className="text-red-600 hover:text-red-700"
                        >
                            <Trash2 className="w-4 h-4 mr-2" />
                            削除
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>バッジ情報</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-6">
                                <div className="flex items-center space-x-4">
                                    <div className="text-4xl border rounded-lg p-4 min-w-[80px] text-center">
                                        {badge.icon || '🏆'}
                                    </div>
                                    <div>
                                        <h2 className="text-2xl font-bold">{badge.name}</h2>
                                        {badge.description && (
                                            <p className="text-gray-600 mt-2">{badge.description}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium text-gray-500">バッジID</Label>
                                        <p className="text-lg">{badge.id}</p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium text-gray-500">アイコン</Label>
                                        <p className="text-lg">{badge.icon || '未設定'}</p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium text-gray-500">作成日</Label>
                                        <p className="text-lg">
                                            {new Date(badge.created_at).toLocaleDateString('ja-JP', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium text-gray-500">更新日</Label>
                                        <p className="text-lg">
                                            {new Date(badge.updated_at).toLocaleDateString('ja-JP', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>プレビュー</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center space-x-3 p-4 border rounded-lg">
                                <div className="text-2xl">
                                    {badge.icon || '🏆'}
                                </div>
                                <div>
                                    <h3 className="font-semibold">{badge.name}</h3>
                                    {badge.description && (
                                        <p className="text-sm text-gray-600">{badge.description}</p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

// Simple Label component for this page
const Label = ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
);
