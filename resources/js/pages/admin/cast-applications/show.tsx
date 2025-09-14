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
    phone_number: string;
    line_id: string;
    front_image: string;
    profile_image: string;
    full_body_image: string;
    front_image_url: string;
    profile_image_url: string;
    full_body_image_url: string;
    status: 'pending' | 'preliminary_passed' | 'preliminary_rejected' | 'final_passed' | 'final_rejected';
    preliminary_notes?: string;
    preliminary_reviewed_at?: string;
    preliminary_reviewed_by?: number;
    preliminary_reviewer?: {
        id: number;
        name: string;
    };
    final_notes?: string;
    final_reviewed_at?: string;
    final_reviewed_by?: number;
    final_reviewer?: {
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
    const [preliminaryNotes, setPreliminaryNotes] = useState(application.preliminary_notes || '');
    const [finalNotes, setFinalNotes] = useState(application.final_notes || '');
    const [isProcessing, setIsProcessing] = useState(false);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return <Badge variant="default">審査待ち</Badge>;
            case 'preliminary_passed':
                return <Badge variant="secondary">一次審査通過</Badge>;
            case 'preliminary_rejected':
                return <Badge variant="destructive">一次審査却下</Badge>;
            case 'final_passed':
                return <Badge variant="secondary">最終審査通過</Badge>;
            case 'final_rejected':
                return <Badge variant="destructive">最終審査却下</Badge>;
            default:
                return <Badge variant="outline">不明</Badge>;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const handleApprovePreliminary = async () => {
        if (!confirm('一次審査を通過させますか？')) return;
        
        setIsProcessing(true);
        try {
            await router.post(`/admin/cast-applications/${application.id}/approve-preliminary`, {
                preliminary_notes: preliminaryNotes
            });
        } catch (error) {
            console.error('Error approving preliminary screening:', error);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleRejectPreliminary = async () => {
        if (!preliminaryNotes.trim()) {
            alert('却下理由を入力してください。');
            return;
        }
        
        if (!confirm('一次審査を却下しますか？')) return;
        
        setIsProcessing(true);
        try {
            await router.post(`/admin/cast-applications/${application.id}/reject-preliminary`, {
                preliminary_notes: preliminaryNotes
            });
        } catch (error) {
            console.error('Error rejecting preliminary screening:', error);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleApproveFinal = async () => {
        if (!confirm('最終審査を通過させますか？')) return;
        
        setIsProcessing(true);
        try {
            await router.post(`/admin/cast-applications/${application.id}/approve-final`, {
                final_notes: finalNotes
            });
        } catch (error) {
            console.error('Error approving final screening:', error);
        } finally {
            setIsProcessing(false);
        }
    };

    const handleRejectFinal = async () => {
        if (!finalNotes.trim()) {
            alert('却下理由を入力してください。');
            return;
        }
        
        if (!confirm('最終審査を却下しますか？')) return;
        
        setIsProcessing(true);
        try {
            await router.post(`/admin/cast-applications/${application.id}/reject-final`, {
                final_notes: finalNotes
            });
        } catch (error) {
            console.error('Error rejecting final screening:', error);
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
                                <label className="text-sm font-medium">電話番号</label>
                                <p className="text-sm text-muted-foreground">
                                    {application.phone_number}
                                </p>
                            </div>

                            <div>
                                <label className="text-sm font-medium">LINE ID</label>
                                <p className="text-sm text-muted-foreground break-all">
                                    {application.line_id}
                                </p>
                            </div>

                            <div>
                                <label className="text-sm font-medium">申請日時</label>
                                <p className="text-sm text-muted-foreground">
                                    {formatDate(application.created_at)}
                                </p>
                            </div>

                            {application.preliminary_reviewed_at && (
                                <div>
                                    <label className="text-sm font-medium">一次審査日時</label>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(application.preliminary_reviewed_at)}
                                        {application.preliminary_reviewer && (
                                            <span> (審査者: {application.preliminary_reviewer.name})</span>
                                        )}
                                    </p>
                                </div>
                            )}

                            {application.final_reviewed_at && (
                                <div>
                                    <label className="text-sm font-medium">最終審査日時</label>
                                    <p className="text-sm text-muted-foreground">
                                        {formatDate(application.final_reviewed_at)}
                                        {application.final_reviewer && (
                                            <span> (審査者: {application.final_reviewer.name})</span>
                                        )}
                                    </p>
                                </div>
                            )}

                            {application.preliminary_notes && (
                                <div>
                                    <label className="text-sm font-medium">一次審査メモ</label>
                                    <p className="text-sm text-muted-foreground">
                                        {application.preliminary_notes}
                                    </p>
                                </div>
                            )}

                            {application.final_notes && (
                                <div>
                                    <label className="text-sm font-medium">最終審査メモ</label>
                                    <p className="text-sm text-muted-foreground">
                                        {application.final_notes}
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

                {/* Preliminary Screening Actions */}
                {application.status === 'pending' && (
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>一次審査</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">
                                    一次審査メモ <span className="text-red-500">*</span>
                                </label>
                                <Textarea
                                    value={preliminaryNotes}
                                    onChange={(e) => setPreliminaryNotes(e.target.value)}
                                    placeholder="一次審査に関するメモを入力してください..."
                                    rows={4}
                                />
                                <p className="text-xs text-muted-foreground mt-1">
                                    * 却下する場合は理由を必ず入力してください
                                </p>
                            </div>

                            <div className="flex gap-4">
                                <Button
                                    onClick={handleApprovePreliminary}
                                    disabled={isProcessing}
                                    className="flex items-center gap-2"
                                >
                                    <Check className="w-4 h-4" />
                                    一次審査通過
                                </Button>
                                <Button
                                    onClick={handleRejectPreliminary}
                                    disabled={isProcessing}
                                    variant="destructive"
                                    className="flex items-center gap-2"
                                >
                                    <X className="w-4 h-4" />
                                    一次審査却下
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Final Screening Actions */}
                {application.status === 'preliminary_passed' && (
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>最終審査</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium mb-2 block">
                                    最終審査メモ <span className="text-red-500">*</span>
                                </label>
                                <Textarea
                                    value={finalNotes}
                                    onChange={(e) => setFinalNotes(e.target.value)}
                                    placeholder="最終審査に関するメモを入力してください..."
                                    rows={4}
                                />
                                <p className="text-xs text-muted-foreground mt-1">
                                    * 却下する場合は理由を必ず入力してください
                                </p>
                            </div>

                            <div className="flex gap-4">
                                <Button
                                    onClick={handleApproveFinal}
                                    disabled={isProcessing}
                                    className="flex items-center gap-2"
                                >
                                    <Check className="w-4 h-4" />
                                    最終審査通過
                                </Button>
                                <Button
                                    onClick={handleRejectFinal}
                                    disabled={isProcessing}
                                    variant="destructive"
                                    className="flex items-center gap-2"
                                >
                                    <X className="w-4 h-4" />
                                    最終審査却下
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
                            <div className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium">現在のステータス:</span>
                                    {getStatusBadge(application.status)}
                                </div>

                                {/* Preliminary Screening History */}
                                {application.preliminary_reviewed_at && (
                                    <div className="border-l-4 border-blue-500 pl-4">
                                        <h4 className="font-medium text-sm mb-2">一次審査</h4>
                                        <div className="space-y-1 text-sm text-muted-foreground">
                                            <div>日時: {formatDate(application.preliminary_reviewed_at)}</div>
                                            {application.preliminary_reviewer && (
                                                <div>審査者: {application.preliminary_reviewer.name}</div>
                                            )}
                                            {application.preliminary_notes && (
                                                <div>メモ: {application.preliminary_notes}</div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Final Screening History */}
                                {application.final_reviewed_at && (
                                    <div className="border-l-4 border-green-500 pl-4">
                                        <h4 className="font-medium text-sm mb-2">最終審査</h4>
                                        <div className="space-y-1 text-sm text-muted-foreground">
                                            <div>日時: {formatDate(application.final_reviewed_at)}</div>
                                            {application.final_reviewer && (
                                                <div>審査者: {application.final_reviewer.name}</div>
                                            )}
                                            {application.final_notes && (
                                                <div>メモ: {application.final_notes}</div>
                                            )}
                                        </div>
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

