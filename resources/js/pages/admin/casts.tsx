import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus, Eye, Search } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';

interface Cast {
    id: number;
    phone?: string;
    line_id?: string;
    nickname?: string;
    avatar?: string;
    avatar_urls?: string[];
    status?: string;
    birth_year?: number;
    height?: number;
    grade?: string;
    grade_points?: number;
    residence?: string;
    birthplace?: string;
    profile_text?: string;
    payjp_customer_id?: string;
    payment_info?: string;
    points: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    casts: {
        data: Cast[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
    };
}

export default function AdminCasts({ casts, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/casts', { search: debouncedSearch }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch]);

    const handleDelete = (castId: number) => {
        if (confirm('このキャストを削除してもよろしいですか？')) {
            router.delete(`/admin/casts/${castId}`);
        }
    };

    const getDisplayName = (cast: Cast) => {
        return cast.nickname || cast.phone || `キャスト${cast.id}`;
    };

    const getStatusBadge = (cast: Cast) => {
        switch (cast.status) {
            case 'active':
                return <Badge variant="default">アクティブ</Badge>;
            case 'inactive':
                return <Badge variant="secondary">非アクティブ</Badge>;
            case 'suspended':
                return <Badge variant="destructive">一時停止</Badge>;
            default:
                return <Badge variant="outline">未設定</Badge>;
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'キャスト一覧', href: '/admin/casts' }]}>
            <Head title="キャスト一覧" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">キャスト一覧</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>キャスト一覧 ({casts.total}件)</CardTitle>
                        <Link href="/admin/casts/create">
                            <Button size="sm" className="gap-1">
                                <Plus className="w-4 h-4" />新規登録
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-4">
                            <div className="relative flex-1 max-w-xs">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="名前・電話番号・LINE IDで検索"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">表示件数</span>
                                <select
                                    className="px-2 py-1 border rounded text-sm"
                                    value={String(casts.per_page || 10)}
                                    onChange={(e) => router.get('/admin/casts', { search: debouncedSearch, page: 1, per_page: Number(e.target.value) }, { preserveState: true })}
                                >
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">名前</th>
                                        <th className="px-3 py-2 text-left font-semibold">電話番号</th>
                                        <th className="px-3 py-2 text-left font-semibold">LINE ID</th>
                                        <th className="px-3 py-2 text-left font-semibold">状態</th>
                                        <th className="px-3 py-2 text-left font-semibold">グレード</th>
                                        <th className="px-3 py-2 text-left font-semibold">グレードポイント</th>
                                        <th className="px-3 py-2 text-left font-semibold">ポイント</th>
                                        <th className="px-3 py-2 text-left font-semibold">登録日</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {casts.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={10} className="text-center py-6 text-muted-foreground">
                                                該当するキャストがいません
                                            </td>
                                        </tr>
                                    ) : (
                                        casts.data.map((cast, idx) => (
                                            <tr key={cast.id} className="border-t">
                                                <td className="px-3 py-2">{casts.per_page * (casts.current_page - 1) + idx + 1}</td>
                                                <td className="px-3 py-2 flex items-center gap-2">
                                                    <Avatar className="w-8 h-8">
                                                        <AvatarImage src={cast.avatar_urls?.[0] || cast.avatar} />
                                                        <AvatarFallback>
                                                            {getDisplayName(cast)[0]}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="font-medium">{getDisplayName(cast)}</span>
                                                </td>
                                                <td className="px-3 py-2">{cast.phone || '-'}</td>
                                                <td className="px-3 py-2">{cast.line_id || '-'}</td>
                                                <td className="px-3 py-2">
                                                    {getStatusBadge(cast)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {cast.grade ? (
                                                        <Badge variant="outline">{cast.grade}</Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {cast.grade_points ? (
                                                        <Badge variant="secondary">{cast.grade_points.toLocaleString()} pt</Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Badge variant="secondary">{cast.points.toLocaleString()} pt</Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {new Date(cast.created_at).toLocaleDateString('ja-JP')}
                                                </td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Link href={`/admin/casts/${cast.id}`}>
                                                        <Button size="sm" variant="outline">
                                                            <Eye className="w-4 h-4" />詳細
                                                        </Button>
                                                    </Link>
                                                    <Link href={`/admin/casts/${cast.id}/edit`}>
                                                        <Button size="sm" variant="outline">
                                                            <Edit className="w-4 h-4" />編集
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => handleDelete(cast.id)}
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

                        {/* Pagination (numbered) */}
                        {casts.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {casts.per_page * (casts.current_page - 1) + 1} - {Math.min(casts.per_page * casts.current_page, casts.total)} / {casts.total}件
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {Array.from({ length: casts.last_page }, (_, i) => i + 1).map((pageNum) => (
                                        <Button
                                            key={pageNum}
                                            size="sm"
                                            variant={pageNum === casts.current_page ? 'default' : 'outline'}
                                            disabled={pageNum === casts.current_page}
                                            onClick={() => router.get('/admin/casts', {
                                                ...filters,
                                                page: pageNum,
                                                per_page: casts.per_page || 10
                                            }, { preserveState: true })}
                                        >
                                            {pageNum}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
