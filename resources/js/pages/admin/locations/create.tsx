import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';

export default function CreateLocation() {
    const [formData, setFormData] = useState({
        name: '',
        prefecture: '',
        is_active: true,
        sort_order: 0
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/locations', formData);
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
            { title: '新規作成', href: '/admin/locations/create' }
        ]}>
            <Head title="ロケーション新規作成" />

            <div className="container mx-auto p-6">
                <div className="flex items-center mb-6">
                    <Link href="/admin/locations" className="mr-4">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">ロケーション新規作成</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>新規ロケーション</CardTitle>
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
                                    作成
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 