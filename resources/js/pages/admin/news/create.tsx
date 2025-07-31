import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft } from 'lucide-react';

export default function AdminNewsCreate() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        content: '',
        target_type: 'all',
        status: 'draft',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/news');
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'お知らせ配信', href: '/admin/notifications' },
            { title: '新規作成', href: '/admin/news/create' }
        ]}>
            <Head title="お知らせ新規作成" />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Button variant="outline" size="sm" onClick={() => window.history.back()}>
                        <ArrowLeft className="w-4 h-4 mr-2" />
                        戻る
                    </Button>
                    <h1 className="text-2xl font-bold">お知らせ新規作成</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>お知らせ情報</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">タイトル *</label>
                                <Input
                                    value={data.title}
                                    onChange={e => setData('title', e.target.value)}
                                    placeholder="お知らせのタイトルを入力"
                                    className={errors.title ? 'border-red-500' : ''}
                                />
                                {errors.title && <p className="text-sm text-red-500">{errors.title}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">内容 *</label>
                                <Textarea
                                    value={data.content}
                                    onChange={e => setData('content', e.target.value)}
                                    placeholder="お知らせの内容を入力"
                                    rows={6}
                                    className={errors.content ? 'border-red-500' : ''}
                                />
                                {errors.content && <p className="text-sm text-red-500">{errors.content}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">対象 *</label>
                                <Select value={data.target_type} onValueChange={value => setData('target_type', value)}>
                                    <SelectTrigger className={errors.target_type ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="対象を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">全体</SelectItem>
                                        <SelectItem value="guest">ゲスト</SelectItem>
                                        <SelectItem value="cast">キャスト</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.target_type && <p className="text-sm text-red-500">{errors.target_type}</p>}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium">ステータス *</label>
                                <Select value={data.status} onValueChange={value => setData('status', value)}>
                                    <SelectTrigger className={errors.status ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="ステータスを選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">下書き</SelectItem>
                                        <SelectItem value="published">公開</SelectItem>
                                        <SelectItem value="archived">アーカイブ</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && <p className="text-sm text-red-500">{errors.status}</p>}
                            </div>

                            <div className="flex gap-4 pt-4">
                                <Button type="submit" disabled={processing}>
                                    {processing ? '作成中...' : '作成'}
                                </Button>
                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    キャンセル
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 