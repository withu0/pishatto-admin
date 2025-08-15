import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Edit, Trash2, Plus, Search, RefreshCw } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import ErrorBoundary from '@/components/error-boundary';

interface RankingData {
    id: number;
    name: string;
    type: string;
    rank: number;
    points: number;
    residence?: string;
    reservation_count?: number;
    gift_count?: number;
}

interface Filters {
    userType: string;
    period: string;
    category: string;
    region: string;
    search: string;
}

const periods = [
    { value: 'allTime', label: '全期間' },
    { value: 'current', label: '今月' },
    { value: 'lastMonth', label: '先月' },
    { value: 'lastWeek', label: '先週' },
    { value: 'yesterday', label: '昨日' },
];

const categories = [
    { value: 'reservation', label: '予約' },
    { value: 'gift', label: 'ギフト' },
];

const regions = [
    { value: '全国', label: '全国' },
    { value: '東京都', label: '東京都' },
    { value: '大阪府', label: '大阪府' },
    { value: '愛知県', label: '愛知県' },
    { value: '福岡県', label: '福岡県' },
    { value: '北海道', label: '北海道' },
];

function AdminRankingContent() {
    const [rankings, setRankings] = useState<RankingData[]>([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState<'ゲスト' | 'キャスト'>('ゲスト');
    const [filters, setFilters] = useState<Filters>({
        userType: 'guest',
        period: 'allTime',
        category: 'reservation',
        region: '全国',
        search: '',
    });

    const fetchRankings = useCallback(async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                ...filters,
                userType: activeTab === 'ゲスト' ? 'guest' : 'cast'
            } as any);
            const response = await fetch(`/admin/ranking/data?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            
            if (response.ok) {
                const data = await response.json();
                setRankings(data.data || []);
            } else {
                console.error('Failed to fetch rankings:', response.statusText);
                setRankings([]);
            }
        } catch (error) {
            console.error('Failed to fetch rankings:', error);
            setRankings([]);
        } finally {
            setLoading(false);
        }
    }, [filters, activeTab]);

    useEffect(() => {
        fetchRankings();
    }, [fetchRankings]);

    const handleTabChange = useCallback((tab: 'ゲスト' | 'キャスト') => {
        setActiveTab(tab);
        setFilters(prev => ({
            ...prev,
            userType: tab === 'ゲスト' ? 'guest' : 'cast'
        }));
    }, []);

    const handleFilterChange = useCallback((key: keyof Filters, value: string) => {
        setFilters(prev => ({
            ...prev,
            [key]: value
        }));
    }, []);

    const handleSearchChange = useCallback((value: string) => {
        setFilters(prev => ({
            ...prev,
            search: value
        }));
    }, []);

    const filteredRankings = rankings.filter(
        (r) => !filters.search || r.name.includes(filters.search) || r.type.includes(filters.search)
    );

    return (
        <div className="p-6">
            <h1 className="text-2xl font-bold mb-4">ランキング管理</h1>
            
            {/* Tabs: Guest / Cast */}
            <div className="mb-6">
                <div className="inline-flex rounded-md border p-1 bg-white">
                    <button
                        type="button"
                        className={`relative px-4 py-2 rounded-sm text-sm font-medium ${activeTab === 'ゲスト' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                        onClick={() => handleTabChange('ゲスト')}
                    >
                        ゲスト
                    </button>
                    <button
                        type="button"
                        className={`relative px-4 py-2 rounded-sm text-sm font-medium ${activeTab === 'キャスト' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-100'}`}
                        onClick={() => handleTabChange('キャスト')}
                    >
                        キャスト
                    </button>
                </div>
            </div>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                    <CardTitle>{activeTab === 'ゲスト' ? 'ゲスト' : 'キャスト'}ランキング一覧</CardTitle>
                    <Button size="sm" className="gap-1" onClick={fetchRankings} disabled={loading}>
                        <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
                        更新
                    </Button>
                </CardHeader>
                <CardContent>
                    {/* Filters */}
                    <div className="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">期間</label>
                            <Select value={filters.period} onValueChange={(value) => handleFilterChange('period', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {periods.map((period) => (
                                        <SelectItem key={period.value} value={period.value}>
                                            {period.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">カテゴリ</label>
                            <Select value={filters.category} onValueChange={(value) => handleFilterChange('category', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category.value} value={category.value}>
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">地域</label>
                            <Select value={filters.region} onValueChange={(value) => handleFilterChange('region', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {regions.map((region) => (
                                        <SelectItem key={region.value} value={region.value}>
                                            {region.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">検索</label>
                            <div className="relative">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="名前で検索"
                                    value={filters.search}
                                    onChange={(e) => handleSearchChange(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm border">
                            <thead>
                                <tr className="bg-muted">
                                    <th className="px-3 py-2 text-left font-semibold">#</th>
                                    <th className="px-3 py-2 text-left font-semibold">名前</th>
                                    <th className="px-3 py-2 text-left font-semibold">順位</th>
                                    <th className="px-3 py-2 text-left font-semibold">ポイント</th>
                                    <th className="px-3 py-2 text-left font-semibold">地域</th>
                                    <th className="px-3 py-2 text-left font-semibold">
                                        {filters.category === 'reservation' ? '予約数' : 'ギフト数'}
                                    </th>
                                    <th className="px-3 py-2 text-left font-semibold">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr key="loading">
                                        <td colSpan={7} className="text-center py-6 text-muted-foreground">
                                            データを読み込み中...
                                        </td>
                                    </tr>
                                ) : filteredRankings.length === 0 ? (
                                    <tr key="no-data">
                                        <td colSpan={7} className="text-center py-6 text-muted-foreground">
                                            該当するデータがありません
                                        </td>
                                    </tr>
                                ) : (
                                    filteredRankings.map((item, idx) => (
                                        <tr key={`${item.id}-${item.type}-${idx}`} className="border-t">
                                            <td className="px-3 py-2">{idx + 1}</td>
                                            <td className="px-3 py-2">{item.name}</td>
                                            <td className="px-3 py-2">{item.rank}</td>
                                            <td className="px-3 py-2">{item.points.toLocaleString()}</td>
                                            <td className="px-3 py-2">{item.residence || '-'}</td>
                                            <td className="px-3 py-2">
                                                {filters.category === 'reservation' 
                                                    ? (item.reservation_count || 0)
                                                    : (item.gift_count || 0)
                                                }
                                            </td>
                                            <td className="px-3 py-2 flex gap-2">
                                                <Button size="sm" variant="outline">
                                                    <Edit className="w-4 h-4" />
                                                    編集
                                                </Button>
                                                <Button size="sm" variant="destructive">
                                                    <Trash2 className="w-4 h-4" />
                                                    削除
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

export default function AdminRanking() {
    return (
        <AppLayout breadcrumbs={[{ title: 'ランキング管理', href: '/admin/ranking' }]}>
            <Head title="ランキング管理" />
            <ErrorBoundary>
                <AdminRankingContent />
            </ErrorBoundary>
        </AppLayout>
    );
}
