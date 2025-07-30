import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';

export default function GuestCreate() {
    const [formData, setFormData] = useState({
        phone: '',
        line_id: '',
        nickname: '',
        age: '',
        shiatsu: '',
        location: '',
        birth_year: '',
        height: '',
        residence: '',
        birthplace: '',
        annual_income: '',
        education: '',
        occupation: '',
        alcohol: '',
        tobacco: '',
        siblings: '',
        cohabitant: '',
        pressure: '',
        favorite_area: '',
        payjp_customer_id: '',
        payment_info: '',
        points: '0',
        identity_verification_completed: 'failed',
        identity_verification: '',
        status: 'active',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/admin/guests', formData);
    };

    const handleChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'ゲスト一覧', href: '/admin/guests' },
            { title: '新規作成', href: '/admin/guests/create' }
        ]}>
            <Head title="ゲスト新規作成" />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/admin/guests">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">ゲスト新規作成</h1>
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
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="line_id">LINE ID</Label>
                                    <Input
                                        id="line_id"
                                        value={formData.line_id}
                                        onChange={(e) => handleChange('line_id', e.target.value)}
                                        placeholder="line_id"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="nickname">ニックネーム</Label>
                                    <Input
                                        id="nickname"
                                        value={formData.nickname}
                                        onChange={(e) => handleChange('nickname', e.target.value)}
                                        placeholder="ニックネーム"
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
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="residence">居住地</Label>
                                    <Select value={formData.residence} onValueChange={(value) => handleChange('residence', value)}>
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
                                    <Select value={formData.birthplace} onValueChange={(value) => handleChange('birthplace', value)}>
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
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>詳細情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="education">学歴</Label>
                                    <Select value={formData.education} onValueChange={(value) => handleChange('education', value)}>
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
                                    <Select value={formData.annual_income} onValueChange={(value) => handleChange('annual_income', value)}>
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
                                    <Select value={formData.occupation} onValueChange={(value) => handleChange('occupation', value)}>
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
                                    <Select value={formData.alcohol} onValueChange={(value) => handleChange('alcohol', value)}>
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
                                    <Select value={formData.tobacco} onValueChange={(value) => handleChange('tobacco', value)}>
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
                                    <Select value={formData.siblings} onValueChange={(value) => handleChange('siblings', value)}>
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
                                    <Select value={formData.cohabitant} onValueChange={(value) => handleChange('cohabitant', value)}>
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
                                    <Select value={formData.pressure} onValueChange={(value) => handleChange('pressure', value)}>
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
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="identity_verification_completed">本人確認状態</Label>
                                    <Select value={formData.identity_verification_completed} onValueChange={(value) => handleChange('identity_verification_completed', value)}>
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

                                <div className="space-y-2">
                                    <Label htmlFor="status">ステータス</Label>
                                    <Select value={formData.status} onValueChange={(value) => handleChange('status', value)}>
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
                        <Button type="submit">作成</Button>
                        <Link href="/admin/guests">
                            <Button type="button" variant="outline">キャンセル</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
