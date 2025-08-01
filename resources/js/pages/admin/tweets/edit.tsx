import AppLayout from '@/layouts/app-layout';
import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Save, Upload, X } from 'lucide-react';
import { useState } from 'react';

interface Tweet {
    id: number;
    userType: string;
    user: string;
    content: string;
    image: string | null;
    guest_id: number | null;
    cast_id: number | null;
    date: string;
}

interface Guest {
    id: number;
    nickname: string;
    phone: string;
}

interface Cast {
    id: number;
    nickname: string;
    phone: string;
}

interface PageProps {
    tweet: Tweet;
    guests: Guest[];
    casts: Cast[];
    [key: string]: any;
}

export default function EditTweet() {
    const { tweet, guests, casts } = usePage<PageProps>().props;
    const [imagePreview, setImagePreview] = useState<string | null>(tweet.image ? `/storage/${tweet.image}` : null);
    
    const { data, setData, put, processing, errors } = useForm({
        content: tweet.content,
        guest_id: tweet.guest_id?.toString() || 'none',
        cast_id: tweet.cast_id?.toString() || 'none',
        image: null as File | null,
    });

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            const reader = new FileReader();
            reader.onload = (e) => {
                setImagePreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const removeImage = () => {
        setData('image', null);
        setImagePreview(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Convert "none" values back to empty strings for backend
        setData('guest_id', data.guest_id === 'none' ? '' : data.guest_id);
        setData('cast_id', data.cast_id === 'none' ? '' : data.cast_id);
        
        put(route('admin.tweets.update', tweet.id));
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'つぶやき管理', href: route('admin.tweets.index') },
            { title: '編集', href: route('admin.tweets.edit', tweet.id) }
        ]}>
            <Head title="つぶやき編集" />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Link href={route('admin.tweets.index')}>
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">つぶやき編集</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>つぶやき情報</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="content">内容 *</Label>
                                <Textarea
                                    id="content"
                                    value={data.content}
                                    onChange={e => setData('content', e.target.value)}
                                    placeholder="つぶやきの内容を入力してください"
                                    className="min-h-[120px]"
                                />
                                {errors.content && (
                                    <p className="text-sm text-red-600">{errors.content}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-2">
                                    <Label htmlFor="guest_id">ゲスト</Label>
                                    <Select value={data.guest_id} onValueChange={value => setData('guest_id', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="ゲストを選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">なし</SelectItem>
                                            {guests.map((guest: Guest) => (
                                                <SelectItem key={guest.id} value={guest.id.toString()}>
                                                    {guest.nickname || guest.phone}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.guest_id && (
                                        <p className="text-sm text-red-600">{errors.guest_id}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="cast_id">キャスト</Label>
                                    <Select value={data.cast_id} onValueChange={value => setData('cast_id', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="キャストを選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">なし</SelectItem>
                                            {casts.map((cast: Cast) => (
                                                <SelectItem key={cast.id} value={cast.id.toString()}>
                                                    {cast.nickname || cast.phone}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.cast_id && (
                                        <p className="text-sm text-red-600">{errors.cast_id}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="image">画像</Label>
                                <div className="flex items-center gap-4">
                                    <Input
                                        id="image"
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        className="max-w-xs"
                                    />
                                    <Upload className="w-4 h-4 text-muted-foreground" />
                                </div>
                                {imagePreview && (
                                    <div className="mt-4 relative inline-block">
                                        <img 
                                            src={imagePreview} 
                                            alt="Preview" 
                                            className="max-w-xs rounded-lg border"
                                        />
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={removeImage}
                                            className="absolute -top-2 -right-2 rounded-full w-6 h-6 p-0"
                                        >
                                            <X className="w-3 h-3" />
                                        </Button>
                                    </div>
                                )}
                                {errors.image && (
                                    <p className="text-sm text-red-600">{errors.image}</p>
                                )}
                            </div>

                            <div className="flex gap-4 pt-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="w-4 h-4 mr-2" />
                                    {processing ? '更新中...' : '更新'}
                                </Button>
                                <Link href={route('admin.tweets.index')}>
                                    <Button type="button" variant="outline">
                                        キャンセル
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
} 