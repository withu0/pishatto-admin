import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Edit, Trash2, Plus, Search, Eye } from 'lucide-react';
import { useState, useEffect } from 'react';
import { debounce } from 'lodash';

interface Gift {
    id: number;
    name: string;
    category: string;
    points: number;
    icon: string | null;
    created_at: string;
}

interface Filters {
    search?: string;
    category?: string;
}

interface Props {
    gifts: {
        data: Gift[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    categories: Record<string, string>;
}

export default function AdminGiftsIndex({ gifts, filters, categories }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || 'all');

    const debouncedSearch = debounce((value: string) => {
        const categoryParam = category === 'all' ? '' : category;
        router.get('/admin/gifts', { search: value, category: categoryParam }, {
            preserveState: true,
            replace: true
        });
    }, 300);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    const handleCategoryChange = (value: string) => {
        setCategory(value);
        const categoryParam = value === 'all' ? '' : value;
        router.get('/admin/gifts', { search, category: categoryParam }, {
            preserveState: true,
            replace: true
        });
    };

    const handleDelete = (giftId: number) => {
        if (confirm('このギフトを削除しますか？')) {
            router.delete(`/admin/gifts/${giftId}`);
        }
    };

    const getCategoryLabel = (categoryKey: string) => {
        return categories[categoryKey] || categoryKey;
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'ギフト管理', href: '/admin/gifts' }]}>
            <Head title="ギフト管理" />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">ギフト管理</h1>
                    <Link href="/admin/gifts/create">
                        <Button size="sm" className="gap-1">
                            <Plus className="w-4 h-4" />
                            新規登録
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>ギフト一覧</CardTitle>
                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                <Input
                                    placeholder="ギフト名で検索"
                                    value={search}
                                    onChange={(e) => handleSearchChange(e.target.value)}
                                    className="pl-8 w-64"
                                />
                            </div>
                            <Select value={category} onValueChange={handleCategoryChange}>
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="カテゴリ" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">すべて</SelectItem>
                                    {Object.entries(categories).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">アイコン</th>
                                        <th className="px-3 py-2 text-left font-semibold">ギフト名</th>
                                        <th className="px-3 py-2 text-left font-semibold">カテゴリ</th>
                                        <th className="px-3 py-2 text-left font-semibold">ポイント</th>
                                        <th className="px-3 py-2 text-left font-semibold">作成日</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {gifts.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="text-center py-6 text-muted-foreground">
                                                該当するデータがありません
                                            </td>
                                        </tr>
                                    ) : (
                                        gifts.data.map((gift, idx) => (
                                            <tr key={gift.id} className="border-t">
                                                <td className="px-3 py-2">{(gifts.current_page - 1) * gifts.per_page + idx + 1}</td>
                                                <td className="px-3 py-2">
                                                    <span className="text-2xl">{gift.icon || '🎁'}</span>
                                                </td>
                                                <td className="px-3 py-2 font-medium">{gift.name}</td>
                                                <td className="px-3 py-2">
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {getCategoryLabel(gift.category)}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">{gift.points.toLocaleString()}</td>
                                                <td className="px-3 py-2">{new Date(gift.created_at).toLocaleDateString('ja-JP')}</td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Link href={`/admin/gifts/${gift.id}`}>
                                                        <Button size="sm" variant="outline">
                                                            <Eye className="w-4 h-4" />
                                                            詳細
                                                        </Button>
                                                    </Link>
                                                    <Link href={`/admin/gifts/${gift.id}/edit`}>
                                                        <Button size="sm" variant="outline">
                                                            <Edit className="w-4 h-4" />
                                                            編集
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => handleDelete(gift.id)}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                        削除
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {gifts.last_page > 1 && (
                            <div className="flex justify-center mt-6">
                                <div className="flex gap-2">
                                    {Array.from({ length: gifts.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            variant={page === gifts.current_page ? "default" : "outline"}
                                            size="sm"
                                            onClick={() => {
                                                const categoryParam = category === 'all' ? '' : category;
                                                router.get('/admin/gifts', {
                                                    page,
                                                    search,
                                                    category: categoryParam
                                                }, { preserveState: true });
                                            }}
                                        >
                                            {page}
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
