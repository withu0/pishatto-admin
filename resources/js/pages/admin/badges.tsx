import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus, Eye } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';

interface Badge {
    id: number;
    name: string;
    icon?: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    badges: {
        data: Badge[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
    };
}

export default function AdminBadges({ badges, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/badges', { search: debouncedSearch }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch]);

    const handleDelete = (badgeId: number) => {
        if (confirm('このバッジを削除してもよろしいですか？')) {
            router.delete(`/admin/badges/${badgeId}`);
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'バッジ管理', href: '/admin/badges' }]}>
            <Head title="バッジ管理" />

            <div className="container mx-auto p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">バッジ管理</h1>
                    <Link href="/admin/badges/create">
                        <Button>
                            <Plus className="w-4 h-4 mr-2" />
                            新規作成
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>バッジ一覧</CardTitle>
                        <div className="flex items-center space-x-2">
                            <Input
                                placeholder="バッジ名または説明で検索..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="max-w-sm"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {badges.data.length === 0 ? (
                                <div className="text-center py-8 text-gray-500">
                                    バッジが見つかりません
                                </div>
                            ) : (
                                <div className="grid gap-4">
                                    {badges.data.map((badge) => (
                                        <div
                                            key={badge.id}
                                            className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                                        >
                                            <div className="flex items-center space-x-4">
                                                <div className="text-2xl">
                                                    {badge.icon || '🏆'}
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-lg">{badge.name}</h3>
                                                    {badge.description && (
                                                        <p className="text-gray-600 text-sm">{badge.description}</p>
                                                    )}
                                                    <div className="flex items-center space-x-2 mt-1">
                                                        <Badge variant="outline" className="text-xs">
                                                            作成日: {new Date(badge.created_at).toLocaleDateString('ja-JP')}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Link href={`/admin/badges/${badge.id}`}>
                                                    <Button variant="outline" size="sm">
                                                        <Eye className="w-4 h-4" />
                                                    </Button>
                                                </Link>
                                                <Link href={`/admin/badges/${badge.id}/edit`}>
                                                    <Button variant="outline" size="sm">
                                                        <Edit className="w-4 h-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleDelete(badge.id)}
                                                    className="text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Pagination */}
                        {badges.last_page > 1 && (
                            <div className="flex justify-center mt-6">
                                <div className="flex space-x-2">
                                    {badges.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            onClick={() => router.get('/admin/badges', {
                                                page: badges.current_page - 1,
                                                search: filters.search
                                            })}
                                        >
                                            前へ
                                        </Button>
                                    )}

                                    <span className="flex items-center px-3 py-2 text-sm">
                                        {badges.current_page} / {badges.last_page}
                                    </span>

                                    {badges.current_page < badges.last_page && (
                                        <Button
                                            variant="outline"
                                            onClick={() => router.get('/admin/badges', {
                                                page: badges.current_page + 1,
                                                search: filters.search
                                            })}
                                        >
                                            次へ
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
