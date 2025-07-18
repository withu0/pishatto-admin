import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Icon } from '@/components/ui/icon';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Users, UserCheck, DollarSign, MessageCircle, Gift, Trophy, ListChecks, Megaphone, Sparkles } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChartContainer } from '@/components/ui/chart';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'ダッシュボード',
        href: '/dashboard',
    },
];

// Mock data from each admin page
const mockGuests = [
    { id: 1, name: '山田 太郎' },
    { id: 2, name: '佐藤 花子' },
    { id: 3, name: '鈴木 一郎' },
];
const mockCasts = [
    { id: 1, name: '高橋 美咲' },
    { id: 2, name: '田中 直樹' },
    { id: 3, name: '小林 さくら' },
];
const mockSales = [
    { id: 1, guest: '山田 太郎', amount: 5000, date: '2024-07-01' },
    { id: 2, guest: '佐藤 花子', amount: 3000, date: '2024-06-15' },
    { id: 3, guest: '鈴木 一郎', amount: 2000, date: '2024-06-10' },
];
const mockMessages = [
    { id: 1 },
    { id: 2 },
];
const mockGifts = [
    { id: 1 },
    { id: 2 },
];
const mockRanking = [
    { id: 1 },
    { id: 2 },
];
const mockMatching = [
    { id: 1 },
    { id: 2 },
];
const mockNotifications = [
    { id: 1 },
    { id: 2 },
];
const mockGiftPie = [
    { name: '花束', value: 5, color: '#a78bfa' },
    { name: 'ぬいぐるみ', value: 3, color: '#f472b6' },
    { name: 'お菓子', value: 2, color: '#facc15' },
];

const summaryCards = [
    {
        title: 'ゲスト',
        value: mockGuests.length,
        icon: Users,
        link: '/admin/guests',
        badge: '一覧',
        color: 'bg-gradient-to-tr from-blue-100 to-blue-300 text-blue-900',
    },
    {
        title: 'キャスト',
        value: mockCasts.length,
        icon: UserCheck,
        link: '/admin/casts',
        badge: '一覧',
        color: 'bg-gradient-to-tr from-pink-100 to-pink-300 text-pink-900',
    },
    {
        title: '売上',
        value: mockSales.reduce((sum, s) => sum + s.amount, 0).toLocaleString() + '円',
        icon: DollarSign,
        link: '/admin/sales',
        badge: '合計',
        color: 'bg-gradient-to-tr from-yellow-100 to-yellow-300 text-yellow-900',
    },
    {
        title: 'メッセージ',
        value: mockMessages.length,
        icon: MessageCircle,
        link: '/admin/messages',
        badge: '件数',
        color: 'bg-gradient-to-tr from-green-100 to-green-300 text-green-900',
    },
    {
        title: 'ギフト',
        value: mockGifts.length,
        icon: Gift,
        link: '/admin/gifts',
        badge: '件数',
        color: 'bg-gradient-to-tr from-purple-100 to-purple-300 text-purple-900',
    },
    {
        title: 'ランキング',
        value: mockRanking.length,
        icon: Trophy,
        link: '/admin/ranking',
        badge: '件数',
        color: 'bg-gradient-to-tr from-orange-100 to-orange-300 text-orange-900',
    },
    {
        title: 'マッチング',
        value: mockMatching.length,
        icon: ListChecks,
        link: '/admin/matching-select',
        badge: '件数',
        color: 'bg-gradient-to-tr from-teal-100 to-teal-300 text-teal-900',
    },
    {
        title: 'お知らせ',
        value: mockNotifications.length,
        icon: Megaphone,
        link: '/admin/notifications',
        badge: '件数',
        color: 'bg-gradient-to-tr from-gray-100 to-gray-300 text-gray-900',
    },
];

const recentUpdates = [
    {
        user: '山田 太郎',
        type: 'ゲスト',
        action: '新規登録',
        time: '1時間前',
    },
    {
        user: '高橋 美咲',
        type: 'キャスト',
        action: 'プロフィール更新',
        time: '2時間前',
    },
    {
        user: '佐藤 花子',
        type: 'ゲスト',
        action: '売上追加',
        time: '3時間前',
    },
    {
        user: '田中 直樹',
        type: 'キャスト',
        action: 'ギフト受取',
        time: '4時間前',
    },
];

const salesChartData = [
    { name: '6/10', 売上: 2000 },
    { name: '6/15', 売上: 3000 },
    { name: '7/01', 売上: 5000 },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ダッシュボード" />
            <div className="flex flex-col gap-6 p-4">
                {/* Information Board */}
                <Alert className="mb-2 bg-gradient-to-r from-indigo-100 to-indigo-50 border-indigo-200">
                    <Sparkles className="text-indigo-400" />
                    <AlertTitle className="text-lg">管理者ダッシュボードへようこそ！</AlertTitle>
                    <AlertDescription>
                        主要な管理機能のサマリーと最新の更新情報を確認できます。
                    </AlertDescription>
                </Alert>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-4">
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
                    {/* Sales Chart */}
                    <Card className="shadow-sm border-2 border-indigo-100">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-indigo-700">
                                <DollarSign className="h-5 w-5 text-yellow-500" />売上グラフ
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-64 w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={salesChartData}>
                                        <XAxis dataKey="name" />
                                        <YAxis />
                                        <Tooltip />
                                        <Bar dataKey="売上" fill="#facc15" radius={[8, 8, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recent Updates Board */}
                    <Card className="shadow-sm border-2 border-indigo-100">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-indigo-700">
                                <Sparkles className="h-5 w-5 text-indigo-400" />最近の更新
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-3">
                                {recentUpdates.map((item, idx) => (
                                    <div key={idx} className="flex items-center gap-4">
                                        <Avatar>
                                            <AvatarFallback>{item.user[0]}</AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <span className="font-medium text-indigo-900">{item.user}</span>
                                            <span className="ml-2 text-xs text-indigo-500">[{item.type}]</span>
                                            <span className="ml-2">{item.action}</span>
                                            <span className="ml-4 text-xs text-muted-foreground">{item.time}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Gift Pie Chart Row */}
                <div className="grid md:grid-cols-3 gap-6 mt-2">
                    <div className="md:col-start-2">
                        <Card className="shadow-sm border-2 border-purple-100">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-purple-700">
                                    <Gift className="h-5 w-5 text-purple-400" />ギフト分布（円グラフ）
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="h-64 w-full flex items-center justify-center">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={mockGiftPie}
                                                dataKey="value"
                                                nameKey="name"
                                                cx="50%"
                                                cy="50%"
                                                outerRadius={80}
                                                label
                                            >
                                                {mockGiftPie.map((entry, index) => (
                                                    <Cell key={`cell-${index}`} fill={entry.color} />
                                                ))}
                                            </Pie>
                                            <Tooltip />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
