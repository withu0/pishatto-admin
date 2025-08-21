import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Check, X, Search, Eye, Download } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useDebounce } from '@/hooks/use-debounce';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';


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
    identity_verification_url?: string;
    status?: 'active' | 'inactive' | 'suspended';
    created_at: string;
    updated_at: string;
}

interface Props {
    verifications: {
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

export default function AdminIdentityVerifications({ verifications, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isApproving, setIsApproving] = useState<number | null>(null);
    const [isRejecting, setIsRejecting] = useState<number | null>(null);
    const [selectedVerification, setSelectedVerification] = useState<Guest | null>(null);
    const [isImageModalOpen, setIsImageModalOpen] = useState(false);
    const debouncedSearch = useDebounce(search, 300);

    useEffect(() => {
        router.get('/admin/identity-verifications', { search: debouncedSearch }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [debouncedSearch]);

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
        } catch (error) {
            console.error('承認に失敗しました:', error);
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
        } catch (error) {
            console.error('却下に失敗しました:', error);
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

    const openImageModal = (verification: Guest) => {
        console.log('Opening modal for verification:', verification);
        console.log('Image URL:', verification.identity_verification_url);
        setSelectedVerification(verification);
        setIsImageModalOpen(true);
    };

    const downloadImage = (url: string, filename: string) => {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    return (
        <AppLayout breadcrumbs={[{ title: '身分証明書認証', href: '/admin/identity-verifications' }]}>
            <Head title="身分証明書認証" />
            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">身分証明書認証</h1>
                    <p className="text-gray-600">アップロードされた身分証明書の確認と承認を行います</p>
                </div>
                <Card className="shadow-sm border-0">
                    <CardHeader className="bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
                        <div className="flex flex-row items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <svg className="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <CardTitle className="text-xl text-gray-900">認証待ち一覧</CardTitle>
                                    <p className="text-sm text-gray-600 mt-1">身分証明書の確認と承認を行ってください</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="text-right">
                                    <div className="text-2xl font-bold text-blue-600">{verifications.total}</div>
                                    <div className="text-sm text-gray-600">件の認証待ち</div>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-6">
                        <div className="mb-6">
                            <div className="relative max-w-md flex items-center gap-4">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                <Input
                                    placeholder="名前・電話番号・LINE IDで検索..."
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    className="pl-10 pr-4 py-2 border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                                />
                                <div className="flex items-center gap-2 ml-4">
                                    <span className="text-sm text-muted-foreground">表示件数</span>
                                    <select
                                        className="px-2 py-1 border rounded text-sm"
                                        value={String(verifications.per_page || 10)}
                                        onChange={(e) => router.get('/admin/identity-verifications', { search, page: 1, per_page: Number(e.target.value) }, { preserveState: true })}
                                    >
                                        <option value="10">10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="px-4 py-3 text-left font-semibold text-gray-700">#</th>
                                        <th className="px-4 py-3 text-left font-semibold text-gray-700">ゲスト情報</th>
                                        <th className="px-4 py-3 text-left font-semibold text-gray-700">身分証明書</th>
                                        <th className="px-4 py-3 text-left font-semibold text-gray-700">登録日</th>
                                        <th className="px-4 py-3 text-left font-semibold text-gray-700">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {verifications.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="text-center py-12">
                                                <div className="flex flex-col items-center text-gray-500">
                                                    <svg className="w-12 h-12 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <p className="text-lg font-medium mb-2">認証待ちの身分証明書がありません</p>
                                                    <p className="text-sm">新しい身分証明書がアップロードされると、ここに表示されます。</p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        verifications.data.map((verification, idx) => (
                                            <tr key={verification.id} className="border-b hover:bg-gray-50 transition-colors">
                                                <td className="px-4 py-4">
                                                    <span className="inline-flex items-center justify-center w-6 h-6 bg-blue-100 text-blue-600 rounded-full text-xs font-medium">
                                                        {verifications.from + idx}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="w-10 h-10">
                                                            <AvatarImage src={verification.avatar_url || verification.avatar_urls?.[0]} />
                                                            <AvatarFallback className="bg-blue-100 text-blue-600">
                                                                {getDisplayName(verification)[0]}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="flex-1">
                                                            <div className="font-medium text-gray-900">{getDisplayName(verification)}</div>
                                                            <div className="text-xs text-gray-500 mt-1">
                                                                {verification.phone && `📞 ${verification.phone}`}
                                                                {verification.line_id && ` | LINE: ${verification.line_id}`}
                                                            </div>
                                                            <div className="text-xs text-gray-500">
                                                                {verification.birth_year && `${getAge(verification.birth_year)}歳`}
                                                                {verification.residence && ` | ${verification.residence}`}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => openImageModal(verification)}
                                                            disabled={!verification.identity_verification_url && !verification.identity_verification}
                                                            className="text-blue-600 border-blue-200 hover:bg-blue-50 disabled:opacity-50"
                                                        >
                                                            <Eye className="w-4 h-4 mr-1" />
                                                            確認
                                                        </Button>
                                                        {verification.identity_verification_url && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => downloadImage(
                                                                    verification.identity_verification_url!,
                                                                    `verification_${verification.id}.jpg`
                                                                )}
                                                                className="text-gray-600 border-gray-200 hover:bg-gray-50"
                                                            >
                                                                <Download className="w-4 h-4 mr-1" />
                                                                ダウンロード
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span className="text-sm text-gray-600">
                                                        {new Date(verification.created_at).toLocaleDateString('ja-JP')}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <div className="flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="default"
                                                            onClick={() => handleApproveVerification(verification.id)}
                                                            disabled={isApproving === verification.id}
                                                            className="bg-green-600 hover:bg-green-700 text-white"
                                                        >
                                                            {isApproving === verification.id ? (
                                                                <div className="flex items-center">
                                                                    <div className="animate-spin rounded-full h-3 w-3 border-b border-white mr-2"></div>
                                                                    承認中...
                                                                </div>
                                                            ) : (
                                                                <>
                                                                    <Check className="w-4 h-4 mr-1" />
                                                                    承認
                                                                </>
                                                            )}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() => handleRejectVerification(verification.id)}
                                                            disabled={isRejecting === verification.id}
                                                        >
                                                            {isRejecting === verification.id ? (
                                                                <div className="flex items-center">
                                                                    <div className="animate-spin rounded-full h-3 w-3 border-b border-white mr-2"></div>
                                                                    却下中...
                                                                </div>
                                                            ) : (
                                                                <>
                                                                    <X className="w-4 h-4 mr-1" />
                                                                    却下
                                                                </>
                                                            )}
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination (numbered) */}
                        {verifications.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    {verifications.from} - {verifications.to} / {verifications.total} 件
                                </div>
                                <div className="flex gap-2 flex-wrap">
                                    {Array.from({ length: verifications.last_page }, (_, i) => i + 1).map((page) => (
                                        <Button
                                            key={page}
                                            variant={page === verifications.current_page ? 'default' : 'outline'}
                                            size="sm"
                                            disabled={page === verifications.current_page}
                                            onClick={() => router.get('/admin/identity-verifications', { page, search: debouncedSearch, per_page: verifications.per_page || 10 }, { preserveState: true })}
                                        >
                                            {page}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Enhanced Image Modal */}
                <Dialog open={isImageModalOpen} onOpenChange={setIsImageModalOpen}>
                    <DialogContent className="max-w-5xl max-h-[90vh] overflow-hidden">
                        <DialogHeader className="pb-4 border-b">
                            <div className="flex items-center justify-between">
                                <div>
                                    <DialogTitle className="text-xl font-semibold">
                                        身分証明書確認
                                    </DialogTitle>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        {selectedVerification && getDisplayName(selectedVerification)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                        ゲストID: {selectedVerification?.id}
                                    </span>
                                    <span className="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">
                                        {selectedVerification && new Date(selectedVerification.created_at).toLocaleDateString('ja-JP')}
                                    </span>
                                </div>
                            </div>
                        </DialogHeader>

                        {selectedVerification && (selectedVerification.identity_verification_url || selectedVerification.identity_verification) && (
                            <div className="flex flex-col h-full">
                                {/* Image Display Section */}
                                <div className="flex-1 min-h-0 p-6">
                                    <div className="relative w-full max-w-4xl mx-auto bg-gray-50 rounded-lg border-2 border-dashed border-gray-200 overflow-hidden">
                                        <img
                                            src={selectedVerification.identity_verification_url || (selectedVerification.identity_verification ? `/storage/${selectedVerification.identity_verification}` : '')}
                                            alt="身分証明書"
                                            className="w-full h-auto max-h-[70vh] object-contain"
                                            onError={(e) => {
                                                console.error('Image failed to load:', selectedVerification.identity_verification_url || selectedVerification.identity_verification);
                                                e.currentTarget.style.display = 'none';
                                                e.currentTarget.nextElementSibling?.classList.remove('hidden');
                                            }}
                                        />
                                        <div className="hidden absolute inset-0 flex items-center justify-center bg-white">
                                            <div className="text-center p-8 max-w-md mx-auto">
                                                <div className="text-gray-400 mb-4">
                                                    <svg className="mx-auto h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                                <h3 className="text-lg font-medium text-gray-900 mb-2">画像を読み込めませんでした</h3>
                                                <p className="text-sm text-gray-500 mb-4">画像の読み込みに失敗しました。以下を確認してください。</p>
                                                <div className="text-xs text-gray-400 space-y-1 mb-4">
                                                    <p>URL: {selectedVerification.identity_verification_url || '(なし)'}</p>
                                                    <p>File Path: {selectedVerification.identity_verification || '(なし)'}</p>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        const directUrl = selectedVerification.identity_verification_url || (selectedVerification.identity_verification ? `/storage/${selectedVerification.identity_verification}` : '');
                                                        if (directUrl) {
                                                            window.open(directUrl, '_blank');
                                                        }
                                                    }}
                                                >
                                                    直接リンクを試す
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Action Bar */}
                                <div className="border-t bg-gray-50 p-4">
                                    <div className="flex items-center justify-between">
                                        {/* Guest Information */}
                                        <div className="flex items-center gap-4">
                                            <div className="flex items-center gap-2">
                                                <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                    <span className="text-blue-600 text-sm font-medium">
                                                        {selectedVerification.nickname?.[0] || 'G'}
                                                    </span>
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium">{selectedVerification.nickname || `ゲスト${selectedVerification.id}`}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {selectedVerification.phone && `📞 ${selectedVerification.phone}`}
                                                        {selectedVerification.line_id && ` | LINE: ${selectedVerification.line_id}`}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex items-center gap-3">
                                            {/* Utility Buttons */}
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => downloadImage(
                                                        selectedVerification.identity_verification_url || `/storage/${selectedVerification.identity_verification}`,
                                                        `verification_${selectedVerification.id}.jpg`
                                                    )}
                                                    disabled={!selectedVerification.identity_verification_url && !selectedVerification.identity_verification}
                                                    className="text-xs disabled:opacity-50"
                                                >
                                                    <Download className="w-3 h-3 mr-1" />
                                                    ダウンロード
                                                </Button>
                                            </div>

                                            {/* Decision Buttons */}
                                            <div className="flex items-center gap-2 ml-4 pl-4 border-l">
                                                <Button
                                                    size="sm"
                                                    variant="default"
                                                    onClick={() => {
                                                        handleApproveVerification(selectedVerification.id);
                                                        setIsImageModalOpen(false);
                                                    }}
                                                    disabled={isApproving === selectedVerification.id}
                                                    className="bg-green-600 hover:bg-green-700 text-white px-4"
                                                >
                                                    {isApproving === selectedVerification.id ? (
                                                        <div className="flex items-center">
                                                            <div className="animate-spin rounded-full h-3 w-3 border-b border-white mr-2"></div>
                                                            承認中...
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <Check className="w-4 h-4 mr-1" />
                                                            承認
                                                        </>
                                                    )}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => {
                                                        handleRejectVerification(selectedVerification.id);
                                                        setIsImageModalOpen(false);
                                                    }}
                                                    disabled={isRejecting === selectedVerification.id}
                                                    className="px-4"
                                                >
                                                    {isRejecting === selectedVerification.id ? (
                                                        <div className="flex items-center">
                                                            <div className="animate-spin rounded-full h-3 w-3 border-b border-white mr-2"></div>
                                                            却下中...
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <X className="w-4 h-4 mr-1" />
                                                            却下
                                                        </>
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
} 