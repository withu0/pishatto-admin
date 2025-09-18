import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Eye, Search, Calendar, User } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';

interface CastApplication {
    id: number;
    line_url: string;
    front_image: string;
    profile_image: string;
    full_body_image: string;
    front_image_url: string;
    profile_image_url: string;
    full_body_image_url: string;
    status: 'pending' | 'preliminary_passed' | 'preliminary_rejected' | 'final_passed' | 'final_rejected';
    admin_notes?: string;
    reviewed_at?: string;
    reviewed_by?: number;
    reviewer?: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

interface Props {
    applications: {
        data: CastApplication[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

export default function AdminCastApplications({ applications, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/cast-applications', { 
            search: debouncedSearch,
            status: statusFilter 
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch, statusFilter]);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return <Badge variant="default">審査待ち</Badge>;
            case 'preliminary_rejected':
                return <Badge variant="destructive">写真不合格</Badge>;
            case 'preliminary_passed':
                return <Badge variant="secondary">一次審査通過</Badge>;
            case 'final_rejected':
                return <Badge variant="destructive">面接不合格</Badge>;
            case 'final_passed':
                return <Badge variant="secondary">合格</Badge>;
            default:
                return <Badge variant="outline">不明</Badge>;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const getPendingCount = () => {
        return applications.data.filter(app => app.status === 'pending').length;
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'キャスト申請一覧', href: '/admin/cast-applications' }]}>
            <Head title="キャスト申請一覧" />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-bold">キャスト申請一覧</h1>
                        <p className="text-muted-foreground">
                            審査待ち: {getPendingCount()}件 / 総数: {applications.total}件
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>申請一覧</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-4">
                            <div className="relative flex-1 max-w-xs">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="LINE URLで検索"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-8"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="px-3 py-2 border border-input rounded-md"
                            >
                                <option value="">すべてのステータス</option>
                                <option value="pending">審査待ち</option>
                                <option value="approved">承認済み</option>
                                <option value="rejected">却下</option>
                            </select>
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">表示件数</span>
                                <select
                                    className="px-2 py-1 border rounded text-sm"
                                    value={String(applications.per_page || 10)}
                                    onChange={(e) => router.get('/admin/cast-applications', { search: debouncedSearch, status: statusFilter, page: 1, per_page: Number(e.target.value) }, { preserveState: true })}
                                >
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>

                        <div className="space-y-4">
                            {applications.data.map((application) => (
                                <div key={application.id} className="border rounded-lg p-4">
                                    <div className="flex items-center justify-between mb-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex gap-2">
                                                {application.front_image_url && (
                                                    <img 
                                                        src={application.front_image_url} 
                                                        alt="Front" 
                                                        className="w-12 h-12 object-cover rounded"
                                                    />
                                                )}
                                                {application.profile_image_url && (
                                                    <img 
                                                        src={application.profile_image_url} 
                                                        alt="Profile" 
                                                        className="w-12 h-12 object-cover rounded"
                                                    />
                                                )}
                                                {application.full_body_image_url && (
                                                    <img 
                                                        src={application.full_body_image_url} 
                                                        alt="Full Body" 
                                                        className="w-12 h-12 object-cover rounded"
                                                    />
                                                )}
                                            </div>
                                            <div>
                                                <p className="font-medium">申請 #{application.id}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    LINE: {application.line_url}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {getStatusBadge(application.status)}
                                            <Link href={`/admin/cast-applications/${application.id}`}>
                                                <Button size="sm" variant="outline">
                                                    <Eye className="w-4 h-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                    
                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                        <div className="flex items-center gap-1">
                                            <Calendar className="w-4 h-4" />
                                            申請日: {formatDate(application.created_at)}
                                        </div>
                                        {application.reviewed_at && (
                                            <div className="flex items-center gap-1">
                                                <User className="w-4 h-4" />
                                                審査日: {formatDate(application.reviewed_at)}
                                                {application.reviewer && (
                                                    <span>({application.reviewer.name})</span>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {application.admin_notes && (
                                        <div className="mt-2 p-2 bg-muted rounded text-sm">
                                            <strong>管理者メモ:</strong> {application.admin_notes}
                                        </div>
                                    )}
                                </div>
                            ))}

                            {applications.data.length === 0 && (
                                <div className="text-center py-8 text-muted-foreground">
                                    申請が見つかりませんでした。
                                </div>
                            )}
                        </div>

                        {/* Pagination (numbered) */}
                        {applications.last_page > 1 && (
                            <div className="flex items-center justify-between mt-6">
                                <div className="text-sm text-muted-foreground">
                                    {(applications.current_page - 1) * applications.per_page + 1} - {Math.min(applications.current_page * applications.per_page, applications.total)} / {applications.total} 件
                                </div>
                                <div className="flex gap-2 flex-wrap">
                                    {Array.from({ length: applications.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            variant={page === applications.current_page ? "default" : "outline"}
                                            size="sm"
                                            disabled={page === applications.current_page}
                                            onClick={() => router.get('/admin/cast-applications', { 
                                                page,
                                                search: debouncedSearch,
                                                status: statusFilter,
                                                per_page: applications.per_page || 10
                                            })}
                                        >
                                            {page}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
