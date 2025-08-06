import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Edit, ArrowLeft, MapPin, Calendar, Phone, MessageCircle, Gift, Heart, Users, CreditCard, Shield, Download, ZoomIn, Trophy, TrendingUp } from 'lucide-react';
import { useState } from 'react';

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
    interests?: string[] | Array<{ category: string; tag: string }>;
    payjp_customer_id?: string;
    payment_info?: string;
    points: number;
    grade?: string;
    grade_points?: number;
    grade_updated_at?: string;
    identity_verification_completed?: 'pending' | 'success' | 'failed';
    identity_verification?: string;
    status?: 'active' | 'inactive' | 'suspended';
    created_at: string;
    updated_at: string;
    reservations?: Array<{ id: number; scheduled_at: string; status: string }>;
    sent_gifts?: Array<{ id: number; gift: { name: string; points: number } }>;
    favorites?: Array<{ id: number; nickname?: string }>;
    point_transactions?: Array<{ id: number; amount: number; type: string; created_at: string }>;
    feedback?: Array<{ id: number; rating: number; comment: string; created_at: string }>;
}

interface Props {
    guest: Guest;
}

export default function GuestShow({ guest }: Props) {
    const getDisplayName = (guest: Guest) => {
        return guest.nickname || guest.phone || `ゲスト${guest.id}`;
    };

    const getAge = (birthYear?: number) => {
        if (!birthYear) return null;
        return new Date().getFullYear() - birthYear;
    };

    const getVerificationBadge = (status?: string) => {
        switch (status) {
            case 'success':
                return <Badge variant="default">認証済み</Badge>;
            case 'pending':
                return <Badge variant="secondary">認証中</Badge>;
            case 'failed':
                return <Badge variant="destructive">認証失敗</Badge>;
            default:
                return <Badge variant="outline">未認証</Badge>;
        }
    };

    const getStatusBadge = (status?: string) => {
        switch (status) {
            case 'active':
                return <Badge variant="default">アクティブ</Badge>;
            case 'inactive':
                return <Badge variant="secondary">非アクティブ</Badge>;
            case 'suspended':
                return <Badge variant="destructive">一時停止</Badge>;
            default:
                return <Badge variant="outline">未設定</Badge>;
        }
    };

    const getGradeBadge = (grade?: string) => {
        const gradeNames: { [key: string]: string } = {
            'green': 'グリーン',
            'orange': 'オレンジ',
            'bronze': 'ブロンズ',
            'silver': 'シルバー',
            'gold': 'ゴールド',
            'platinum': 'プラチナ',
            'centurion': 'センチュリオン',
        };

        const gradeColors: { [key: string]: string } = {
            'green': 'bg-green-500',
            'orange': 'bg-orange-500',
            'bronze': 'bg-amber-600',
            'silver': 'bg-gray-400',
            'gold': 'bg-yellow-500',
            'platinum': 'bg-purple-500',
            'centurion': 'bg-yellow-600',
        };

        const gradeName = gradeNames[grade || 'green'] || 'グリーン';
        const gradeColor = gradeColors[grade || 'green'] || 'bg-green-500';

        return (
            <Badge className={`${gradeColor} text-white`}>
                {gradeName}
            </Badge>
        );
    };

    const getNextGradeInfo = (currentGrade?: string, gradePoints?: number) => {
        const gradeThresholds: { [key: string]: number } = {
            'green': 0,
            'orange': 100000,
            'bronze': 300000,
            'silver': 500000,
            'gold': 1000000,
            'platinum': 6000000,
            'centurion': 30000000,
        };

        const gradeNames: { [key: string]: string } = {
            'green': 'グリーン',
            'orange': 'オレンジ',
            'bronze': 'ブロンズ',
            'silver': 'シルバー',
            'gold': 'ゴールド',
            'platinum': 'プラチナ',
            'centurion': 'センチュリオン',
        };

        const grades = ['green', 'orange', 'bronze', 'silver', 'gold', 'platinum', 'centurion'];
        const currentGradeIndex = grades.indexOf(currentGrade || 'green');
        const nextGrade = grades[currentGradeIndex + 1];
        
        if (!nextGrade) return null;

        const currentPoints = gradePoints || 0;
        const nextThreshold = gradeThresholds[nextGrade];
        const pointsNeeded = nextThreshold - currentPoints;

        return {
            nextGrade,
            nextGradeName: gradeNames[nextGrade],
            pointsNeeded,
            progress: Math.min((currentPoints / nextThreshold) * 100, 100),
        };
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

    const nextGradeInfo = getNextGradeInfo(guest.grade, guest.grade_points);

    return (
        <AppLayout breadcrumbs={[
            { title: 'ゲスト一覧', href: '/admin/guests' },
            { title: getDisplayName(guest), href: `/admin/guests/${guest.id}` }
        ]}>
            <Head title={`${getDisplayName(guest)} - ゲスト詳細`} />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/guests">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">ゲスト詳細</h1>
                    </div>
                    <Link href={`/admin/guests/${guest.id}/edit`}>
                        <Button>
                            <Edit className="w-4 h-4 mr-2" />
                            編集
                        </Button>
                    </Link>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-4">
                                    <Avatar className="w-16 h-16">
                                        <AvatarImage src={guest.avatar ? `/storage/${guest.avatar}` : undefined} />
                                        <AvatarFallback className="text-lg">{getDisplayName(guest)[0]}</AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div className="text-xl font-bold">{getDisplayName(guest)}</div>
                                        <div className="text-sm text-muted-foreground">ID: {guest.id}</div>
                                    </div>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-4">
                                    {getVerificationBadge(guest.identity_verification_completed)}
                                    {getStatusBadge(guest.status)}
                                    <Badge variant="secondary">{guest.points.toLocaleString()} pt</Badge>
                                    {getGradeBadge(guest.grade)}
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Phone className="w-4 h-4 text-muted-foreground" />
                                            <span className="font-medium">電話番号:</span>
                                            <span>{guest.phone || '未設定'}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <MessageCircle className="w-4 h-4 text-muted-foreground" />
                                            <span className="font-medium">LINE ID:</span>
                                            <span>{guest.line_id || '未設定'}</span>
                                        </div>
                                        {guest.birth_year && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <Calendar className="w-4 h-4 text-muted-foreground" />
                                                <span className="font-medium">年齢:</span>
                                                <span>{getAge(guest.birth_year)}歳 ({guest.birth_year}年生まれ)</span>
                                            </div>
                                        )}
                                        {guest.height && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">身長:</span>
                                                <span>{guest.height}cm</span>
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        {guest.residence && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <MapPin className="w-4 h-4 text-muted-foreground" />
                                                <span className="font-medium">居住地:</span>
                                                <span>{guest.residence}</span>
                                            </div>
                                        )}
                                        {guest.birthplace && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <MapPin className="w-4 h-4 text-muted-foreground" />
                                                <span className="font-medium">出身地:</span>
                                                <span>{guest.birthplace}</span>
                                            </div>
                                        )}
                                        {guest.occupation && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">職業:</span>
                                                <span>{guest.occupation}</span>
                                            </div>
                                        )}
                                        {guest.favorite_area && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">好みのエリア:</span>
                                                <span>{guest.favorite_area}</span>
                                            </div>
                                        )}
                                        {guest.interests && guest.interests.length > 0 && (
                                            <div className="flex items-start gap-2 text-sm">
                                                <span className="font-medium">興味・関心:</span>
                                                <div className="flex flex-wrap gap-1">
                                                    {guest.interests.map((interest, index) => (
                                                        <Badge key={index} variant="secondary" className="text-xs">
                                                            {typeof interest === 'string' 
                                                                ? interest 
                                                                : typeof interest === 'object' && interest.category && interest.tag
                                                                    ? `${interest.category}: ${interest.tag}`
                                                                    : JSON.stringify(interest)
                                                            }
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Trophy className="w-5 h-5" />
                                    グレード情報
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Trophy className="w-8 h-8 mx-auto mb-2 text-yellow-500" />
                                        <div className="text-lg font-bold">{getGradeBadge(guest.grade)}</div>
                                        <div className="text-sm text-muted-foreground">現在のグレード</div>
                                    </div>
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <TrendingUp className="w-8 h-8 mx-auto mb-2 text-blue-500" />
                                        <div className="text-2xl font-bold">{(guest.grade_points || 0).toLocaleString()}</div>
                                        <div className="text-sm text-muted-foreground">グレードポイント</div>
                                    </div>
                                    {nextGradeInfo && (
                                        <div className="text-center p-4 bg-muted rounded-lg">
                                            <div className="text-lg font-bold">{nextGradeInfo.nextGradeName}</div>
                                            <div className="text-sm text-muted-foreground">次のグレード</div>
                                            <div className="text-xs text-muted-foreground mt-1">
                                                あと {nextGradeInfo.pointsNeeded.toLocaleString()} pt
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {nextGradeInfo && (
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <span>次のグレードまでの進捗</span>
                                            <span>{Math.round(nextGradeInfo.progress)}%</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div 
                                                className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                                style={{ width: `${nextGradeInfo.progress}%` }}
                                            ></div>
                                        </div>
                                    </div>
                                )}

                                {guest.grade_updated_at && (
                                    <div className="text-sm text-muted-foreground">
                                        最終更新: {new Date(guest.grade_updated_at).toLocaleDateString('ja-JP')}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>統計情報</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Calendar className="w-8 h-8 mx-auto mb-2 text-blue-500" />
                                        <div className="text-2xl font-bold">{guest.reservations?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">予約数</div>
                                    </div>
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Gift className="w-8 h-8 mx-auto mb-2 text-green-500" />
                                        <div className="text-2xl font-bold">{guest.sent_gifts?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">送ったギフト</div>
                                    </div>
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Heart className="w-8 h-8 mx-auto mb-2 text-red-500" />
                                        <div className="text-2xl font-bold">{guest.favorites?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">お気に入り</div>
                                    </div>
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Users className="w-8 h-8 mx-auto mb-2 text-purple-500" />
                                        <div className="text-2xl font-bold">{guest.feedback?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">フィードバック</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>登録情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">登録日:</span>
                                    <span>{new Date(guest.created_at).toLocaleDateString('ja-JP')}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">最終更新:</span>
                                    <span>{new Date(guest.updated_at).toLocaleDateString('ja-JP')}</span>
                                </div>
                            </CardContent>
                        </Card>

                        {guest.payjp_customer_id && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="w-4 h-4" />
                                        支払い情報
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">PayJP顧客ID:</span>
                                        <span className="font-mono text-xs">{guest.payjp_customer_id}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="w-4 h-4" />
                                    本人確認
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-between items-center">
                                    <span className="text-muted-foreground">状態:</span>
                                    {getVerificationBadge(guest.identity_verification_completed)}
                                </div>

                                {guest.identity_verification && (
                                    <div className="space-y-2">
                                        <span className="text-sm font-medium">提出された身分証明書:</span>
                                        <div className="relative">
                                            <img 
                                                src={`/storage/${guest.identity_verification}`}
                                                alt="身分証明書"
                                                className="w-full max-w-md h-auto rounded-lg border shadow-sm cursor-pointer"
                                                onClick={() => handleImageClick(`/storage/${guest.identity_verification}`)}
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
                                                    onClick={() => handleImageClick(`/storage/${guest.identity_verification}`)}
                                                    className="h-8 w-8 p-0"
                                                >
                                                    <ZoomIn className="w-4 h-4" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() => {
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
                            </CardContent>
                        </Card>
                    </div>
                </div>
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
