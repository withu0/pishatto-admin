import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

type GuestCandidate = {
    guest_id: number;
    current_grade: string;
    target_grade: string | null;
    quarter_usage: number;
    threshold: number;
};

type CastCandidate = {
    cast_id: number;
    current_grade: string;
    target_grade: string | null;
    quarter_earned_points: number;
    threshold: number;
};

type QuarterlyInfo = {
    current_quarter: string;
    quarter_start: string;
    quarter_end: string;
    next_reset_date: string;
    days_until_reset: number;
};

interface Props {
    guests: {
        data: any[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    gradeStats: {
        total_guests: number;
        grade_distribution: Record<string, number>;
        grade_info: any;
    };
    guestCandidates: GuestCandidate[];
    castCandidates: CastCandidate[];
    quarterlyInfo: QuarterlyInfo;
}

export default function AdminGrades({ guests, gradeStats, guestCandidates: initialGuestCandidates, castCandidates: initialCastCandidates, quarterlyInfo: initialQuarterlyInfo }: Props) {
    const [guestUpgradeCandidates, setGuestUpgradeCandidates] = useState<GuestCandidate[]>(initialGuestCandidates || []);
    const [castUpgradeCandidates, setCastUpgradeCandidates] = useState<CastCandidate[]>(initialCastCandidates || []);
    const [guestDowngradeCandidates, setGuestDowngradeCandidates] = useState<GuestCandidate[]>([]);
    const [castDowngradeCandidates, setCastDowngradeCandidates] = useState<CastCandidate[]>([]);
    const [evaluationInfo, setEvaluationInfo] = useState<{
        current_quarter: string;
        evaluation_period: string;
        evaluation_start: string;
        evaluation_end: string;
        next_evaluation_date: string;
    } | null>(null);
    const [quarterlyInfo, setQuarterlyInfo] = useState<QuarterlyInfo | null>(initialQuarterlyInfo || null);
    const [loading, setLoading] = useState(false);
    const [approvingId, setApprovingId] = useState<number | null>(null);



    const fetchCandidates = async () => {
        setLoading(true);
        try {
            // Fetch upgrade candidates
            const upgradeRes = await fetch('/api/admin/grades/candidates');
            const upgradeJson = await upgradeRes.json();
            
            // Fetch downgrade candidates
            const downgradeRes = await fetch('/api/admin/grades/downgrade-candidates');
            const downgradeJson = await downgradeRes.json();
            
            // Fetch evaluation info
            const evalRes = await fetch('/api/admin/grades/evaluation-info');
            const evalJson = await evalRes.json();
            
            // Fetch quarterly points info
            const quarterlyRes = await fetch('/api/admin/grades/quarterly-points-info');
            const quarterlyJson = await quarterlyRes.json();
            
            if (upgradeJson.success) {
                setGuestUpgradeCandidates(upgradeJson.data.guests || []);
                setCastUpgradeCandidates(upgradeJson.data.casts || []);
            } else {
                console.warn('Failed to fetch upgrade candidates:', upgradeJson);
                setGuestUpgradeCandidates([]);
                setCastUpgradeCandidates([]);
            }
            
            if (downgradeJson.success) {
                setGuestDowngradeCandidates(downgradeJson.data.guests || []);
                setCastDowngradeCandidates(downgradeJson.data.casts || []);
            } else {
                console.warn('Failed to fetch downgrade candidates:', downgradeJson);
                setGuestDowngradeCandidates([]);
                setCastDowngradeCandidates([]);
            }
            
            if (evalJson.success) {
                setEvaluationInfo(evalJson.data);
            } else {
                console.warn('Failed to fetch evaluation info:', evalJson);
                setEvaluationInfo(null);
            }
            
            if (quarterlyJson.success) {
                setQuarterlyInfo(quarterlyJson.data);
            } else {
                console.warn('Failed to fetch quarterly info:', quarterlyJson);
                setQuarterlyInfo(null);
            }
        } catch (error) {
            console.error('Error fetching candidates:', error);
            toast.error('候補の取得に失敗しました');
            // Set empty arrays to prevent undefined errors
            setGuestUpgradeCandidates([]);
            setCastUpgradeCandidates([]);
            setGuestDowngradeCandidates([]);
            setCastDowngradeCandidates([]);
            setEvaluationInfo(null);
            setQuarterlyInfo(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchCandidates();
    }, []);

    const approveGuest = async (guestId: number) => {
        setApprovingId(guestId);
        try {
            const res = await fetch('/api/admin/grades/approve-guest', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ guest_id: guestId })
            });
            const json = await res.json();
            if (json.success) {
                toast.success('ゲストの昇格を承認しました');
                fetchCandidates();
            } else {
                toast.error('承認に失敗しました');
            }
        } catch {
            toast.error('承認に失敗しました');
        } finally {
            setApprovingId(null);
        }
    };

    const approveCast = async (castId: number) => {
        setApprovingId(castId);
        try {
            const res = await fetch('/api/admin/grades/approve-cast', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cast_id: castId })
            });
            const json = await res.json();
            if (json.success) {
                toast.success('キャストの昇格を承認しました');
                fetchCandidates();
            } else {
                toast.error('承認に失敗しました');
            }
        } catch {
            toast.error('承認に失敗しました');
        } finally {
            setApprovingId(null);
        }
    };

    const runAutoDowngrade = async () => {
        try {
            const res = await fetch('/api/admin/grades/auto-downgrade', { method: 'POST' });
            const json = await res.json();
            if (json.success) {
                toast.success('自動降格を実行しました');
            } else {
                toast.error('自動降格の実行に失敗しました');
            }
        } catch {
            toast.error('自動降格の実行に失敗しました');
        }
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'グレード管理', href: '/admin/grades' }] }>
            <Head title="グレード管理" />
            <div className="p-6">
                <h1 className="text-2xl font-bold mb-4">グレード管理</h1>
                <Card className="mb-4">
                    <CardHeader>
                        <CardTitle>サマリー</CardTitle>
                        <div className="text-sm text-muted-foreground">
                            年4回の四半期評価システム：1-3月、4-6月、7-9月、10-12月の各期間の実績に基づいて評価を実施します。
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="text-center">
                                <div className="text-2xl font-bold text-blue-600">{gradeStats?.total_guests || 0}</div>
                                <div className="text-sm text-muted-foreground">総ゲスト数</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-green-600">{gradeStats?.grade_distribution?.gold || 0}</div>
                                <div className="text-sm text-muted-foreground">ゴールド以上</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-purple-600">{gradeStats?.grade_distribution?.platinum || 0}</div>
                                <div className="text-sm text-muted-foreground">プラチナ以上</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Quarterly Points Information */}
                {quarterlyInfo && (
                    <Card className="mb-4">
                        <CardHeader>
                            <CardTitle>四半期ポイント情報</CardTitle>
                            <div className="text-sm text-muted-foreground">
                                現在の四半期のポイント蓄積状況と次回リセット日
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div className="text-center">
                                    <div className="text-lg font-semibold text-blue-600">{quarterlyInfo.current_quarter || 'N/A'}</div>
                                    <div className="text-sm text-muted-foreground">現在の四半期</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-lg font-semibold text-green-600">
                                        {quarterlyInfo.quarter_start ? new Date(quarterlyInfo.quarter_start).toLocaleDateString('ja-JP') : 'N/A'}
                                    </div>
                                    <div className="text-sm text-muted-foreground">四半期開始日</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-lg font-semibold text-orange-600">
                                        {quarterlyInfo.quarter_end ? new Date(quarterlyInfo.quarter_end).toLocaleDateString('ja-JP') : 'N/A'}
                                    </div>
                                    <div className="text-sm text-muted-foreground">四半期終了日</div>
                                </div>
                                <div className="text-center">
                                    <div className="text-lg font-semibold text-red-600">
                                        {quarterlyInfo.days_until_reset !== undefined && quarterlyInfo.days_until_reset > 0 ? `${quarterlyInfo.days_until_reset}日` : '本日'}
                                    </div>
                                    <div className="text-sm text-muted-foreground">次回リセットまで</div>
                                </div>
                            </div>
                            <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                                <div className="text-sm text-blue-800">
                                    <strong>次回ポイントリセット:</strong> {quarterlyInfo.next_reset_date ? new Date(quarterlyInfo.next_reset_date).toLocaleDateString('ja-JP') : 'N/A'} 00:01
                                </div>
                                <div className="text-xs text-blue-600 mt-1">
                                    この日時になると、キャストのポイントとゲストのグレードポイントが自動的にリセットされます
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
                <div className="flex gap-2 mb-4">
                    <Button variant="outline" onClick={fetchCandidates} disabled={loading}>
                        {loading ? '読み込み中...' : '候補を再読み込み'}
                    </Button>
                    <Button variant="destructive" onClick={runAutoDowngrade}>自動降格を実行</Button>
                </div>
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>ゲスト 昇格候補</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {!guestUpgradeCandidates || guestUpgradeCandidates.length === 0 ? (
                                <div className="text-sm text-muted-foreground">候補がありません</div>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted">
                                            <th className="px-3 py-2 text-left">ID</th>
                                            <th className="px-3 py-2 text-left">現グレード</th>
                                            <th className="px-3 py-2 text-left">対象グレード</th>
                                            <th className="px-3 py-2 text-left">四半期使用</th>
                                            <th className="px-3 py-2 text-left">閾値</th>
                                            <th className="px-3 py-2 text-left">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {guestUpgradeCandidates.map((g) => (
                                            <tr key={g.guest_id} className="border-t">
                                                <td className="px-3 py-2">{g.guest_id}</td>
                                                <td className="px-3 py-2"><Badge variant="secondary">{g.current_grade || 'N/A'}</Badge></td>
                                                <td className="px-3 py-2"><Badge>{g.target_grade || 'N/A'}</Badge></td>
                                                <td className="px-3 py-2">{(g.quarter_usage || 0).toLocaleString()} pt</td>
                                                <td className="px-3 py-2">{(g.threshold || 0).toLocaleString()} pt</td>
                                                <td className="px-3 py-2">
                                                    <Button size="sm" onClick={() => approveGuest(g.guest_id)} disabled={approvingId === g.guest_id}>承認</Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>キャスト 昇格候補</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {!castUpgradeCandidates || castUpgradeCandidates.length === 0 ? (
                                <div className="text-sm text-muted-foreground">候補がありません</div>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted">
                                            <th className="px-3 py-2 text-left">ID</th>
                                            <th className="px-3 py-2 text-left">現グレード</th>
                                            <th className="px-3 py-2 text-left">対象グレード</th>
                                            <th className="px-3 py-2 text-left">四半期獲得</th>
                                            <th className="px-3 py-2 text-left">閾値</th>
                                            <th className="px-3 py-2 text-left">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {castUpgradeCandidates.map((c) => (
                                            <tr key={c.cast_id} className="border-t">
                                                <td className="px-3 py-2">{c.cast_id}</td>
                                                <td className="px-3 py-2"><Badge variant="secondary">{c.current_grade || 'N/A'}</Badge></td>
                                                <td className="px-3 py-2"><Badge>{c.target_grade || 'N/A'}</Badge></td>
                                                <td className="px-3 py-2">{(c.quarter_earned_points || 0).toLocaleString()} pt</td>
                                                <td className="px-3 py-2">{(c.threshold || 0).toLocaleString()} pt</td>
                                                <td className="px-3 py-2">
                                                    <Button size="sm" onClick={() => approveCast(c.cast_id)} disabled={approvingId === c.cast_id}>承認</Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                </div>
                
                {/* Downgrade Candidates Section */}
                <div className="mt-6">
                    <h2 className="text-xl font-bold mb-4">降格候補（自動降格対象）</h2>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Guest Downgrade Candidates */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-destructive">ゲスト 降格候補</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!guestDowngradeCandidates || guestDowngradeCandidates.length === 0 ? (
                                    <div className="text-sm text-muted-foreground">候補がありません</div>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="bg-muted">
                                                <th className="px-3 py-2 text-left">ID</th>
                                                <th className="px-3 py-2 text-left">現グレード</th>
                                                <th className="px-3 py-2 text-left">対象グレード</th>
                                                <th className="px-3 py-2 text-left">四半期使用</th>
                                                <th className="px-3 py-2 text-left">閾値</th>
                                                <th className="px-3 py-2 text-left">状況</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {guestDowngradeCandidates.map((g) => (
                                                <tr key={g.guest_id} className="border-t">
                                                    <td className="px-3 py-2">{g.guest_id}</td>
                                                    <td className="px-3 py-2"><Badge variant="destructive">{g.current_grade || 'N/A'}</Badge></td>
                                                    <td className="px-3 py-2"><Badge variant="outline">{g.target_grade || 'N/A'}</Badge></td>
                                                    <td className="px-3 py-2">{(g.quarter_usage || 0).toLocaleString()} pt</td>
                                                    <td className="px-3 py-2">{(g.threshold || 0).toLocaleString()} pt</td>
                                                    <td className="px-3 py-2">
                                                        <Badge variant="destructive">自動降格対象</Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </CardContent>
                        </Card>
                        
                        {/* Cast Downgrade Candidates */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-destructive">キャスト 降格候補</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!castDowngradeCandidates || castDowngradeCandidates.length === 0 ? (
                                    <div className="text-sm text-muted-foreground">候補がありません</div>
                                ) : (
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="bg-muted">
                                                <th className="px-3 py-2 text-left">ID</th>
                                                <th className="px-3 py-2 text-left">現グレード</th>
                                                <th className="px-3 py-2 text-left">対象グレード</th>
                                                <th className="px-3 py-2 text-left">四半期獲得</th>
                                                <th className="px-3 py-2 text-left">閾値</th>
                                                <th className="px-3 py-2 text-left">状況</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {castDowngradeCandidates.map((c) => (
                                                <tr key={c.cast_id} className="border-t">
                                                    <td className="px-3 py-2">{c.cast_id}</td>
                                                    <td className="px-3 py-2"><Badge variant="destructive">{c.current_grade || 'N/A'}</Badge></td>
                                                    <td className="px-3 py-2"><Badge variant="outline">{c.target_grade || 'N/A'}</Badge></td>
                                                    <td className="px-3 py-2">{(c.quarter_earned_points || 0).toLocaleString()} pt</td>
                                                    <td className="px-3 py-2">{(c.threshold || 0).toLocaleString()} pt</td>
                                                    <td className="px-3 py-2">
                                                        <Badge variant="destructive">自動降格対象</Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}


