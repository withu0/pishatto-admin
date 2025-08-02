import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Icon } from '@/components/ui/icon';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Users, UserCheck, DollarSign, MessageCircle, Gift, Trophy, ListChecks, Megaphone, Sparkles, Clock, Shield } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChartContainer } from '@/components/ui/chart';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'ダッシュボード',
        href: '/dashboard',
    },
];



export default function Dashboard() {
    // Get real data from props
    const { pendingApplications, totalReservations, activeReservations, totalCasts, totalGuests } = usePage().props as any;

    const summaryCards = [
        {
            title: '保留中の応募',
            value: pendingApplications,
            icon: Clock,
            link: '/admin/reservation-applications',
            badge: '件数',
            color: 'bg-gradient-to-tr from-orange-100 to-orange-300 text-orange-900',
        },
        {
            title: '身分証明書認証待ち',
            value: pendingApplications, // This will be updated with actual verification data
            icon: Shield,
            link: '/admin/identity-verifications',
            badge: '件数',
            color: 'bg-gradient-to-tr from-red-100 to-red-300 text-red-900',
        },
        {
            title: '総予約数',
            value: totalReservations,
            icon: ListChecks,
            link: '/admin/guests',
            badge: '件数',
            color: 'bg-gradient-to-tr from-blue-100 to-blue-300 text-blue-900',
        },
        {
            title: 'アクティブ予約',
            value: activeReservations,
            icon: UserCheck,
            link: '/admin/casts',
            badge: '件数',
            color: 'bg-gradient-to-tr from-green-100 to-green-300 text-green-900',
        },
        {
            title: '総キャスト数',
            value: totalCasts,
            icon: Users,
            link: '/admin/casts',
            badge: '件数',
            color: 'bg-gradient-to-tr from-purple-100 to-purple-300 text-purple-900',
        },
        {
            title: '総ゲスト数',
            value: totalGuests,
            icon: Users,
            link: '/admin/guests',
            badge: '件数',
            color: 'bg-gradient-to-tr from-pink-100 to-pink-300 text-pink-900',
        },
    ];

    const recentUpdates = [
        {
            user: '予約応募管理',
            type: '管理',
            action: '保留中の応募を確認',
            time: '今すぐ',
        },
        {
            user: '統計情報',
            type: 'データ',
            action: '予約・キャスト・ゲスト数',
            time: 'リアルタイム',
        },
    ];

    const reservationChartData = [
        { name: '総予約', 数: totalReservations },
        { name: 'アクティブ', 数: activeReservations },
        { name: '保留中応募', 数: pendingApplications },
    ];
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ダッシュボード" />
            <div className="flex flex-col gap-6 p-4">
                {/* Information Board */}
                <Alert className="mb-2 bg-gradient-to-r from-indigo-100 to-indigo-50 border-indigo-200">
                    <Sparkles className="text-indigo-400" />
                    <AlertTitle className="text-lg">予約応募管理ダッシュボードへようこそ！</AlertTitle>
                    <AlertDescription>
                        保留中の予約応募を管理し、承認・却下の操作を行えます。主要な統計情報も確認できます。
                    </AlertDescription>
                </Alert>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-6">
                    {summaryCards.map((card) => (
                        <Link href={card.link} key={card.title} className="block">
                            <Card className={`hover:scale-[1.03] transition-transform shadow-md ${card.color}`} style={{ minHeight: 120 }}>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Icon iconNode={card.icon} className="h-5 w-5" />
                                        {card.title}
                                    </CardTitle>
                                    <Badge className="bg-white/80 text-xs text-black shadow-sm">{card.badge}</Badge>
                                </CardHeader>
                                <CardContent>
                                    <span className="text-3xl font-bold drop-shadow-sm">{card.value}</span>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>

                {/* Chart + Recent Updates Row */}
                <div className="grid md:grid-cols-2 gap-6 mt-2">
                    {/* Reservation Chart */}
                    <Card className="shadow-sm border-2 border-indigo-100">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-indigo-700">
                                <ListChecks className="h-5 w-5 text-blue-500" />予約統計グラフ
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-64 w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={reservationChartData}>
                                        <XAxis dataKey="name" />
                                        <YAxis />
                                        <Tooltip />
                                        <Bar dataKey="数" fill="#3b82f6" radius={[8, 8, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Quick Actions Board */}
                    <Card className="shadow-sm border-2 border-indigo-100">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-indigo-700">
                                <Sparkles className="h-5 w-5 text-indigo-400" />クイックアクション
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3">
                                <Link href="/admin/reservation-applications" className="flex items-center gap-4 p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <Clock className="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <span className="font-medium text-blue-900">予約応募管理</span>
                                        <span className="ml-2 text-xs text-blue-500">[管理]</span>
                                        <span className="ml-2">保留中の応募を承認・却下</span>
                                    </div>
                                </Link>
                                <Link href="/admin/identity-verifications" className="flex items-center gap-4 p-3 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                    <div className="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                                        <Shield className="h-5 w-5 text-red-600" />
                                    </div>
                                    <div>
                                        <span className="font-medium text-red-900">身分証明書認証</span>
                                        <span className="ml-2 text-xs text-red-500">[認証]</span>
                                        <span className="ml-2">身分証明書の承認・却下</span>
                                    </div>
                                </Link>
                                <div className="flex items-center gap-4 p-3 bg-gray-50 rounded-lg">
                                    <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <ListChecks className="h-5 w-5 text-gray-600" />
                                    </div>
                                    <div>
                                        <span className="font-medium text-gray-900">統計情報</span>
                                        <span className="ml-2 text-xs text-gray-500">[データ]</span>
                                        <span className="ml-2">リアルタイム統計</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>


            </div>
        </AppLayout>
    );
}
