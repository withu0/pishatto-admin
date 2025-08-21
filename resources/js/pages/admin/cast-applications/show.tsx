import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Check, X, Calendar, User } from 'lucide-react';
import { useState } from 'react';

interface CastApplication {
    id: number;
    line_url: string;
    front_image: string;
    profile_image: string;
    full_body_image: string;
    front_image_url: string;
    profile_image_url: string;
    full_body_image_url: string;
    status: 'pending' | 'approved' | 'rejected';
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
    application: CastApplication;
}

export default function AdminCastApplicationShow({ application }: Props) {
    const [adminNotes, setAdminNotes] = useState(application.admin_notes || '');
    const [isProcessing, setIsProcessing] = useState(false);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return <Badge variant="default">審査待ち</Badge>;
            case 'approved':
                return <Badge variant="secondary">承認済み</Badge>;
            case 'rejected':
                return <Badge variant="destructive">却下</Badge>;
            default:
                return <Badge variant="outline">不明</Badge>;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const handleApprove = async () => {
        if (!confirm('この申請を承認しますか？')) return;
        
        setIsProcessing(true);
        try {
            await router.post(`/admin/cast-applications/${application.id}/approve`, {
                admin_notes: adminNotes
            });
        } catch (error) {
            console.error('Error approving application:', error);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleReject = async () => {
        if (!adminNotes.trim()) {
            alert('却下理由を入力してください。');
            return;
        }
        
        if (!confirm('この申請を却下しますか？')) return;
        
        setIsProcessing(true);
        try {
            await router.post(`/admin/cast-applications/${application.id}/reject`, {
                admin_notes: adminNotes
            });
        } catch (error) {
            console.error('Error rejecting application:', error);
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'キャスト申請一覧', href: '/admin/cast-applications' },
            { title: `申請 #${application.id}`, href: `/admin/cast-applications/${application.id}` }
        ]}>
            <Head title={`キャスト申請 #${application.id}`} />
            <div className="p-6">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/admin/cast-applications">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold">キャスト申請 #{application.id}</h1>
                        <div className="flex items-center gap-2 mt-1">
                            {getStatusBadge(application.status)}
                            <span className="text-sm text-muted-foreground">
                                申請日: {formatDate(application.created_at)}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Application Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>申請詳細</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium">LINE URL</label>
                                <p className="text-sm text-muted-foreground break-all">
                                    {application.line_url}
                                </p>
                            </div>

                            <div>
                                <label className="text-sm font-medium">申請日時</label>
                                <p className="text-sm text-muted-foreground">
                                    {formatDate(application.created_at)}
                                </p>
                            </div>

                            {application.reviewed_at && (
                                <div>
                                    <label className="text-sm font-medium">審査日時</label>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(application.reviewed_at)}
                                        {application.reviewer && (
                                            <span> (審査者: {application.reviewer.name})</span>
                                        )}
                                    </p>
                                </div>
                            )}

                            {application.admin_notes && (
                                <div>
                                    <label className="text-sm font-medium">管理者メモ</label>
                                    <p className="text-sm text-muted-foreground">
                                        {application.admin_notes}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Images */}
                    <Card>
                        <CardHeader>
                            <CardTitle>提出写真</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">正面写真</label>
                                {application.front_image_url && (
                                    <img 
                                        src={application.front_image_url} 
                                        alt="Front" 
                                        className="w-full max-w-md h-auto rounded-lg border"
                                    />
                                )}
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-2 block">横顔写真</label>
                                {application.profile_image_url && (
                                    <img 
                                        src={application.profile_image_url} 
                                        alt="Profile" 
                                        className="w-full max-w-md h-auto rounded-lg border"
                                    />
                                )}
                            </div>

                            <div>
                                <label className="text-sm font-medium mb-2 block">全身写真</label>
                                {application.full_body_image_url && (
                                    <img 
                                        src={application.full_body_image_url} 
                                        alt="Full Body" 
                                        className="w-full max-w-md h-auto rounded-lg border"
                                    />
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Approval Actions */}
                {application.status === 'pending' && (
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>審査</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">
                                    管理者メモ {application.status === 'pending' && <span className="text-red-500">*</span>}
                                </label>
                                <Textarea
                                    value={adminNotes}
                                    onChange={(e) => setAdminNotes(e.target.value)}
                                    placeholder="審査に関するメモを入力してください..."
                                    rows={4}
                                />
                                {application.status === 'pending' && (
                                    <p className="text-xs text-muted-foreground mt-1">
                                        * 却下する場合は理由を必ず入力してください
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-4">
                                <Button
                                    onClick={handleApprove}
                                    disabled={isProcessing}
                                    className="flex items-center gap-2"
                                >
                                    <Check className="w-4 h-4" />
                                    承認
                                </Button>
                                <Button
                                    onClick={handleReject}
                                    disabled={isProcessing}
                                    variant="destructive"
                                    className="flex items-center gap-2"
                                >
                                    <X className="w-4 h-4" />
                                    却下
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Review History */}
                {application.status !== 'pending' && (
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>審査履歴</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">ステータス:</span>
                                    {getStatusBadge(application.status)}
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">審査日時:</span>
                                    <span className="text-sm text-muted-foreground">
                                        {application.reviewed_at ? formatDate(application.reviewed_at) : '未審査'}
                                    </span>
                                </div>
                                {application.reviewer && (
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">審査者:</span>
                                        <span className="text-sm text-muted-foreground">
                                            {application.reviewer.name}
                                        </span>
                                    </div>
                                )}
                                {application.admin_notes && (
                                    <div>
                                        <span className="text-sm font-medium">メモ:</span>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            {application.admin_notes}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
