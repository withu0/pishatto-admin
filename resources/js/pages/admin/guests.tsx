import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Edit, Trash2, Plus, Eye, Check, X } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';
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
    avatar_url?: string;
    avatar_urls?: string[];
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
    guests: {
        data: Guest[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
    };
}

export default function AdminGuests({ guests, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isDeleting, setIsDeleting] = useState<number | null>(null);
    const [isApproving, setIsApproving] = useState<number | null>(null);
    const [isRejecting, setIsRejecting] = useState<number | null>(null);
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/guests', { search: debouncedSearch }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch]);

    const handleDelete = async (guestId: number) => {
        if (!confirm('このゲストを削除してもよろしいですか？この操作は取り消せません。')) {
            return;
        }

        setIsDeleting(guestId);
        try {
            await router.delete(`/admin/guests/${guestId}`, {
                onSuccess: () => {
                    toast.success('ゲストが正常に削除されました');
                },
                onError: (errors) => {
                    toast.error('ゲストの削除に失敗しました');
                    console.error('Delete error:', errors);
                },
                onFinish: () => {
                    setIsDeleting(null);
                }
            });
        } catch {
            toast.error('ゲストの削除に失敗しました');
            setIsDeleting(null);
        }
    };

    const handleApproveVerification = async (guestId: number) => {
        if (!confirm('このゲストの身分証明書を承認しますか？')) {
            return;
        }

        setIsApproving(guestId);
        try {
            await router.post(`/admin/identity-verifications/${guestId}/approve`, {}, {
                onSuccess: () => {
                    toast.success('身分証明書が承認されました');
                },
                onError: (errors) => {
                    toast.error('承認に失敗しました');
                    console.error('Approve error:', errors);
                },
                onFinish: () => {
                    setIsApproving(null);
                }
            });
        } catch {
            toast.error('承認に失敗しました');
            setIsApproving(null);
        }
    };

    const handleRejectVerification = async (guestId: number) => {
        if (!confirm('このゲストの身分証明書を却下しますか？')) {
            return;
        }

        setIsRejecting(guestId);
        try {
            await router.post(`/admin/identity-verifications/${guestId}/reject`, {}, {
                onSuccess: () => {
                    toast.success('身分証明書が却下されました');
                },
                onError: (errors) => {
                    toast.error('却下に失敗しました');
                    console.error('Reject error:', errors);
                },
                onFinish: () => {
                    setIsRejecting(null);
                }
            });
        } catch {
            toast.error('却下に失敗しました');
            setIsRejecting(null);
        }
    };

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

    return (
        <AppLayout breadcrumbs={[{ title: 'ゲスト一覧', href: '/admin/guests' }]}>
            <Head title="ゲスト一覧" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">ゲスト一覧</h1>
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>ゲスト一覧</CardTitle>
                        <div className="flex gap-2">
                            <Link href="/admin/grades">
                                <Button size="sm" variant="outline">グレード管理</Button>
                            </Link>
                            <Link href="/admin/guests/create">
                            <Button size="sm" className="gap-1">
                                <Plus className="w-4 h-4" />
                                新規登録
                            </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4 flex items-center gap-4">
                            <Input
                                placeholder="名前・電話番号・LINE IDで検索"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="max-w-xs"
                            />
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">表示件数</span>
                                <select
                                    className="px-2 py-1 border rounded text-sm"
                                    value={String(guests.per_page || 10)}
                                    onChange={(e) => router.get('/admin/guests', { search: debouncedSearch, page: 1, per_page: Number(e.target.value) }, { preserveState: true })}
                                >
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">#</th>
                                        <th className="px-3 py-2 text-left font-semibold">名前</th>
                                        <th className="px-3 py-2 text-left font-semibold">電話番号</th>
                                        <th className="px-3 py-2 text-left font-semibold">LINE ID</th>
                                        <th className="px-3 py-2 text-left font-semibold">年齢</th>
                                        <th className="px-3 py-2 text-left font-semibold">居住地</th>
                                        <th className="px-3 py-2 text-left font-semibold">ポイント</th>
                                        <th className="px-3 py-2 text-left font-semibold">認証状態</th>
                                        <th className="px-3 py-2 text-left font-semibold">ステータス</th>
                                        <th className="px-3 py-2 text-left font-semibold">登録日</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {guests.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={11} className="text-center py-6 text-muted-foreground">
                                                該当するゲストがいません
                                            </td>
                                        </tr>
                                    ) : (
                                        guests.data.map((guest, idx) => (
                                            <tr key={guest.id} className="border-t">
                                                <td className="px-3 py-2">{guests.from + idx}</td>
                                                <td className="px-3 py-2 flex items-center gap-2">
                                                    <Avatar>
                                                        <AvatarImage src={guest.avatar_url || guest.avatar_urls?.[0]} />
                                                        <AvatarFallback>{getDisplayName(guest)[0]}</AvatarFallback>
                                                    </Avatar>
                                                    {getDisplayName(guest)}
                                                </td>
                                                <td className="px-3 py-2">{guest.phone || '未設定'}</td>
                                                <td className="px-3 py-2">{guest.line_id || '未設定'}</td>
                                                <td className="px-3 py-2">
                                                    {guest.birth_year ? `${getAge(guest.birth_year)}歳` : '未設定'}
                                                </td>
                                                <td className="px-3 py-2">{guest.residence || '未設定'}</td>
                                                <td className="px-3 py-2">
                                                    <Badge variant="secondary">{guest.points.toLocaleString()} pt</Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-2">
                                                        {getVerificationBadge(guest.identity_verification_completed)}
                                                        {guest.identity_verification_completed === 'pending' && guest.identity_verification && (
                                                            <div className="flex gap-1">
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="h-6 px-2"
                                                                    onClick={() => handleApproveVerification(guest.id)}
                                                                    disabled={isApproving === guest.id}
                                                                >
                                                                    <Check className="w-3 h-3" />
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="h-6 px-2"
                                                                    onClick={() => handleRejectVerification(guest.id)}
                                                                    disabled={isRejecting === guest.id}
                                                                >
                                                                    <X className="w-3 h-3" />
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {getStatusBadge(guest.status)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {new Date(guest.created_at).toLocaleDateString('ja-JP')}
                                                </td>
                                                <td className="px-3 py-2 flex gap-2">
                                                    <Link href={`/admin/guests/${guest.id}`}>
                                                        <Button size="sm" variant="outline">
                                                            <Eye className="w-4 h-4" />
                                                            詳細
                                                        </Button>
                                                    </Link>
                                                    <Link href={`/admin/guests/${guest.id}/edit`}>
                                                        <Button size="sm" variant="outline">
                                                            <Edit className="w-4 h-4" />
                                                            編集
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => handleDelete(guest.id)}
                                                        disabled={isDeleting === guest.id}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                        {isDeleting === guest.id ? '削除中...' : '削除'}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination (numbered) */}
                        {guests.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {guests.from} - {guests.to} / {guests.total} 件
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {Array.from({ length: guests.last_page }, (_, i) => i + 1).map((pageNum) => (
                                        <Button
                                            key={pageNum}
                                            size="sm"
                                            variant={pageNum === guests.current_page ? 'default' : 'outline'}
                                            disabled={pageNum === guests.current_page}
                                            onClick={() => router.get('/admin/guests', {
                                                page: pageNum,
                                                search: debouncedSearch,
                                                per_page: guests.per_page || 10
                                            }, { preserveState: true })}
                                        >
                                            {pageNum}
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
