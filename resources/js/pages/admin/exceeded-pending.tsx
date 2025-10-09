import React, { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Clock, Users, DollarSign, RefreshCw, AlertTriangle } from 'lucide-react';

interface PointTransaction {
  id: number;
  guest_id: number | null;
  cast_id: number | null;
  type: string;
  amount: number;
  reservation_id: number | null;
  payment_id: number | null;
  description: string | null;
  gift_type: string | null;
  created_at: string;
  updated_at: string;
  guest?: {
    id: number;
    nickname: string;
    phone: string;
  } | null;
  cast?: {
    id: number;
    nickname: string;
    grade: string;
  } | null;
  reservation?: {
    id: number;
    type: string;
    duration: number;
    scheduled_at: string;
    started_at: string | null;
    ended_at: string | null;
    guest?: {
      id: number;
      nickname: string;
      phone: string;
    } | null;
  } | null;
  payment?: {
    id: number;
    amount: number;
    status: string;
    stripe_payment_intent_id: string | null;
    is_automatic: boolean;
    created_at: string;
  } | null;
}

interface CastFinancialSummary {
  cast_id: number;
  nickname: string;
  grade: string;
  total_points: number;
  exceeded_points: number;
  automatic_payments: number;
  payment_count: number;
}

interface TransactionGroup {
  reservation_id: number | null;
  reservation: {
    id: number;
    type: string;
    duration: number;
    scheduled_at: string;
    started_at: string | null;
    ended_at: string | null;
  } | null;
  transactions: PointTransaction[];
  total_amount: number;
  guest: {
    id: number;
    nickname: string;
    phone: string;
  } | null;
  casts: Array<{
    id: number;
    nickname: string;
    grade: string;
  }>;
  cast_financial_summary: CastFinancialSummary[];
  created_at: string;
}

const PointTransactionPage: React.FC = () => {
  const [transactionGroups, setTransactionGroups] = useState<TransactionGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [cancellingPayments, setCancellingPayments] = useState<Set<number>>(new Set());

  useEffect(() => {
    fetchPointTransactions();
  }, []);

  const fetchPointTransactions = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch('/api/admin/point-transactions', {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      console.log('API Response Status:', response.status);
      const data = await response.json();
      console.log('API Response Data:', data);

      if (data.success) {
        // Group transactions by reservation_id
        const grouped = groupTransactionsByReservation(data.data);
        setTransactionGroups(grouped);
      } else {
        setError(data.message || 'ポイント取引データの取得に失敗しました');
      }
    } catch (err) {
      setError('ネットワークエラーが発生しました');
      console.error('Error fetching point transactions:', err);
    } finally {
      setLoading(false);
    }
  };

  const groupTransactionsByReservation = (transactions: PointTransaction[]): TransactionGroup[] => {
    const groups: { [key: string]: TransactionGroup } = {};

    transactions.forEach(transaction => {
      // Hide negative exceeded_pending records
      if (transaction.type === 'exceeded_pending' && transaction.amount < 0) {
        return; // Skip this transaction
      }

      const key = transaction.reservation_id ? `reservation_${transaction.reservation_id}` : 'no_reservation';

      if (!groups[key]) {
        groups[key] = {
          reservation_id: transaction.reservation_id,
          reservation: transaction.reservation || null,
          transactions: [],
          total_amount: 0,
          guest: transaction.guest || null,
          casts: [],
          cast_financial_summary: [],
          created_at: transaction.created_at
        };
      }

      groups[key].transactions.push(transaction);
      groups[key].total_amount += transaction.amount;

      // Collect guest information from any transaction that has it
      if (transaction.guest && !groups[key].guest) {
        groups[key].guest = transaction.guest;
      }

      // Also check reservation's guest information if transaction guest is not available
      if (!groups[key].guest && transaction.reservation?.guest) {
        groups[key].guest = transaction.reservation.guest;
      }

      // Collect cast information from transfer transactions
      if (transaction.cast && transaction.type === 'transfer') {
        const existingCast = groups[key].casts.find(cast => cast.id === transaction.cast!.id);
        if (!existingCast) {
          groups[key].casts.push({
            id: transaction.cast.id,
            nickname: transaction.cast.nickname,
            grade: transaction.cast.grade
          });
        }
      }
    });

    // Calculate financial summary for each group
    Object.values(groups).forEach(group => {
      if (group.reservation_id) {
        group.cast_financial_summary = calculateCastFinancialSummary(group);
      }
    });

    return Object.values(groups).sort((a, b) =>
      new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    );
  };

  const calculateCastFinancialSummary = (group: TransactionGroup): CastFinancialSummary[] => {
    const castSummary: { [key: number]: CastFinancialSummary } = {};

    // Initialize cast summaries
    group.casts.forEach(cast => {
      castSummary[cast.id] = {
        cast_id: cast.id,
        nickname: cast.nickname,
        grade: cast.grade,
        total_points: 0,
        exceeded_points: 0,
        automatic_payments: 0,
        payment_count: 0
      };
    });

    // Process transactions
    group.transactions.forEach(transaction => {
      if (transaction.cast_id && castSummary[transaction.cast_id]) {
        const castId = transaction.cast_id;

        // Transfer transactions (cast earnings)
        if (transaction.type === 'transfer' && transaction.amount > 0) {
          castSummary[castId].total_points += transaction.amount;
        }

        // Exceeded pending transactions (what guest needs to pay)
        if (transaction.type === 'exceeded_pending' && transaction.amount > 0) {
          castSummary[castId].exceeded_points += transaction.amount;
        }

        // Automatic payments (credit card payments)
        if (transaction.payment && transaction.payment.is_automatic && transaction.payment.status === 'paid') {
          castSummary[castId].automatic_payments += transaction.payment.amount;
          castSummary[castId].payment_count += 1;
        }
      }
    });

    return Object.values(castSummary);
  };

  const refreshData = () => {
    fetchPointTransactions();
  };

  const cancelPayment = async (paymentId: number) => {
    try {
      setCancellingPayments(prev => new Set(prev).add(paymentId));

      const response = await fetch('/api/admin/exceeded-pending/cancel-payment', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ payment_id: paymentId }),
      });

      const data = await response.json();

      if (data.success) {
        // Refresh the data to show updated status
        await fetchPointTransactions();
        alert(`支払いがキャンセルされました。返金ポイント: ${data.refunded_points}P`);
      } else {
        alert(`支払いのキャンセルに失敗しました: ${data.message}`);
      }
    } catch (error) {
      console.error('Error cancelling payment:', error);
      alert('支払いのキャンセル中にエラーが発生しました');
    } finally {
      setCancellingPayments(prev => {
        const newSet = new Set(prev);
        newSet.delete(paymentId);
        return newSet;
      });
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('ja-JP', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getTypeLabel = (type: string) => {
    const typeLabels: { [key: string]: string } = {
      'buy': '購入',
      'transfer': '転送',
      'convert': '変換',
      'gift': 'ギフト',
      'exceeded_pending': '超過時間保留',
      'pending': '保留',
      'refund': '返金'
    };
    return typeLabels[type] || type;
  };

  const getTypeBadgeColor = (type: string) => {
    const colorMap: { [key: string]: string } = {
      'buy': 'bg-blue-100 text-blue-800',
      'transfer': 'bg-green-100 text-green-800',
      'convert': 'bg-purple-100 text-purple-800',
      'gift': 'bg-pink-100 text-pink-800',
      'exceeded_pending': 'bg-orange-100 text-orange-800',
      'pending': 'bg-yellow-100 text-yellow-800',
      'refund': 'bg-red-100 text-red-800'
    };
    return colorMap[type] || 'bg-gray-100 text-gray-800';
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="h-8 w-8 animate-spin" />
        <span className="ml-2">ポイント取引を読み込み中...</span>
      </div>
    );
  }

  return (
    <AppLayout>
      <Head title="ポイント取引管理" />
      <div className="container mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">ポイント取引管理</h1>
          <p className="text-gray-600 mt-2">
              すべてのポイント取引を表示します
          </p>
        </div>
        <div className="flex space-x-2">
          <Button
              onClick={refreshData}
            variant="outline"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            更新
          </Button>
        </div>
      </div>

      {error && (
        <Alert className="border-red-200 bg-red-50">
          <AlertDescription className="text-red-800 whitespace-pre-line">
            {error}
          </AlertDescription>
        </Alert>
      )}

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">取引グループ数</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
              <div className="text-2xl font-bold">{transactionGroups.length}</div>
            <p className="text-xs text-muted-foreground">
                予約関連・その他取引
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">総取引数</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
                {transactionGroups.reduce((sum, group) => sum + group.transactions.length, 0)}
            </div>
            <p className="text-xs text-muted-foreground">
                全ポイント取引
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">予約関連</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
                {transactionGroups.filter(g => g.reservation_id !== null).length}
            </div>
            <p className="text-xs text-muted-foreground">
                予約に関連する取引
            </p>
          </CardContent>
        </Card>
      </div>

        {transactionGroups.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <Clock className="h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-lg font-semibold text-gray-600 mb-2">
                取引データはありません
            </h3>
            <p className="text-gray-500 text-center">
                現在、ポイント取引データはありません。
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-6">
            {transactionGroups.map((group, index) => (
              <Card key={group.reservation_id || `no_reservation_${index}`} className="hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-center space-x-3">
                    <Badge variant="outline" className="bg-blue-100 text-blue-800">
                        {group.reservation_id ? `予約ID: ${group.reservation_id}` : '予約外取引'}
                    </Badge>
                    <span className="text-sm text-gray-500">
                        {formatDate(group.created_at)}
                    </span>
                  </div>
                    <div className="text-right">
                      <div className="text-sm text-gray-600">取引数</div>
                      <div className="text-lg font-bold text-blue-600">{group.transactions.length}件</div>
                    </div>
                </div>

                  {/* Group Information */}
                  {group.reservation_id ? (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                  <div>
                        <h4 className="font-semibold text-gray-900 mb-3">予約情報</h4>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">
                            <strong>予約タイプ:</strong> {group.reservation?.type || 'N/A'}
                          </p>
                          <p className="text-sm text-gray-600">
                            <strong>予約時間:</strong> {group.reservation?.duration ? `${group.reservation.duration * 60}分` : 'N/A'}
                      </p>
                      <p className="text-sm text-gray-600">
                            <strong>予約日時:</strong> {group.reservation?.scheduled_at ? formatDate(group.reservation.scheduled_at) : 'N/A'}
                      </p>
                      <p className="text-sm text-gray-600">
                            <strong>開始時間:</strong> {group.reservation?.started_at ? formatDate(group.reservation.started_at) : 'N/A'}
                      </p>
                      <p className="text-sm text-gray-600">
                            <strong>終了時間:</strong> {group.reservation?.ended_at ? formatDate(group.reservation.ended_at) : 'N/A'}
                      </p>
                    </div>
                  </div>

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">ユーザー情報</h4>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">
                            <strong>ゲスト:</strong> {group.guest?.nickname || 'N/A'} ({group.guest?.phone || 'N/A'})
                          </p>
                          {group.casts.length > 0 && (
                            <div>
                              <p className="text-sm text-gray-600 font-medium mb-1">参加キャスト:</p>
                              <div className="space-y-1">
                                {group.casts.map((cast, index) => (
                                  <p key={cast.id} className="text-sm text-gray-600 ml-2">
                                    • {cast.nickname} ({cast.grade})
                                  </p>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  ) : (
                    <div className="mb-6">
                      <h4 className="font-semibold text-gray-900 mb-3">予約外取引</h4>
                      <p className="text-sm text-gray-600">
                        この取引グループは予約に関連していません。
                      </p>
                    </div>
                  )}

                  {/* Cast Financial Summary */}
                  {group.reservation_id && group.cast_financial_summary.length > 0 && (
                    <div className="mt-6">
                      <h4 className="font-semibold text-gray-900 mb-3">キャスト別財務サマリー</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {group.cast_financial_summary.map((castSummary) => (
                          <div key={castSummary.cast_id} className="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <div className="flex items-center justify-between mb-3">
                              <div>
                                <h5 className="font-medium text-gray-900">{castSummary.nickname}</h5>
                                <p className="text-sm text-gray-600">{castSummary.grade}</p>
                  </div>
                              <Badge variant="outline" className="bg-blue-100 text-blue-800">
                                キャスト
                              </Badge>
                </div>

                            <div className="space-y-2">
                              <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-600">総獲得ポイント:</span>
                                <span className="font-semibold text-green-600">
                                  {castSummary.total_points.toLocaleString()}P
                                </span>
                    </div>

                              <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-600">超過ポイント:</span>
                                <span className="font-semibold text-orange-600">
                                  {castSummary.exceeded_points.toLocaleString()}P
                                </span>
                </div>

                              {castSummary.payment_count > 0 && (
                                <div className="flex justify-between items-center">
                                  <span className="text-sm text-gray-600">支払い回数:</span>
                                  <span className="text-sm text-gray-500">
                                    {castSummary.payment_count}回
                          </span>
                      </div>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                  </div>
                )}

                {/* Transaction Details */}
                  <div className="bg-gray-50 rounded-lg p-4">
                    <h4 className="font-semibold text-gray-900 mb-3">取引詳細</h4>
                  <div className="space-y-2">
                      {group.transactions.map((transaction, txIndex) => (
                        <div key={transaction.id} className="flex justify-between items-center py-3 px-4 bg-white rounded border">
                        <div className="flex items-center space-x-3">
                          <Badge variant="outline" className={`${getTypeBadgeColor(transaction.type)}`}>
                            {getTypeLabel(transaction.type)}
                          </Badge>
                            <div>
                              <p className="text-sm text-gray-600">{transaction.description || '取引'}</p>
                              <p className="text-xs text-gray-500">
                                {transaction.guest?.nickname && `ゲスト: ${transaction.guest.nickname}`}
                                {transaction.cast?.nickname && `キャスト: ${transaction.cast.nickname}`}
                                {transaction.payment_id && `支払いID: ${transaction.payment_id}`}
                              </p>
                            </div>
                        </div>
                          <div className="flex items-center space-x-3">
                            <div className="text-right">
                              <span className={`text-sm font-medium ${transaction.amount > 0 ? 'text-green-600' : 'text-red-600'}`}>
                          {transaction.amount > 0 ? '+' : ''}{transaction.amount.toLocaleString()}P
                        </span>
                              {transaction.type === 'buy' && transaction.amount > 0 && (
                                <p className="text-xs text-gray-600 mt-1">
                                  {Math.max(100, Math.round(transaction.amount * 1.2 * 1.1)).toLocaleString()}円
                                </p>
                              )}
                              <p className="text-xs text-gray-500">
                                {formatDate(transaction.created_at)}
                              </p>
                            </div>

                            {/* Payment status and cancel button */}
                            {transaction.payment && (
                              <div className="flex items-center space-x-2">
                                {/* Payment status badge */}
                                <Badge
                                  variant="outline"
                                  className={
                                    transaction.payment.status === 'pending'
                                      ? 'bg-yellow-100 text-yellow-800 border-yellow-300'
                                      : transaction.payment.status === 'paid'
                                      ? 'bg-green-100 text-green-800 border-green-300'
                                      : transaction.payment.status === 'failed'
                                      ? 'bg-red-100 text-red-800 border-red-300'
                                      : transaction.payment.status === 'refunded'
                                      ? 'bg-gray-100 text-gray-800 border-gray-300'
                                      : 'bg-gray-100 text-gray-800 border-gray-300'
                                  }
                                >
                                  {transaction.payment.status === 'pending' && '保留中'}
                                  {transaction.payment.status === 'paid' && '支払い済み'}
                                  {transaction.payment.status === 'failed' && '失敗'}
                                  {transaction.payment.status === 'refunded' && 'キャンセル済み'}
                                </Badge>

                                {/* Cancel button for pending payments */}
                                {transaction.payment.status === 'pending' &&
                                 transaction.payment.stripe_payment_intent_id && (
                                  <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => {
                                      if (confirm('この支払いをキャンセルしますか？ポイントが返金されます。')) {
                                        cancelPayment(transaction.payment!.id);
                                      }
                                    }}
                                    disabled={cancellingPayments.has(transaction.payment!.id)}
                                    className="text-xs"
                                  >
                                    {cancellingPayments.has(transaction.payment!.id) ? (
                                      <>
                                        <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                                        キャンセル中...
                                      </>
                                    ) : (
                                      'キャンセル'
                                    )}
                                  </Button>
                                )}
                              </div>
                            )}
                          </div>
                      </div>
                    ))}
                    </div>
                  </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      </div>
    </AppLayout>
  );
};

export default PointTransactionPage;
