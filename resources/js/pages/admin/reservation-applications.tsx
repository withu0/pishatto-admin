import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {  Check, X, Eye, RefreshCw, User, Calendar, Star,  Phone, MapPin as LocationIcon, Users, Star as StarIcon, CheckCircle } from 'lucide-react';
import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';

interface ReservationApplication {
    id: number;
    cast: {
        id: number;
        nickname: string;
        avatar?: string;
        phone?: string;
        name?: string;
        birth_year?: number;
        height?: number;
        grade?: string;
        grade_points?: number;
        residence?: string;
        birthplace?: string;
        location?: string;
        profile_text?: string;
        points?: number;
        status?: string;
        created_at?: string;
        total_reservations?: number;
        category?: string;
    };
    status: 'pending' | 'approved' | 'rejected';
    applied_at: string;
    approved_at?: string;
    rejected_at?: string;
    rejection_reason?: string;
}

interface Reservation {
    id: number;
    guest: {
        id: number;
        nickname: string;
        avatar?: string;
        phone?: string;
        age?: number;
        location?: string;
        residence?: string;
        birthplace?: string;
        occupation?: string;
        education?: string;
        annual_income?: string;
        interests?: (string | { category: string; tag: string })[];
        points?: number;
        created_at?: string;
        total_reservations?: number;
    };
    scheduled_at: string;
    location?: string;
    duration?: number;
    details?: string;
    type: string;
    applications: ReservationApplication[];
}

interface Props {
    reservations: Reservation[];
}

export default function AdminReservationApplications({ reservations: initialReservations }: Props) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const [search, setSearch] = useState('');
    const [reservations, setReservations] = useState<Reservation[]>(initialReservations);
    const [selectedApplication, setSelectedApplication] = useState<ReservationApplication | null>(null);
    const [showRejectModal, setShowRejectModal] = useState(false);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [showMultiCastModal, setShowMultiCastModal] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [selectedCasts, setSelectedCasts] = useState<ReservationApplication[]>([]);
    const [currentReservation, setCurrentReservation] = useState<Reservation | null>(null);
    const [expandedReservations, setExpandedReservations] = useState<Set<number>>(new Set());

    const filtered = reservations.filter(
        (reservation) =>
            reservation.guest.nickname.includes(search) ||
            reservation.location?.includes(search) ||
            reservation.applications.some(app => app.cast.nickname.includes(search))
    );

    // Toggle reservation expansion
    const toggleReservationExpansion = (reservationId: number) => {
        setExpandedReservations(prev => {
            const newSet = new Set(prev);
            if (newSet.has(reservationId)) {
                newSet.delete(reservationId);
            } else {
                newSet.add(reservationId);
            }
            return newSet;
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return <Badge variant="secondary">保留中</Badge>;
            case 'approved':
                return <Badge variant="default" className="bg-green-500">承認済</Badge>;
            case 'rejected':
                return <Badge variant="destructive">却下済</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({ only: ['reservations'] });
    };

    const handleApprove = async (applicationId: number) => {
        console.log("AOO", applicationId);
        if (!confirm('この応募を承認しますか？')) return;

        try {
            const response = await fetch(`/admin/reservation-applications/${applicationId}/approve`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    admin_id: 1,
                    cast_id: selectedApplication?.cast.id,
                }),
            });


            if (response.ok) {
                // Refresh the data after successful approval
                handleRefresh();
            } else {
                throw new Error('Failed to approve application');
            }
        } catch (error) {
            console.error('Failed to approve application:', error);
            alert('承認に失敗しました');
        }
    };

    const handleReject = async () => {
        if (!selectedApplication) return;

        try {
            const response = await fetch(`/admin/reservation-applications/${selectedApplication.id}/reject`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    admin_id: 1, // TODO: Get from auth context
                    rejection_reason: rejectionReason,
                }),
            });

            if (response.ok) {
                setShowRejectModal(false);
                setSelectedApplication(null);
                setRejectionReason('');
                // Refresh the data after successful rejection
                handleRefresh();
            } else {
                throw new Error('Failed to reject application');
            }
        } catch (error) {
            console.error('Failed to reject application:', error);
            alert('却下に失敗しました');
        }
    };

    const handleViewDetails = (application: ReservationApplication, reservation?: Reservation) => {
        setSelectedApplication(application);
        if (reservation) {
            setCurrentReservation(reservation);
        }
        setShowDetailModal(true);
    };

    const closeDetailModal = () => {
        setShowDetailModal(false);
        setSelectedApplication(null);
    };

    // New function to handle multi-cast selection for Pishatto calls
    const handleMultiCastSelection = (reservation: Reservation) => {
        setCurrentReservation(reservation);
        // Get all pending applications for this reservation
        const pendingApps = reservation.applications.filter(app => app.status === 'pending');
        setSelectedCasts(pendingApps);
        setShowMultiCastModal(true);
    };

    const handleMultiCastApprove = async () => {
        if (!currentReservation || selectedCasts.length === 0) return;

        try {
            const response = await fetch(`/admin/reservation-applications/multi-approve`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    admin_id: 1, // TODO: Get actual admin ID
                    reservation_id: currentReservation.id,
                    cast_ids: selectedCasts.map(app => app.cast.id),
                }),
            });

            if (response.ok) {
                setReservations(prev => prev.map(reservation =>
                    reservation.id === currentReservation.id
                        ? {
                            ...reservation,
                            applications: reservation.applications.map(app =>
                    selectedCasts.some(selected => selected.id === app.id)
                        ? { ...app, status: 'approved' as const }
                                    : app.status === 'pending'
                            ? { ...app, status: 'rejected' as const }
                            : app
                            )
                        }
                        : reservation
                ));
                setShowMultiCastModal(false);
                setSelectedCasts([]);
                setCurrentReservation(null);
                // Show success message
                alert('複数のキャストが正常に承認されました');
            } else {
                console.error('Failed to approve multiple applications');
                alert('複数キャストの承認に失敗しました');
            }
        } catch (error) {
            console.error('Error approving multiple applications:', error);
        }
    };

    const toggleCastSelection = (application: ReservationApplication) => {
        setSelectedCasts(prev => {
            const isSelected = prev.some(app => app.id === application.id);
            if (isSelected) {
                return prev.filter(app => app.id !== application.id);
            } else {
                return [...prev, application];
            }
        });
    };

    const isCastSelected = (application: ReservationApplication) => {
        return selectedCasts.some(app => app.id === application.id);
    };

    // Get all applications for the same reservation
    const getReservationApplications = (reservationId: number) => {
        const reservation = reservations.find(r => r.id === reservationId);
        return reservation ? reservation.applications : [];
    };

    // Get pending applications count for a reservation
    const getPendingApplicationsCount = (reservationId: number) => {
        const reservation = reservations.find(r => r.id === reservationId);
        return reservation ? reservation.applications.filter(app => app.status === 'pending').length : 0;
    };

    // Get approved applications count for a reservation
    const getApprovedApplicationsCount = (reservationId: number) => {
        const reservation = reservations.find(r => r.id === reservationId);
        return reservation ? reservation.applications.filter(app => app.status === 'approved').length : 0;
    };

    // Update reservations when props change
    useEffect(() => {
        setReservations(initialReservations);
        setIsRefreshing(false);
    }, [initialReservations]);

    return (
        <AppLayout breadcrumbs={[{ title: '予約応募管理', href: '/admin/reservation-applications' }]}>
            <Head title="予約応募管理" />
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">予約応募管理</h1>
                    <Button
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                        className="gap-2"
                    >
                        <RefreshCw className={`w-4 h-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                        更新
                    </Button>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4 pb-2">
                        <CardTitle>予約応募一覧</CardTitle>
                        <div className="flex items-center gap-2">
                            <Input
                                placeholder="ゲスト・キャスト・場所で検索"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                className="max-w-xs"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm border">
                                <thead>
                                    <tr className="bg-muted">
                                        <th className="px-3 py-2 text-left font-semibold">予約ID</th>
                                        <th className="px-3 py-2 text-left font-semibold">ゲスト</th>
                                        <th className="px-3 py-2 text-left font-semibold">応募情報</th>
                                        <th className="px-3 py-2 text-left font-semibold">予約日時</th>
                                        <th className="px-3 py-2 text-left font-semibold">場所</th>
                                        <th className="px-3 py-2 text-left font-semibold">状態</th>
                                        <th className="px-3 py-2 text-left font-semibold">予約タイプ</th>
                                        <th className="px-3 py-2 text-left font-semibold">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="text-center py-6 text-muted-foreground">
                                                該当するデータがありません
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((reservation, reservationIdx) => {
                                            const isExpanded = expandedReservations.has(reservation.id);
                                            const pendingCount = reservation.applications.filter(app => app.status === 'pending').length;
                                            const approvedCount = reservation.applications.filter(app => app.status === 'approved').length;
                                            const rejectedCount = reservation.applications.filter(app => app.status === 'rejected').length;

                                            return (
                                                <React.Fragment key={reservation.id}>
                                                    {/* Reservation Summary Row */}
                                                    <tr
                                                        className="border-t bg-blue-50 hover:bg-blue-100 cursor-pointer"
                                                        onClick={() => toggleReservationExpansion(reservation.id)}
                                                    >
                                                        <td className="px-3 py-3">
                                                    <div className="flex items-center gap-2">
                                                                <span className="font-semibold text-blue-900">
                                                                    #{reservation.id}
                                                                </span>
                                                                {isExpanded ? (
                                                                    <span className="text-blue-600">▼</span>
                                                                ) : (
                                                                    <span className="text-blue-600">▶</span>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-8 h-8 rounded-full bg-blue-200 flex items-center justify-center">
                                                                    {reservation.guest.avatar ? (
                                                                        <img
                                                                            src={reservation.guest.avatar}
                                                                            alt={reservation.guest.nickname}
                                                                    className="w-8 h-8 rounded-full object-cover"
                                                                />
                                                            ) : (
                                                                        <span className="text-xs text-blue-600 font-bold">
                                                                            {reservation.guest.nickname.charAt(0)}
                                                                </span>
                                                            )}
                                                        </div>
                                                                <div>
                                                                    <div className="font-semibold text-blue-900">
                                                                        {reservation.guest.nickname}
                                                                    </div>
                                                                    <div className="text-xs text-blue-600">
                                                                        ゲスト #{reservation.guest.id}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="text-blue-800 font-medium">
                                                                応募数: {reservation.applications.length}件
                                                            </div>
                                                            <div className="text-xs text-blue-600">
                                                                保留: {pendingCount} | 承認: {approvedCount} | 却下: {rejectedCount}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="font-semibold text-blue-900">
                                                                {formatDate(reservation.scheduled_at)}
                                                            </div>
                                                            <div className="text-xs text-blue-600">
                                                                {reservation.duration || 1}時間
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="text-blue-800">
                                                                {reservation.location || '未設定'}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex gap-1">
                                                                {pendingCount > 0 && <Badge variant="secondary" className="text-xs">保留 {pendingCount}</Badge>}
                                                                {approvedCount > 0 && <Badge variant="default" className="bg-green-500 text-xs">承認 {approvedCount}</Badge>}
                                                                {rejectedCount > 0 && <Badge variant="destructive" className="text-xs">却下 {rejectedCount}</Badge>}
                                                                {reservation.applications.length === 0 && <Badge variant="outline" className="text-xs">応募なし</Badge>}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="text-blue-800">
                                                                {reservation.type === 'Pishatto' ? 'プレミアム' :
                                                                 reservation.type === 'normal' ? '通常' :
                                                                 reservation.type}
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-3">
                                                            <div className="flex gap-2">
                                                                {reservation.type === 'Pishatto' && pendingCount > 0 && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        className="bg-blue-600 hover:bg-blue-700 text-white"
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            handleMultiCastSelection(reservation);
                                                                        }}
                                                                    >
                                                                        <Users className="w-4 h-4" />
                                                                    </Button>
                                                                )}
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        // Show first application details as representative
                                                                        if (reservation.applications.length > 0) {
                                                                            handleViewDetails(reservation.applications[0], reservation);
                                                                        }
                                                                    }}
                                                                >
                                                                    <Eye className="w-4 h-4" />
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    {/* Expanded Applications */}
                                                    {isExpanded && reservation.applications.map((item, appIdx) => (
                                                        <tr key={item.id} className="border-t bg-gray-50">
                                                            <td className="px-3 py-2 pl-8">
                                                                <div className="text-sm text-gray-600">
                                                                    {appIdx + 1}
                                                                </div>
                                                            </td>
                                                            <td className="px-3 py-2 pl-8">
                                                                <div className="text-sm text-gray-600">
                                                                    -
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                            {item.cast.avatar ? (
                                                                <img
                                                                    src={item.cast.avatar}
                                                                    alt={item.cast.nickname}
                                                                    className="w-8 h-8 rounded-full object-cover"
                                                                />
                                                            ) : (
                                                                <span className="text-xs text-gray-500">
                                                                    {item.cast.nickname.charAt(0)}
                                                                </span>
                                                            )}
                                                        </div>
                                                                    <div>
                                                                        <div className="font-medium">{item.cast.nickname}</div>
                                                                        <div className="text-xs text-gray-500">
                                                                            キャスト #{item.cast.id}
                                                                        </div>
                                                                    </div>
                                                    </div>
                                                </td>
                                                            <td className="px-3 py-2">
                                                                <div className="text-sm text-gray-600">
                                                                    -
                                                                </div>
                                                            </td>
                                                            <td className="px-3 py-2">
                                                                <div className="text-sm text-gray-600">
                                                                    -
                                                                </div>
                                                            </td>
                                                <td className="px-3 py-2">{getStatusBadge(item.status)}</td>
                                                            <td className="px-3 py-2">
                                                                <div className="text-sm">
                                                                    {formatDate(item.applied_at)}
                                                                </div>
                                                            </td>
                                                <td className="px-3 py-2 flex gap-2">
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => handleViewDetails(item, reservation)}
                                                                >
                                                                    <Eye className="w-4 h-4" />
                                                                </Button>
                                                    {item.status === 'pending' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="default"
                                                                className="bg-green-600 hover:bg-green-700"
                                                                onClick={() => handleApprove(item.id)}
                                                            >
                                                                <Check className="w-4 h-4" />
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                onClick={() => {
                                                                    setSelectedApplication(item);
                                                                    setShowRejectModal(true);
                                                                }}
                                                            >
                                                                <X className="w-4 h-4" />
                                                            </Button>
                                                        </>
                                                    )}
                                                </td>
                                            </tr>
                                                    ))}
                                                </React.Fragment>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Detail Modal - Full version with better structure */}
            {showDetailModal && selectedApplication && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" style={{zIndex: 9999}}>
                    <div className="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto" style={{backgroundColor: 'white', color: 'black'}}>
                        {/* Header */}
                        <div className="flex items-center justify-between p-6 border-b border-gray-200">
                            <h2 className="text-2xl font-bold text-gray-900">予約詳細</h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={closeDetailModal}
                                className="text-gray-500 hover:text-gray-700"
                            >
                                <X className="w-5 h-5" />
                            </Button>
                        </div>

                        <div className="p-6">
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                {/* Guest Profile */}
                                <div className="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200">
                                    <div className="flex items-center mb-4">
                                        <User className="w-5 h-5 text-blue-600 mr-2" />
                                        <h3 className="text-lg font-semibold text-blue-900">ゲストプロフィール</h3>
                                    </div>

                                    {/* Basic Info */}
                                    <div className="flex items-center mb-6">
                                        <div className="w-20 h-20 rounded-full bg-blue-200 flex items-center justify-center mr-4 border-4 border-white shadow-lg">
                                            {currentReservation?.guest.avatar ? (
                                                <img
                                                    src={currentReservation.guest.avatar}
                                                    alt={currentReservation.guest.nickname}
                                                    className="w-20 h-20 rounded-full object-cover"
                                                />
                                            ) : (
                                                <span className="text-3xl text-blue-600 font-bold">
                                                    {currentReservation?.guest.nickname.charAt(0)}
                                                </span>
                                            )}
                                        </div>
                                        <div>
                                            <h4 className="text-2xl font-bold text-blue-900">
                                                {currentReservation?.guest.nickname}
                                            </h4>
                                            <p className="text-blue-600">ゲスト #{currentReservation?.guest.id}</p>
                                            {currentReservation?.guest.points && (
                                                <div className="flex items-center mt-1">
                                                    <StarIcon className="w-4 h-4 text-yellow-500 mr-1" />
                                                    <span className="text-blue-700 font-medium">{Number(currentReservation.guest.points).toLocaleString()}P</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Contact Info */}
                                    <div className="space-y-3 mb-6">
                                        {currentReservation?.guest.phone && (
                                            <div className="flex items-center">
                                                <Phone className="w-4 h-4 text-blue-600 mr-3" />
                                                <span className="text-blue-800">{currentReservation.guest.phone}</span>
                                            </div>
                                        )}
                                        {currentReservation?.guest.location && (
                                            <div className="flex items-center">
                                                <LocationIcon className="w-4 h-4 text-blue-600 mr-3" />
                                                <span className="text-blue-800">{currentReservation.guest.location}</span>
                                            </div>
                                        )}
                                        {currentReservation?.guest.residence && (
                                            <div className="flex items-center">
                                                <LocationIcon className="w-4 h-4 text-blue-600 mr-3" />
                                                <span className="text-blue-800">{currentReservation.guest.residence}</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Personal Info */}
                                    <div className="grid grid-cols-2 gap-4 mb-6">
                                        {currentReservation?.guest.age && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">年齢</p>
                                                <p className="text-blue-900 font-semibold">{currentReservation.guest.age}歳</p>
                                            </div>
                                        )}
                                        {currentReservation?.guest.birthplace && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">出身地</p>
                                                <p className="text-blue-900 font-semibold">{currentReservation.guest.birthplace}</p>
                                            </div>
                                        )}
                                        {currentReservation?.guest.occupation && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">職業</p>
                                                <p className="text-blue-900 font-semibold">{currentReservation.guest.occupation}</p>
                                            </div>
                                        )}
                                        {currentReservation?.guest.education && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">学歴</p>
                                                <p className="text-blue-900 font-semibold">{currentReservation.guest.education}</p>
                                            </div>
                                        )}
                                        {currentReservation?.guest.created_at && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">登録日</p>
                                                <p className="text-blue-900 font-semibold">{formatDate(currentReservation.guest.created_at)}</p>
                                            </div>
                                        )}
                                        {currentReservation?.guest.total_reservations && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">予約回数</p>
                                                <p className="text-blue-900 font-semibold">{currentReservation.guest.total_reservations}回</p>
                                            </div>
                                        )}
                                        {currentReservation?.guest.annual_income && (
                                            <div className="bg-blue-200 rounded-lg p-3">
                                                <p className="text-xs text-blue-600 mb-1">年収</p>
                                                <p className="text-blue-900 font-semibold">{currentReservation.guest.annual_income}</p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Interests */}
                                    {currentReservation?.guest.interests && currentReservation.guest.interests.length > 0 && (
                                        <div>
                                            <h5 className="text-sm font-semibold text-blue-700 mb-2">興味・関心</h5>
                                            <div className="flex flex-wrap gap-2">
                                                {currentReservation.guest.interests.map((interest, index) => (
                                                    <Badge key={index} variant="secondary" className="bg-blue-200 text-blue-800">
                                                        {typeof interest === 'string'
                                                            ? interest
                                                            : typeof interest === 'object' && interest.category && interest.tag
                                                                ? `${interest.category}: ${interest.tag}`
                                                                : JSON.stringify(interest)
                                                        }
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* Cast Profile */}
                                <div className="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 border border-purple-200">
                                    <div className="flex items-center mb-4">
                                        <Star className="w-5 h-5 text-purple-600 mr-2" />
                                        <h3 className="text-lg font-semibold text-purple-900">キャストプロフィール</h3>
                                    </div>

                                    {/* Basic Info */}
                                    <div className="flex items-center mb-6">
                                        <div className="w-20 h-20 rounded-full bg-purple-200 flex items-center justify-center mr-4 border-4 border-white shadow-lg">
                                            {selectedApplication.cast.avatar ? (
                                                <img
                                                    src={selectedApplication.cast.avatar}
                                                    alt={selectedApplication.cast.nickname}
                                                    className="w-20 h-20 rounded-full object-cover"
                                                />
                                            ) : (
                                                <span className="text-3xl text-purple-600 font-bold">
                                                    {selectedApplication.cast.nickname.charAt(0)}
                                                </span>
                                            )}
                                        </div>
                                        <div>
                                            <h4 className="text-2xl font-bold text-purple-900">
                                                {selectedApplication.cast.nickname}
                                            </h4>
                                            <p className="text-purple-600">キャスト #{selectedApplication.cast.id}</p>
                                            {selectedApplication.cast.points && (
                                                <div className="flex items-center mt-1">
                                                    <StarIcon className="w-4 h-4 text-yellow-500 mr-1" />
                                                    <span className="text-purple-700 font-medium">{Number(selectedApplication.cast.points).toLocaleString()}P</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Contact Info */}
                                    <div className="space-y-3 mb-6">
                                        {selectedApplication.cast.phone && (
                                            <div className="flex items-center">
                                                <Phone className="w-4 h-4 text-purple-600 mr-3" />
                                                <span className="text-purple-800">{selectedApplication.cast.phone}</span>
                                            </div>
                                        )}
                                        {selectedApplication.cast.location && (
                                            <div className="flex items-center">
                                                <LocationIcon className="w-4 h-4 text-purple-600 mr-3" />
                                                <span className="text-purple-800">{selectedApplication.cast.location}</span>
                                            </div>
                                        )}
                                        {selectedApplication.cast.residence && (
                                            <div className="flex items-center">
                                                <LocationIcon className="w-4 h-4 text-purple-600 mr-3" />
                                                <span className="text-purple-800">{selectedApplication.cast.residence}</span>
                                            </div>
                                        )}
                                        {selectedApplication.cast.category && (
                                            <div className="flex items-center">
                                                <User className="w-4 h-4 text-purple-600 mr-3" />
                                                <span className="text-purple-800">{selectedApplication.cast.category}</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Professional Info */}
                                    <div className="grid grid-cols-2 gap-4 mb-6">
                                        {selectedApplication.cast.birth_year && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">生年</p>
                                                <p className="text-purple-900 font-semibold">{selectedApplication.cast.birth_year}年</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.height && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">身長</p>
                                                <p className="text-purple-900 font-semibold">{selectedApplication.cast.height}cm</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.grade && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">グレード</p>
                                                <p className="text-purple-900 font-semibold">{selectedApplication.cast.grade}</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.grade_points && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">グレードポイント</p>
                                                <p className="text-purple-900 font-semibold">{Number(selectedApplication.cast.grade_points).toLocaleString()}P</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.created_at && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">登録日</p>
                                                <p className="text-purple-900 font-semibold">{formatDate(selectedApplication.cast.created_at)}</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.total_reservations && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">予約回数</p>
                                                <p className="text-purple-900 font-semibold">{selectedApplication.cast.total_reservations}回</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.birthplace && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">出身地</p>
                                                <p className="text-purple-900 font-semibold">{selectedApplication.cast.birthplace}</p>
                                            </div>
                                        )}
                                        {selectedApplication.cast.status && (
                                            <div className="bg-purple-200 rounded-lg p-3">
                                                <p className="text-xs text-purple-600 mb-1">ステータス</p>
                                                <p className="text-purple-900 font-semibold">{selectedApplication.cast.status}</p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Profile Text */}
                                    {selectedApplication.cast.profile_text && (
                                        <div className="mb-6">
                                            <h5 className="text-sm font-semibold text-purple-700 mb-2">プロフィール</h5>
                                            <p className="text-purple-800 text-sm leading-relaxed">{selectedApplication.cast.profile_text}</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Reservation Details */}
                            <div className="mt-6 bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border border-green-200">
                                <div className="flex items-center mb-4">
                                    <Calendar className="w-5 h-5 text-green-600 mr-2" />
                                    <h3 className="text-lg font-semibold text-green-900">予約詳細</h3>
                                </div>
                                {/* Additional Reservation Info */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="bg-green-200 rounded-lg p-3">
                                        <p className="text-xs text-green-600 mb-1">予約ID</p>
                                        <p className="text-green-900 font-semibold">#{currentReservation?.id}</p>
                                    </div>
                                    <div className="bg-green-200 rounded-lg p-3">
                                        <p className="text-xs text-green-600 mb-1">予約タイプ</p>
                                        <p className="text-green-900 font-semibold">
                                            {currentReservation?.type === 'Pishatto' ? 'プレミアム' :
                                             currentReservation?.type === 'normal' ? '通常' :
                                             currentReservation?.type}
                                        </p>
                                    </div>
                                    {currentReservation?.duration && (
                                        <div className="bg-green-200 rounded-lg p-3">
                                            <p className="text-xs text-green-600 mb-1">予約時間</p>
                                            <p className="text-green-900 font-semibold">{currentReservation.duration}時間</p>
                                        </div>
                                    )}
                                    <div className="bg-green-200 rounded-lg p-3">
                                        <p className="text-xs text-green-600 mb-1">予約場所</p>
                                        <p className="text-green-900 font-semibold">{currentReservation?.location || '未設定'}</p>
                                    </div>
                                </div>

                                {/* Application Statistics */}
                                <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div className="bg-blue-200 rounded-lg p-3">
                                        <p className="text-xs text-blue-600 mb-1">総応募数</p>
                                        <p className="text-blue-900 font-semibold text-lg">
                                            {currentReservation?.applications.length || 0}件
                                        </p>
                                    </div>
                                    <div className="bg-yellow-200 rounded-lg p-3">
                                        <p className="text-xs text-yellow-600 mb-1">保留中</p>
                                        <p className="text-yellow-900 font-semibold text-lg">
                                            {currentReservation?.applications.filter(app => app.status === 'pending').length || 0}件
                                        </p>
                                    </div>
                                    <div className="bg-green-200 rounded-lg p-3">
                                        <p className="text-xs text-green-600 mb-1">承認済</p>
                                        <p className="text-green-900 font-semibold text-lg">
                                            {currentReservation?.applications.filter(app => app.status === 'approved').length || 0}件
                                        </p>
                                    </div>
                                </div>

                                {/* Reservation Details Text */}
                                {currentReservation?.details && (
                                    <div className="mt-4 bg-green-200 rounded-lg p-4">
                                        <p className="text-xs text-green-600 mb-2">予約詳細情報</p>
                                        <p className="text-green-900 whitespace-pre-wrap text-sm leading-relaxed">
                                            {currentReservation.details}
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Application Details */}
                            <div className="mt-6 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 border border-gray-200">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">応募情報</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-gray-600">応募日時</p>
                                        <p className="font-medium text-gray-900">{formatDate(selectedApplication.applied_at)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600">ステータス</p>
                                        <div className="mt-1">{getStatusBadge(selectedApplication.status)}</div>
                                    </div>
                                    {selectedApplication.approved_at && (
                                        <div>
                                            <p className="text-sm text-gray-600">承認日時</p>
                                            <p className="font-medium text-gray-900">{formatDate(selectedApplication.approved_at)}</p>
                                        </div>
                                    )}
                                    {selectedApplication.rejected_at && (
                                        <div>
                                            <p className="text-sm text-gray-600">却下日時</p>
                                            <p className="font-medium text-gray-900">{formatDate(selectedApplication.rejected_at)}</p>
                                        </div>
                                    )}
                                    {selectedApplication.rejection_reason && (
                                        <div className="md:col-span-2">
                                            <p className="text-sm text-gray-600">却下理由</p>
                                            <p className="font-medium text-gray-900">{selectedApplication.rejection_reason}</p>
                                        </div>
                                    )}
                                </div>
                            </div>


                        </div>

                        {/* Footer */}
                        <div className="flex justify-end gap-3 p-6 border-t border-gray-200">
                            <Button variant="outline" onClick={closeDetailModal}>
                                閉じる
                            </Button>
                            {selectedApplication.status === 'pending' && (
                                <>
                                    <Button
                                        variant="default"
                                        className="bg-green-600 hover:bg-green-700"
                                        onClick={() => {
                                            closeDetailModal();
                                            handleApprove(selectedApplication.id);
                                        }}
                                    >
                                        <Check className="w-4 h-4 mr-2" />
                                        承認
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            closeDetailModal();
                                            setShowRejectModal(true);
                                        }}
                                    >
                                        <X className="w-4 h-4 mr-2" />
                                        却下
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* Reject Modal */}
            {showRejectModal && selectedApplication && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">
                            応募を却下
                        </h3>
                        <p className="text-gray-600 mb-4">
                            {selectedApplication.cast.nickname} の応募を却下しますか？
                        </p>
                        <div className="mb-4">
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                却下理由 (オプション)
                            </label>
                            <textarea
                                value={rejectionReason}
                                onChange={(e) => setRejectionReason(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                rows={3}
                                placeholder="却下理由を入力してください..."
                            />
                        </div>
                        <div className="flex space-x-3">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setShowRejectModal(false);
                                    setSelectedApplication(null);
                                    setRejectionReason('');
                                }}
                                className="flex-1"
                            >
                                キャンセル
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleReject}
                                className="flex-1"
                            >
                                却下
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Multi-Cast Selection Modal */}
            {showMultiCastModal && currentReservation && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between mb-6">
                            <h3 className="text-xl font-semibold text-gray-900">
                                複数キャスト選択 - プレミアム予約
                            </h3>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setShowMultiCastModal(false);
                                    setSelectedCasts([]);
                                    setCurrentReservation(null);
                                }}
                            >
                                <X className="w-5 h-5" />
                            </Button>
                        </div>

                        {/* Reservation Info */}
                        <div className="bg-blue-50 rounded-lg p-4 mb-6">
                            <h4 className="font-semibold text-blue-900 mb-2">予約情報</h4>
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="text-blue-600">場所:</span> {currentReservation.location || '未設定'}
                                </div>
                                <div>
                                    <span className="text-blue-600">時間:</span> {formatDate(currentReservation.scheduled_at)}
                                </div>
                                <div>
                                    <span className="text-blue-600">期間:</span> {currentReservation.duration || 1}時間
                                </div>
                                <div>
                                    <span className="text-blue-600">タイプ:</span> プレミアム
                                </div>
                            </div>
                        </div>

                        {/* Cast Selection */}
                        <div className="mb-6">
                            <h4 className="font-semibold text-gray-900 mb-4">
                                応募キャスト一覧 ({selectedCasts.length} 人中選択中)
                            </h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {selectedCasts.map((application) => (
                                    <div
                                        key={application.id}
                                        className={`border rounded-lg p-4 cursor-pointer transition-colors ${
                                            isCastSelected(application)
                                                ? 'border-blue-500 bg-blue-50'
                                                : 'border-gray-200 hover:border-gray-300'
                                        }`}
                                        onClick={() => toggleCastSelection(application)}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-3">
                                                <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    {application.cast.avatar ? (
                                                        <img
                                                            src={application.cast.avatar}
                                                            alt={application.cast.nickname}
                                                            className="w-10 h-10 rounded-full object-cover"
                                                        />
                                                    ) : (
                                                        <span className="text-lg font-bold text-gray-600">
                                                            {application.cast.nickname.charAt(0)}
                                                        </span>
                                                    )}
                                                </div>
                                                <div>
                                                    <h5 className="font-medium text-gray-900">
                                                        {application.cast.nickname}
                                                    </h5>
                                                    <p className="text-sm text-gray-600">
                                                        キャスト #{application.cast.id}
                                                    </p>
                                                    {application.cast.points && (
                                                        <p className="text-sm text-yellow-600">
                                                            {Number(application.cast.points).toLocaleString()}P
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center">
                                                {isCastSelected(application) ? (
                                                    <CheckCircle className="w-5 h-5 text-blue-600" />
                                                ) : (
                                                    <div className="w-5 h-5 border-2 border-gray-300 rounded-full" />
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setShowMultiCastModal(false);
                                    setSelectedCasts([]);
                                    setCurrentReservation(null);
                                }}
                            >
                                キャンセル
                            </Button>
                            <Button
                                variant="default"
                                className="bg-blue-600 hover:bg-blue-700"
                                onClick={handleMultiCastApprove}
                                disabled={selectedCasts.length === 0}
                            >
                                <Users className="w-4 h-4 mr-2" />
                                選択したキャストを承認 ({selectedCasts.length}人)
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
