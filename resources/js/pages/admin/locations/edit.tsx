import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { ArrowLeft } from 'lucide-react';
import { useState, useEffect } from 'react';

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
    location: Location;
}

export default function EditLocation({ location }: Props) {
    const [formData, setFormData] = useState({
        name: location.name,
        prefecture: location.prefecture,
        is_active: location.is_active,
        sort_order: location.sort_order
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(`/admin/locations/${location.id}`, formData);
    };

    const handleInputChange = (field: string, value: string | boolean | number) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'ロケーション管理', href: '/admin/locations' },
            { title: location.name, href: `/admin/locations/${location.id}` },
            { title: '編集', href: `/admin/locations/${location.id}/edit` }
        ]}>
            <Head title={`ロケーション編集 - ${location.name}`} />

            <div className="container mx-auto p-6">
                <div className="flex items-center mb-6">
                    <Link href="/admin/locations" className="mr-4">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">ロケーション編集</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>ロケーション編集: {location.name}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">ロケーション名 *</Label>
                                    <Input
                                        id="name"
                                        value={formData.name}
                                        onChange={(e) => handleInputChange('name', e.target.value)}
                                        placeholder="例: 東京都"
                                        required
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="prefecture">都道府県 *</Label>
                                    <Input
                                        id="prefecture"
                                        value={formData.prefecture}
                                        onChange={(e) => handleInputChange('prefecture', e.target.value)}
                                        placeholder="例: 東京都"
                                        required
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="sort_order">並び順</Label>
                                    <Input
                                        id="sort_order"
                                        type="number"
                                        value={formData.sort_order}
                                        onChange={(e) => handleInputChange('sort_order', parseInt(e.target.value) || 0)}
                                        placeholder="0"
                                        min="0"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="is_active">ステータス</Label>
                                    <div className="flex items-center space-x-2">
                                        <Switch
                                            id="is_active"
                                            checked={formData.is_active}
                                            onCheckedChange={(checked) => handleInputChange('is_active', checked)}
                                        />
                                        <Label htmlFor="is_active">
                                            {formData.is_active ? 'アクティブ' : '非アクティブ'}
                                        </Label>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end space-x-4">
                                <Link href="/admin/locations">
                                    <Button variant="outline" type="button">
                                        キャンセル
                                    </Button>
                                </Link>
                                <Button type="submit">
                                    更新
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 