import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';

export default function CastCreate() {
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [uploadedAvatars, setUploadedAvatars] = useState<string[]>([]);

    const { data, setData, post, processing, errors } = useForm({
        phone: '',
        line_id: '',
        nickname: '',
        avatar: null as string | null,
        avatars: null as string[] | null,
        status: 'active',
        birth_year: '',
        height: '',
        grade: '',
        grade_points: '',
        residence: '',
        birthplace: '',
        profile_text: '',
        payjp_customer_id: '',
        payment_info: '',
        points: '0',
    });

    const handleSubmit = async(e: React.FormEvent) => {
        e.preventDefault();

        // Upload avatars first if files are selected
        if (selectedFiles.length > 0) {
            try {
                const formData = new FormData();
                for (let i = 0; i < selectedFiles.length; i++) {
                    formData.append('avatars[]', selectedFiles[i]);
                }

                // Get CSRF token from meta tag
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const response = await fetch('/admin/casts/upload-avatar', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken || '',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    const result = await response.json();
                    setUploadedAvatars(result.paths);
                    setData('avatars', result.paths);
                } else {
                    const errorData = await response.json().catch(() => ({}));
                    console.error('Upload failed:', errorData);
                    alert('アバター画像のアップロードに失敗しました');
                    return; // Don't proceed with form submission
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('通信エラーが発生しました');
                return; // Don't proceed with form submission
            }
        }

        // Submit the form
        post('/admin/casts');
    };

    const handleAvatar = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (!files || files.length === 0) return;

        // Store selected files for later upload
        const fileArray = Array.from(files);
        setSelectedFiles(fileArray);

        // Create preview URLs for display
        const previewUrls = fileArray.map(file => URL.createObjectURL(file));
        setUploadedAvatars(previewUrls);
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'キャスト一覧', href: '/admin/casts' },
            { title: '新規作成', href: '/admin/casts/create' }
        ]}>
            <Head title="キャスト新規作成" />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/admin/casts">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">キャスト新規作成</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>キャスト情報</CardTitle>
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

                                    <div className="space-y-2">
                                        <Label htmlFor="avatar">アバター画像 (複数選択可能)</Label>
                                        <Input
                                            id="avatar"
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            onChange={handleAvatar}
                                        />
                                        {errors.avatar && (
                                            <p className="text-sm text-red-500">{errors.avatar}</p>
                                        )}

                                        {/* Avatar Preview */}
                                        {uploadedAvatars.length > 0 && (
                                            <div className="mt-4">
                                                <Label className="text-sm font-medium">
                                                    {selectedFiles.length > 0 ? '選択された画像 (保存時にアップロードされます):' : 'アップロード済み画像:'}
                                                </Label>
                                                <div className="flex flex-wrap gap-2 mt-2">
                                                    {uploadedAvatars.map((path, index) => (
                                                        <div key={index} className="relative">
                                                            <img
                                                                src={path}
                                                                alt={`Avatar ${index + 1}`}
                                                                className="w-16 h-16 object-cover rounded border"
                                                            />
                                                            <span className="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                                                {index + 1}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
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
                                            placeholder="東京都/渋谷区"
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
                                    <Label htmlFor="grade">ランク</Label>
                                    <Input
                                        id="grade"
                                        value={data.grade}
                                        onChange={e => setData('grade', e.target.value)}
                                        placeholder="プレミアム/VIP/ロイヤルVIP"
                                    />
                                    {errors.grade && (
                                        <p className="text-sm text-red-500">{errors.grade}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="grade_points">30分あたりのポイント</Label>
                                    <Input
                                        id="grade_points"
                                        type="number"
                                        value={data.grade_points}
                                        onChange={e => setData('grade_points', e.target.value)}
                                        placeholder="10,000"
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
                                        disabled
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

                            {/* 送信ボタン */}
                            <div className="flex justify-end gap-4">
                                <Link href="/admin/casts">
                                    <Button type="button" variant="outline">
                                        キャンセル
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    <Save className="w-4 h-4 mr-2" />
                                    {processing ? '作成中...' : '作成'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
