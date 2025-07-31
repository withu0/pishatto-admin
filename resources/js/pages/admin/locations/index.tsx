import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus, Eye, MapPin } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';

interface Location {
    id: number;
    name: string;
    prefecture: string;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    locations: Location[];
}

export default function AdminLocations({ locations }: Props) {
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/locations', { search: debouncedSearch }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch]);

    const handleDelete = (locationId: number) => {
        if (confirm('このロケーションを削除してもよろしいですか？')) {
            router.delete(`/admin/locations/${locationId}`);
        }
    };

    const filteredLocations = locations.filter(location =>
        location.name.toLowerCase().includes(search.toLowerCase()) ||
        location.prefecture.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <AppLayout breadcrumbs={[{ title: 'ロケーション管理', href: '/admin/locations' }]}>
            <Head title="ロケーション管理" />

            <div className="container mx-auto p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">ロケーション管理</h1>
                    <Link href="/admin/locations/create">
                        <Button>
                            <Plus className="w-4 h-4 mr-2" />
                            新規作成
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>ロケーション一覧</CardTitle>
                        <div className="flex items-center space-x-2">
                            <Input
                                placeholder="ロケーション名または都道府県で検索..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="max-w-sm"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {filteredLocations.length === 0 ? (
                                <div className="text-center py-8 text-gray-500">
                                    ロケーションが見つかりません
                                </div>
                            ) : (
                                <div className="grid gap-4">
                                    {filteredLocations.map((location) => (
                                        <div
                                            key={location.id}
                                            className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50"
                                        >
                                            <div className="flex items-center space-x-4">
                                                <div className="text-2xl">
                                                    <MapPin className="w-6 h-6 text-blue-500" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-lg">{location.name}</h3>
                                                    <p className="text-gray-600 text-sm">{location.prefecture}</p>
                                                    <div className="flex items-center space-x-2 mt-1">
                                                        <Badge variant={location.is_active ? "default" : "secondary"}>
                                                            {location.is_active ? 'アクティブ' : '非アクティブ'}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Link href={`/admin/locations/${location.id}/edit`}>
                                                    <Button variant="outline" size="sm">
                                                        <Edit className="w-4 h-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleDelete(location.id)}
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
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 