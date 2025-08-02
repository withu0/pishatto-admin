import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ArrowLeft, Download, ZoomIn } from 'lucide-react';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';

interface Guest {
    id: number;
    phone?: string;
    line_id?: string;
    nickname?: string;
    age?: string;
    shiatsu?: string;
    location?: string;
    avatar?: string;
    birth_year?: number;
    height?: number;
    residence?: string;
    birthplace?: string;
    annual_income?: string;
    education?: string;
    occupation?: string;
    alcohol?: string;
    tobacco?: string;
    siblings?: string;
    cohabitant?: string;
    pressure?: 'weak' | 'medium' | 'strong';
    favorite_area?: string;
    interests?: (string | { category: string; tag: string })[];
    payjp_customer_id?: string;
    payment_info?: string;
    points: number;
    identity_verification_completed?: 'pending' | 'success' | 'failed';
    identity_verification?: string;
    status?: 'active' | 'inactive' | 'suspended';
    created_at: string;
    updated_at: string;
}

interface Props {
    guest: Guest;
}

export default function GuestEdit({ guest }: Props) {
    const [formData, setFormData] = useState({
        phone: guest.phone || '',
        line_id: guest.line_id || '',
        nickname: guest.nickname || '',
        age: guest.age || '',
        shiatsu: guest.shiatsu || '',
        location: guest.location || '',
        birth_year: guest.birth_year?.toString() || '',
        height: guest.height?.toString() || '',
        residence: guest.residence || '',
        birthplace: guest.birthplace || '',
        annual_income: guest.annual_income || '',
        education: guest.education || '',
        occupation: guest.occupation || '',
        alcohol: guest.alcohol || '',
        tobacco: guest.tobacco || '',
        siblings: guest.siblings || '',
        cohabitant: guest.cohabitant || '',
        pressure: guest.pressure || '',
        favorite_area: guest.favorite_area || '',
        interests: guest.interests || [],
        payjp_customer_id: guest.payjp_customer_id || '',
        payment_info: guest.payment_info || '',
        points: guest.points?.toString() || '0',
        identity_verification_completed: guest.identity_verification_completed || 'failed',
        identity_verification: guest.identity_verification || '',
        status: guest.status || 'active',
    });

    const [interestsInput, setInterestsInput] = useState(() => {
        if (!guest.interests || guest.interests.length === 0) return '';
        
        return guest.interests.map(interest => {
            if (typeof interest === 'string') {
                return interest;
            } else if (typeof interest === 'object' && interest.category && interest.tag) {
                return `${interest.category}: ${interest.tag}`;
            } else {
                return JSON.stringify(interest);
            }
        }).join(', ');
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        
        try {
            await router.put(`/admin/guests/${guest.id}`, formData, {
                onSuccess: () => {
                    toast.success('ゲストが正常に更新されました');
                },
                onError: (errors) => {
                    toast.error('ゲストの更新に失敗しました');
                    console.error('Update error:', errors);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                }
            });
        } catch (error) {
            toast.error('ゲストの更新に失敗しました');
            setIsSubmitting(false);
        }
    };

    const handleChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const handleInterestsChange = (value: string) => {
        setInterestsInput(value);
        // For now, treat all interests as simple strings
        // In a more complex implementation, you might want to parse category:tag format
        const interestsArray = value.split(',').map(interest => interest.trim()).filter(interest => interest.length > 0);
        setFormData(prev => ({ ...prev, interests: interestsArray }));
    };

    const getDisplayName = (guest: Guest) => {
        return guest.nickname || guest.phone || `ゲスト${guest.id}`;
    };

    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [selectedImageUrl, setSelectedImageUrl] = useState<string | null>(null);

    const handleImageClick = (url: string) => {
        setSelectedImageUrl(url);
        setIsDialogOpen(true);
    };

    const handleCloseDialog = () => {
        setIsDialogOpen(false);
        setSelectedImageUrl(null);
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'ゲスト一覧', href: '/admin/guests' },
            { title: getDisplayName(guest), href: `/admin/guests/${guest.id}` },
            { title: '編集', href: `/admin/guests/${guest.id}/edit` }
        ]}>
            <Head title={`${getDisplayName(guest)} - 編集`} />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Link href={`/admin/guests/${guest.id}`}>
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">ゲスト編集</h1>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>基本情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="phone">電話番号 *</Label>
                                    <Input
                                        id="phone"
                                        value={formData.phone}
                                        onChange={(e) => handleChange('phone', e.target.value)}
                                        placeholder="090-1234-5678"
                                        required
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="line_id">LINE ID</Label>
                                    <Input
                                        id="line_id"
                                        value={formData.line_id}
                                        onChange={(e) => handleChange('line_id', e.target.value)}
                                        placeholder="line_id"
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="nickname">ニックネーム</Label>
                                    <Input
                                        id="nickname"
                                        value={formData.nickname}
                                        onChange={(e) => handleChange('nickname', e.target.value)}
                                        placeholder="ニックネーム"
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="birth_year">生年</Label>
                                    <Input
                                        id="birth_year"
                                        type="number"
                                        value={formData.birth_year}
                                        onChange={(e) => handleChange('birth_year', e.target.value)}
                                        placeholder="1990"
                                        min="1900"
                                        max={new Date().getFullYear() - 18}
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="height">身長 (cm)</Label>
                                    <Input
                                        id="height"
                                        type="number"
                                        value={formData.height}
                                        onChange={(e) => handleChange('height', e.target.value)}
                                        placeholder="170"
                                        min="100"
                                        max="250"
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="residence">居住地</Label>
                                    <Select value={formData.residence} onValueChange={(value) => handleChange('residence', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="居住地を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="埼玉県">埼玉県</SelectItem>
                                            <SelectItem value="千葉県">千葉県</SelectItem>
                                            <SelectItem value="東京都">東京都</SelectItem>
                                            <SelectItem value="神奈川県">神奈川県</SelectItem>
                                            <SelectItem value="新潟県">新潟県</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="birthplace">出身地</Label>
                                    <Select value={formData.birthplace} onValueChange={(value) => handleChange('birthplace', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="出身地を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="群馬県">群馬県</SelectItem>
                                            <SelectItem value="埼玉県">埼玉県</SelectItem>
                                            <SelectItem value="千葉県">千葉県</SelectItem>
                                            <SelectItem value="東京都">東京都</SelectItem>
                                            <SelectItem value="神奈川県">神奈川県</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="favorite_area">好みのエリア</Label>
                                    <Input
                                        id="favorite_area"
                                        value={formData.favorite_area}
                                        onChange={(e) => handleChange('favorite_area', e.target.value)}
                                        placeholder="好みのエリア"
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="interests">興味・関心 (カンマ区切り)</Label>
                                    <Textarea
                                        id="interests"
                                        value={interestsInput}
                                        onChange={(e) => handleInterestsChange(e.target.value)}
                                        placeholder="旅行, スポーツ, 音楽"
                                        rows={3}
                                        disabled={isSubmitting}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        複数の興味・関心をカンマ区切りで入力してください
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>詳細情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="education">学歴</Label>
                                    <Select value={formData.education} onValueChange={(value) => handleChange('education', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="学歴を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="高校卒">高校卒</SelectItem>
                                            <SelectItem value="大学卒">大学卒</SelectItem>
                                            <SelectItem value="大学院卒">大学院卒</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="annual_income">年収</Label>
                                    <Select value={formData.annual_income} onValueChange={(value) => handleChange('annual_income', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="年収を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="200万未満">200万未満</SelectItem>
                                            <SelectItem value="200万〜400万">200万〜400万</SelectItem>
                                            <SelectItem value="400万〜600万">400万〜600万</SelectItem>
                                            <SelectItem value="600万〜800万">600万〜800万</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="occupation">お仕事</Label>
                                    <Select value={formData.occupation} onValueChange={(value) => handleChange('occupation', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="お仕事を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="会社員">会社員</SelectItem>
                                            <SelectItem value="医者">医者</SelectItem>
                                            <SelectItem value="弁護士">弁護士</SelectItem>
                                            <SelectItem value="公認会計士">公認会計士</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="alcohol">お酒</Label>
                                    <Select value={formData.alcohol} onValueChange={(value) => handleChange('alcohol', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="お酒を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="飲まない">飲まない</SelectItem>
                                            <SelectItem value="飲む">飲む</SelectItem>
                                            <SelectItem value="ときどき飲む">ときどき飲む</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="tobacco">タバコ</Label>
                                    <Select value={formData.tobacco} onValueChange={(value) => handleChange('tobacco', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="タバコを選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="吸わない">吸わない</SelectItem>
                                            <SelectItem value="吸う（電子タバコ）">吸う（電子タバコ）</SelectItem>
                                            <SelectItem value="吸う（紙巻きたばこ）">吸う（紙巻きたばこ）</SelectItem>
                                            <SelectItem value="ときどき吸う">ときどき吸う</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="siblings">兄弟姉妹</Label>
                                    <Select value={formData.siblings} onValueChange={(value) => handleChange('siblings', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="兄弟姉妹を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="長男">長男</SelectItem>
                                            <SelectItem value="次男">次男</SelectItem>
                                            <SelectItem value="三男">三男</SelectItem>
                                            <SelectItem value="その他">その他</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="cohabitant">同居人</Label>
                                    <Select value={formData.cohabitant} onValueChange={(value) => handleChange('cohabitant', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="同居人を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="一人暮らし">一人暮らし</SelectItem>
                                            <SelectItem value="家族と同居">家族と同居</SelectItem>
                                            <SelectItem value="ペットと一緒">ペットと一緒</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="pressure">好みの圧力</Label>
                                    <Select value={formData.pressure} onValueChange={(value) => handleChange('pressure', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="圧力を選択" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="weak">弱い</SelectItem>
                                            <SelectItem value="medium">普通</SelectItem>
                                            <SelectItem value="strong">強い</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="points">ポイント</Label>
                                    <Input
                                        id="points"
                                        type="number"
                                        value={formData.points}
                                        onChange={(e) => handleChange('points', e.target.value)}
                                        placeholder="0"
                                        min="0"
                                        disabled={isSubmitting}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="identity_verification_completed">本人確認状態</Label>
                                    <Select value={formData.identity_verification_completed} onValueChange={(value) => handleChange('identity_verification_completed', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">認証中</SelectItem>
                                            <SelectItem value="success">認証済み</SelectItem>
                                            <SelectItem value="failed">認証失敗</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {guest.identity_verification && (
                                    <div className="space-y-2">
                                        <Label>提出された身分証明書</Label>
                                        <div className="relative">
                                            <img
                                                src={`/storage/${guest.identity_verification}`}
                                                alt="身分証明書"
                                                className="w-full max-w-md h-auto rounded-lg border shadow-sm cursor-pointer"
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    handleImageClick(`/storage/${guest.identity_verification}`);
                                                }}
                                                onError={(e) => {
                                                    e.currentTarget.style.display = 'none';
                                                    e.currentTarget.nextElementSibling?.classList.remove('hidden');
                                                }}
                                            />
                                            <div className="hidden text-center p-4 text-muted-foreground text-sm border rounded-lg">
                                                画像を読み込めませんでした
                                            </div>
                                            <div className="absolute top-2 right-2 flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        handleImageClick(`/storage/${guest.identity_verification}`);
                                                    }}
                                                    className="h-8 w-8 p-0"
                                                >
                                                    <ZoomIn className="w-4 h-4" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    type="button"
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        const link = document.createElement('a');
                                                        link.href = `/storage/${guest.identity_verification}`;
                                                        link.download = `guest_${guest.id}_id_card.jpg`;
                                                        document.body.appendChild(link);
                                                        link.click();
                                                        document.body.removeChild(link);
                                                    }}
                                                    className="h-8 w-8 p-0"
                                                >
                                                    <Download className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="status">ステータス</Label>
                                    <Select value={formData.status} onValueChange={(value) => handleChange('status', value)} disabled={isSubmitting}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">アクティブ</SelectItem>
                                            <SelectItem value="inactive">非アクティブ</SelectItem>
                                            <SelectItem value="suspended">一時停止</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-6 flex gap-4">
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? '更新中...' : '更新'}
                        </Button>
                        <Link href={`/admin/guests/${guest.id}`}>
                            <Button type="button" variant="outline" disabled={isSubmitting}>キャンセル</Button>
                        </Link>
                    </div>
                </form>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-w-[80vw] max-h-[80vh]">
                    <DialogHeader>
                        <DialogTitle>身分証明書</DialogTitle>
                    </DialogHeader>
                    {selectedImageUrl && (
                        <div className="flex justify-center items-center">
                            <img
                                src={selectedImageUrl}
                                alt="身分証明書"
                                className="max-w-full max-h-[80vh] object-contain rounded-lg"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
