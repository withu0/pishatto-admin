import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { AvatarSlider } from '@/components/ui/avatar-slider';
import { Edit, ArrowLeft, MapPin, Calendar, Phone, MessageCircle, Gift, Heart, Users } from 'lucide-react';

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
    stripe_customer_id?: string;
    payjp_customer_id?: string; // Keep for backward compatibility
    payment_info?: string;
    points: number;
    created_at: string;
    updated_at: string;
    // badges?: Array<{ id: number; name: string; icon: string; description: string }>;
    likes?: Array<{ id: number }>;
    received_gifts?: Array<{ id: number; gift: { name: string; points: number } }>;
    favorited_by?: Array<{ id: number; nickname?: string }>;
}

interface Props {
    cast: Cast;
}

export default function CastShow({ cast }: Props) {
    const getDisplayName = (cast: Cast) => {
        return cast.nickname || cast.phone || `キャスト${cast.id}`;
    };

    const getAge = (birthYear?: number) => {
        if (!birthYear) return null;
        return new Date().getFullYear() - birthYear;
    };

    const getStatusBadge = (cast: Cast) => {
        switch (cast.status) {
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

    // Get avatar URLs from the cast data
    const getAvatarUrls = (cast: Cast): string[] => {
        if (cast.avatar_urls && cast.avatar_urls.length > 0) {
            return cast.avatar_urls;
        }
        if (cast.avatar) {
            // Fallback: parse comma-separated avatar string
            return cast.avatar.split(',').map(path => `/storage/${path.trim()}`);
        }
        return [];
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'キャスト一覧', href: '/admin/casts' },
            { title: getDisplayName(cast), href: `/admin/casts/${cast.id}` }
        ]}>
            <Head title={`${getDisplayName(cast)} - キャスト詳細`} />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/casts">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                戻る
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">キャスト詳細</h1>
                    </div>
                    <Link href={`/admin/casts/${cast.id}/edit`}>
                        <Button>
                            <Edit className="w-4 h-4 mr-2" />
                            編集
                        </Button>
                    </Link>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* プロフィール情報 */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-4">
                                    <AvatarSlider
                                        avatars={getAvatarUrls(cast)}
                                        fallbackText={getDisplayName(cast)[0]}
                                        size="2xl"
                                        shape="rectangle"
                                        showNavigation={true}
                                        showDots={true}
                                        autoPlay={false}
                                    />
                                    <div>
                                        <div className="text-xl font-bold">{getDisplayName(cast)}</div>
                                        <div className="text-sm text-muted-foreground">ID: {cast.id}</div>
                                    </div>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-4">
                                    {getStatusBadge(cast)}
                                    <Badge variant="secondary">{cast.points.toLocaleString()} pt</Badge>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Phone className="w-4 h-4 text-muted-foreground" />
                                            <span className="font-medium">電話番号:</span>
                                            <span>{cast.phone || '未設定'}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <MessageCircle className="w-4 h-4 text-muted-foreground" />
                                            <span className="font-medium">LINE ID:</span>
                                            <span>{cast.line_id || '未設定'}</span>
                                        </div>
                                        {cast.birth_year && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <Calendar className="w-4 h-4 text-muted-foreground" />
                                                <span className="font-medium">年齢:</span>
                                                <span>{getAge(cast.birth_year)}歳 ({cast.birth_year}年生まれ)</span>
                                            </div>
                                        )}
                                        {cast.height && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">身長:</span>
                                                <span>{cast.height}cm</span>
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        {cast.residence && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <MapPin className="w-4 h-4 text-muted-foreground" />
                                                <span className="font-medium">居住地:</span>
                                                <span>{cast.residence}</span>
                                            </div>
                                        )}
                                        {cast.birthplace && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <MapPin className="w-4 h-4 text-muted-foreground" />
                                                <span className="font-medium">出身地:</span>
                                                <span>{cast.birthplace}</span>
                                            </div>
                                        )}
                                        {cast.grade && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">グレード:</span>
                                                <span>{cast.grade}</span>
                                            </div>
                                        )}
                                        {cast.grade_points && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="font-medium">30分あたりのポイント:</span>
                                                <span>{cast.grade_points}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {cast.profile_text && (
                                    <div className="pt-4 border-t">
                                        <h3 className="font-medium mb-2">プロフィール</h3>
                                        <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                            {cast.profile_text}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* 統計情報 */}
                        <Card>
                            <CardHeader>
                                <CardTitle>統計情報</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Heart className="w-8 h-8 mx-auto mb-2 text-red-500" />
                                        <div className="text-2xl font-bold">{cast.likes?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">いいね数</div>
                                    </div>
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Gift className="w-8 h-8 mx-auto mb-2 text-green-500" />
                                        <div className="text-2xl font-bold">{cast.received_gifts?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">受け取ったギフト</div>
                                    </div>
                                    <div className="text-center p-4 bg-muted rounded-lg">
                                        <Users className="w-8 h-8 mx-auto mb-2 text-blue-500" />
                                        <div className="text-2xl font-bold">{cast.favorited_by?.length || 0}</div>
                                        <div className="text-sm text-muted-foreground">お気に入り登録</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* サイドバー */}
                    <div className="space-y-6">
                        {/* 登録情報 */}
                        <Card>
                            <CardHeader>
                                <CardTitle>登録情報</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">登録日:</span>
                                    <span>{new Date(cast.created_at).toLocaleDateString('ja-JP')}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">最終更新:</span>
                                    <span>{new Date(cast.updated_at).toLocaleDateString('ja-JP')}</span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* 支払い情報 */}
                        {(cast.stripe_customer_id || cast.payjp_customer_id) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>支払い情報</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {cast.stripe_customer_id && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Stripe顧客ID:</span>
                                            <span className="font-mono text-xs">{cast.stripe_customer_id}</span>
                                        </div>
                                    )}
                                    {cast.payjp_customer_id && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">PayJP顧客ID:</span>
                                            <span className="font-mono text-xs">{cast.payjp_customer_id}</span>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
