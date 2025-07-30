import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AvatarSlider } from '@/components/ui/avatar-slider';
import { ArrowLeft, Save, Upload, X, Plus } from 'lucide-react';
import { useState } from 'react';

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
    cast: Cast;
}

export default function CastEdit({ cast }: Props) {
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [uploadedAvatars, setUploadedAvatars] = useState<string[]>([]);
    const [isUploading, setIsUploading] = useState(false);
    const [currentAvatarPaths, setCurrentAvatarPaths] = useState<string>(cast.avatar || '');

    const { data, setData, put, processing, errors } = useForm({
        phone: cast.phone || '',
        line_id: cast.line_id || '',
        nickname: cast.nickname || '',
        avatar: cast.avatar || '',
        status: cast.status || 'active',
        birth_year: cast.birth_year?.toString() || '',
        height: cast.height?.toString() || '',
        grade: cast.grade || '',
        grade_points: cast.grade_points?.toString() || '',
        residence: cast.residence || '',
        birthplace: cast.birthplace || '',
        profile_text: cast.profile_text || '',
        payjp_customer_id: cast.payjp_customer_id || '',
        payment_info: cast.payment_info || '',
        points: cast.points.toString(),
    });

    // Get current avatar URLs - now based on currentAvatarPaths state
    const getCurrentAvatarUrls = (): string[] => {
        if (cast.avatar_urls && cast.avatar_urls.length > 0 && currentAvatarPaths === cast.avatar) {
            return cast.avatar_urls;
        }
        if (currentAvatarPaths) {
            return currentAvatarPaths.split(',').map(path => `/storage/${path.trim()}`);
        }
        return [];
    };

    const currentAvatars = getCurrentAvatarUrls();
    const allAvatars = [...currentAvatars, ...uploadedAvatars];

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            const files = Array.from(e.target.files);
            setSelectedFiles(prev => [...prev, ...files]);

            // Create preview URLs
            const newPreviewUrls = files.map(file => URL.createObjectURL(file));
            setUploadedAvatars(prev => [...prev, ...newPreviewUrls]);
        }
    };

    const handleAvatarUpload = async () => {
        if (selectedFiles.length === 0) return;

        setIsUploading(true);
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('avatars[]', file);
        });

        try {
            const response = await fetch('/admin/casts/upload-avatar', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                const result = await response.json();
                // Update the avatar field with new paths
                const newPaths = result.paths;
                const currentPaths = currentAvatarPaths ? currentAvatarPaths.split(',') : [];
                const updatedPaths = [...currentPaths, ...newPaths];
                const newAvatarString = updatedPaths.join(',');
                setData('avatar', newAvatarString);
                setCurrentAvatarPaths(newAvatarString);

                // Clear selected files and previews
                setSelectedFiles([]);
                setUploadedAvatars([]);

                // Show success message
                alert(`${result.message}`);
            } else {
                const errorData = await response.json();
                console.error('Upload failed:', errorData);
                alert('アップロードに失敗しました。ファイルサイズや形式を確認してください。');
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('アップロード中にエラーが発生しました。');
        } finally {
            setIsUploading(false);
        }
    };

    const handleDeleteAvatar = async (index: number) => {
        // Show confirmation dialog
        const isConfirmed = window.confirm('このアバターを削除しますか？この操作は取り消せません。');
        if (!isConfirmed) return;

        if (index < currentAvatars.length) {
            // Delete existing avatar via API
            try {
                const response = await fetch(`/admin/casts/${cast.id}/avatar`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ avatar_index: index }),
                });

                if (response.ok) {
                    const result = await response.json();
                    // Update both the form data and the current avatar paths state
                    const newAvatarString = result.remaining_avatars.join(',');
                    setData('avatar', newAvatarString);
                    setCurrentAvatarPaths(newAvatarString);
                    // Show success message
                    alert('アバターが正常に削除されました。');
                } else {
                    console.error('Delete failed');
                    alert('アバターの削除に失敗しました。');
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('アバターの削除中にエラーが発生しました。');
            }
        } else {
            // Delete uploaded preview
            const previewIndex = index - currentAvatars.length;
            setSelectedFiles(prev => prev.filter((_, i) => i !== previewIndex));
            setUploadedAvatars(prev => prev.filter((_, i) => i !== previewIndex));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/casts/${cast.id}`);
    };

    const getDisplayName = (cast: Cast) => {
        return cast.nickname || cast.phone || `キャスト${cast.id}`;
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'キャスト一覧', href: '/admin/casts' },
            { title: getDisplayName(cast), href: `/admin/casts/${cast.id}` },
            { title: '編集', href: `/admin/casts/${cast.id}/edit` }
        ]}>
            <Head title={`${getDisplayName(cast)} - 編集`} />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Link href={`/admin/casts/${cast.id}`}>
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">キャスト編集</h1>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* アバター管理 */}
                    <div className="lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle>アバター管理</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* 現在のアバター表示 */}
                                <div className="space-y-2">
                                    <Label>現在のアバター</Label>
                                    <div className="flex justify-center">
                                        <AvatarSlider
                                            avatars={allAvatars}
                                            fallbackText={getDisplayName(cast)[0]}
                                            size="xl"
                                            shape="rectangle"
                                            showNavigation={true}
                                            showDots={true}
                                            autoPlay={false}
                                        />
                                    </div>
                                </div>

                                {/* アバター一覧 */}
                                {allAvatars.length > 0 && (
                                    <div className="space-y-2">
                                        <Label>アバター一覧</Label>
                                        <div className="grid grid-cols-2 gap-3">
                                            {allAvatars.map((avatar, index) => (
                                                <div key={index} className="relative group">
                                                    <img
                                                        src={avatar}
                                                        alt={`Avatar ${index + 1}`}
                                                        className="w-full h-24 object-cover rounded-lg border"
                                                    />
                                                    <button
                                                        onClick={() => handleDeleteAvatar(index)}
                                                        className="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                                    >
                                                        <X className="w-3 h-3" />
                                                    </button>
                                                    <span className="absolute bottom-1 left-1 bg-black/50 text-white text-xs px-1 rounded">
                                                        {index + 1}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* アップロード機能 */}
                                <div className="space-y-2">
                                    <Label htmlFor="avatar-upload">新しいアバターを追加</Label>
                                    <div
                                        className="border-2 border-dashed border-muted-foreground/25 rounded-lg p-4 text-center hover:border-muted-foreground/50 transition-colors"
                                        onDragOver={(e) => {
                                            e.preventDefault();
                                            e.currentTarget.classList.add('border-primary/50', 'bg-primary/5');
                                        }}
                                        onDragLeave={(e) => {
                                            e.preventDefault();
                                            e.currentTarget.classList.remove('border-primary/50', 'bg-primary/5');
                                        }}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            e.currentTarget.classList.remove('border-primary/50', 'bg-primary/5');
                                            const files = Array.from(e.dataTransfer.files);
                                            if (files.length > 0) {
                                                setSelectedFiles(prev => [...prev, ...files]);
                                                const newPreviewUrls = files.map(file => URL.createObjectURL(file));
                                                setUploadedAvatars(prev => [...prev, ...newPreviewUrls]);
                                            }
                                        }}
                                    >
                                        <Upload className="w-8 h-8 mx-auto mb-2 text-muted-foreground" />
                                        <p className="text-sm text-muted-foreground mb-2">
                                            ファイルをドラッグ&ドロップするか、下のボタンをクリックしてください
                                        </p>
                                        <div className="flex gap-2 justify-center">
                                            <Input
                                                id="avatar-upload"
                                                type="file"
                                                multiple
                                                accept="image/*"
                                                hidden
                                                onChange={handleFileSelect}
                                                className="max-w-xs"
                                            />
                                            {selectedFiles.length > 0 && (
                                                <Button
                                                    onClick={handleAvatarUpload}
                                                    disabled={isUploading}
                                                    size="sm"
                                                >
                                                    <Upload className="w-4 h-4 mr-1" />
                                                    {isUploading ? 'アップロード中...' : 'アップロード'}
                                                </Button>
                                            )}
                                        </div>
                                        {selectedFiles.length > 0 && (
                                            <p className="text-sm text-muted-foreground mt-2">
                                                {selectedFiles.length}個の画像が選択されました
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* メインフォーム */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>キャスト情報編集</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {/* 基本情報 */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-medium">基本情報</h3>

                                            <div className="space-y-2">
                                                <Label htmlFor="nickname">ニックネーム</Label>
                                                <Input
                                                    id="nickname"
                                                    value={data.nickname}
                                                    onChange={e => setData('nickname', e.target.value)}
                                                    placeholder="ニックネーム"
                                                />
                                                {errors.nickname && (
                                                    <p className="text-sm text-red-500">{errors.nickname}</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="phone">電話番号</Label>
                                                <Input
                                                    id="phone"
                                                    value={data.phone}
                                                    onChange={e => setData('phone', e.target.value)}
                                                    placeholder="090-1234-5678"
                                                />
                                                {errors.phone && (
                                                    <p className="text-sm text-red-500">{errors.phone}</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="line_id">LINE ID</Label>
                                                <Input
                                                    id="line_id"
                                                    value={data.line_id}
                                                    onChange={e => setData('line_id', e.target.value)}
                                                    placeholder="line_id"
                                                />
                                                {errors.line_id && (
                                                    <p className="text-sm text-red-500">{errors.line_id}</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="status">ステータス</Label>
                                                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="ステータスを選択" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="active">アクティブ</SelectItem>
                                                        <SelectItem value="inactive">非アクティブ</SelectItem>
                                                        <SelectItem value="suspended">一時停止</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {errors.status && (
                                                    <p className="text-sm text-red-500">{errors.status}</p>
                                                )}
                                            </div>
                                        </div>

                                        {/* 詳細情報 */}
                                        <div className="space-y-4">
                                            <h3 className="text-lg font-medium">詳細情報</h3>

                                            <div className="space-y-2">
                                                <Label htmlFor="birth_year">生年</Label>
                                                <Input
                                                    id="birth_year"
                                                    type="number"
                                                    value={data.birth_year}
                                                    onChange={e => setData('birth_year', e.target.value)}
                                                    placeholder="1990"
                                                    min="1900"
                                                    max={new Date().getFullYear() - 18}
                                                />
                                                {errors.birth_year && (
                                                    <p className="text-sm text-red-500">{errors.birth_year}</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="height">身長 (cm)</Label>
                                                <Input
                                                    id="height"
                                                    type="number"
                                                    value={data.height}
                                                    onChange={e => setData('height', e.target.value)}
                                                    placeholder="160"
                                                    min="100"
                                                    max="250"
                                                />
                                                {errors.height && (
                                                    <p className="text-sm text-red-500">{errors.height}</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="residence">居住地</Label>
                                                <Input
                                                    id="residence"
                                                    value={data.residence}
                                                    onChange={e => setData('residence', e.target.value)}
                                                    placeholder="東京都渋谷区"
                                                />
                                                {errors.residence && (
                                                    <p className="text-sm text-red-500">{errors.residence}</p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="birthplace">出身地</Label>
                                                <Input
                                                    id="birthplace"
                                                    value={data.birthplace}
                                                    onChange={e => setData('birthplace', e.target.value)}
                                                    placeholder="東京都"
                                                />
                                                {errors.birthplace && (
                                                    <p className="text-sm text-red-500">{errors.birthplace}</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* グレード情報 */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="grade">グレード</Label>
                                            <Input
                                                id="grade"
                                                value={data.grade}
                                                onChange={e => setData('grade', e.target.value)}
                                                placeholder="A"
                                            />
                                            {errors.grade && (
                                                <p className="text-sm text-red-500">{errors.grade}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="grade_points">グレードポイント</Label>
                                            <Input
                                                id="grade_points"
                                                type="number"
                                                value={data.grade_points}
                                                onChange={e => setData('grade_points', e.target.value)}
                                                placeholder="100"
                                                min="0"
                                            />
                                            {errors.grade_points && (
                                                <p className="text-sm text-red-500">{errors.grade_points}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* プロフィール */}
                                    <div className="space-y-2">
                                        <Label htmlFor="profile_text">プロフィール</Label>
                                        <textarea
                                            id="profile_text"
                                            value={data.profile_text}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('profile_text', e.target.value)}
                                            placeholder="自己紹介を入力してください"
                                            rows={4}
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        {errors.profile_text && (
                                            <p className="text-sm text-red-500">{errors.profile_text}</p>
                                        )}
                                    </div>

                                    {/* 支払い情報 */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="payjp_customer_id">PayJP顧客ID</Label>
                                            <Input
                                                id="payjp_customer_id"
                                                value={data.payjp_customer_id}
                                                onChange={e => setData('payjp_customer_id', e.target.value)}
                                                placeholder="cus_xxxxxxxxxx"
                                            />
                                            {errors.payjp_customer_id && (
                                                <p className="text-sm text-red-500">{errors.payjp_customer_id}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="points">ポイント</Label>
                                            <Input
                                                id="points"
                                                type="number"
                                                value={data.points}
                                                onChange={e => setData('points', e.target.value)}
                                                placeholder="0"
                                                min="0"
                                            />
                                            {errors.points && (
                                                <p className="text-sm text-red-500">{errors.points}</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* 支払い情報詳細 */}
                                    <div className="space-y-2">
                                        <Label htmlFor="payment_info">支払い情報詳細</Label>
                                        <textarea
                                            id="payment_info"
                                            value={data.payment_info}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setData('payment_info', e.target.value)}
                                            placeholder="支払い情報の詳細（JSON形式など）"
                                            rows={3}
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        {errors.payment_info && (
                                            <p className="text-sm text-red-500">{errors.payment_info}</p>
                                        )}
                                    </div>

                                    {/* 送信ボタン */}
                                    <div className="flex justify-end gap-4">
                                        <Link href={`/admin/casts/${cast.id}`}>
                                            <Button type="button" variant="outline">
                                                キャンセル
                                            </Button>
                                        </Link>
                                        <Button type="submit" disabled={processing}>
                                            <Save className="w-4 h-4 mr-2" />
                                            {processing ? '更新中...' : '更新'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
