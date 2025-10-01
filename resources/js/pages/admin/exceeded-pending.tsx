import React, { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Clock, Users, DollarSign, RefreshCw, AlertTriangle } from 'lucide-react';

interface ReservationSummary {
  reservation_id: number;
  reservation: {
    id: number;
    type: string;
    duration: number;
    scheduled_at: string;
    started_at: string | null;
    ended_at: string | null;
    points_earned: number | null;
  } | null;
  guest: {
    id: number;
    nickname: string;
    phone: string;
  } | null;
  cast: {
    id: number;
    nickname: string;
    grade: string;
  } | null;
  reserved_points: number;
  actual_used_points: number;
  exceeded_points: number;
  bought_points: number;
  payment_amount_yen: number;
  payment_status: string | null;
  payment_id: number | null;
  stripe_payment_intent_id: string | null;
  transactions: any[];
  created_at: string;
  updated_at: string;
}

const PointTransactionPage: React.FC = () => {
  const [reservationSummaries, setReservationSummaries] = useState<ReservationSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [showCancelModal, setShowCancelModal] = useState(false);
  const [paymentToCancel, setPaymentToCancel] = useState<{id: number, amount: number, castName: string} | null>(null);

  useEffect(() => {
    fetchReservationSummaries();
  }, []);

  const fetchReservationSummaries = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch('/api/admin/exceeded-pending/grouped', {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });

      console.log('API Response Status:', response.status);
      const data = await response.json();
      console.log('API Response Data:', data);

      if (data.success) {
        setReservationSummaries(data.data);
      } else {
        setError(data.message || '予約データの取得に失敗しました');
      }
    } catch (err) {
      setError('ネットワークエラーが発生しました');
      console.error('Error fetching reservation summaries:', err);
    } finally {
      setLoading(false);
    }
  };

  const processAllTransactions = async () => {
    try {
      setProcessing(true);
      setError(null);
      setSuccess(null);

      const response = await fetch('/api/admin/exceeded-pending/process-all', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      const data = await response.json();

      if (data.success) {
        setSuccess(`${data.processed_count}件の取引を正常に処理しました`);
        await fetchReservationSummaries(); // Refresh the list
      } else {
        setError(data.message || '取引の処理に失敗しました');
      }
    } catch (err) {
      setError('ネットワークエラーが発生しました');
      console.error('Error processing transactions:', err);
    } finally {
      setProcessing(false);
    }
  };

  const showCancelConfirmation = (paymentId: number, amount: number, castName: string) => {
    setPaymentToCancel({ id: paymentId, amount, castName });
    setShowCancelModal(true);
  };

  const confirmCancelPayment = async () => {
    if (!paymentToCancel) return;

    try {
      setProcessing(true);
      setError(null);
      setSuccess(null);
      setShowCancelModal(false);

      const response = await fetch('/api/admin/exceeded-pending/cancel-payment', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ payment_id: paymentToCancel.id }),
      });

      const data = await response.json();

      if (data.success) {
        setSuccess(`✅ 支払いをキャンセルしました！\n${data.refunded_points}ポイントを返金しました。`);
        await fetchReservationSummaries(); // Refresh the list

        // Auto-hide success message after 5 seconds
        setTimeout(() => {
          setSuccess(null);
        }, 5000);
      } else {
        setError(`❌ 支払いのキャンセルに失敗しました\n${data.message || 'エラーが発生しました'}`);
      }
    } catch (err) {
      setError('ネットワークエラーが発生しました');
      console.error('Error cancelling payment:', err);
    } finally {
      setProcessing(false);
      setPaymentToCancel(null);
    }
  };

  const cancelCancelPayment = () => {
    setShowCancelModal(false);
    setPaymentToCancel(null);
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', {
      style: 'currency',
      currency: 'JPY',
    }).format(amount);
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

  const getTimeUntilAutoTransfer = (createdAt: string) => {
    const created = new Date(createdAt);
    const twoDaysLater = new Date(created.getTime() + 2 * 24 * 60 * 60 * 1000);
    const now = new Date();

    if (now >= twoDaysLater) {
      return '自動転送準備完了';
    }

    const diffMs = twoDaysLater.getTime() - now.getTime();
    const diffHours = Math.ceil(diffMs / (1000 * 60 * 60));

    if (diffHours > 24) {
      const days = Math.floor(diffHours / 24);
      const hours = diffHours % 24;
      return `${days}日${hours}時間後`;
    }

    return `${diffHours}時間後`;
  };

  const getTypeLabel = (type: string) => {
    const typeLabels: { [key: string]: string } = {
      'buy': '購入',
      'transfer': '転送',
      'convert': '変換',
      'gift': 'ギフト',
      'exceeded_pending': '超過時間保留'
    };
    return typeLabels[type] || type;
  };

  const getTypeBadgeColor = (type: string) => {
    const colorMap: { [key: string]: string } = {
      'buy': 'bg-blue-100 text-blue-800',
      'transfer': 'bg-green-100 text-green-800',
      'convert': 'bg-purple-100 text-purple-800',
      'gift': 'bg-pink-100 text-pink-800',
      'exceeded_pending': 'bg-orange-100 text-orange-800'
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
            すべてのポイント取引を管理します（保留中を除く）
          </p>
        </div>
        <div className="flex space-x-2">
          <Button
            onClick={fetchReservationSummaries}
            variant="outline"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            更新
          </Button>
          <Button
            onClick={processAllTransactions}
            disabled={processing || reservationSummaries.length === 0}
            className="bg-blue-600 hover:bg-blue-700"
          >
            {processing ? (
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
            ) : (
              <DollarSign className="h-4 w-4 mr-2" />
            )}
            一括処理 ({reservationSummaries.length})
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

      {success && (
        <Alert className="border-green-200 bg-green-50">
          <AlertDescription className="text-green-800 whitespace-pre-line">
            {success}
          </AlertDescription>
        </Alert>
      )}

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">予約総数</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{reservationSummaries.length}</div>
            <p className="text-xs text-muted-foreground">
              完了した予約
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">総支払い金額</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {formatCurrency(reservationSummaries.reduce((sum, r) => sum + r.payment_amount_yen, 0))}
            </div>
            <p className="text-xs text-muted-foreground">
              クレジットカード支払い
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">超過予約数</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {reservationSummaries.filter(r => r.exceeded_points > 0).length}
            </div>
            <p className="text-xs text-muted-foreground">
              時間超過した予約
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">保留中支払い</CardTitle>
            <RefreshCw className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {reservationSummaries.filter(r => r.payment_status === 'pending').length}
            </div>
            <p className="text-xs text-muted-foreground">
              2日後自動引き落とし
            </p>
          </CardContent>
        </Card>
      </div>

      {reservationSummaries.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <Clock className="h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-lg font-semibold text-gray-600 mb-2">
              予約データはありません
            </h3>
            <p className="text-gray-500 text-center">
              現在、完了した予約データはありません。
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-6">
          {reservationSummaries.map((summary) => (
            <Card key={summary.reservation_id} className="hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-center space-x-3">
                    <Badge variant="outline" className="bg-blue-100 text-blue-800">
                      予約ID: {summary.reservation_id}
                    </Badge>
                    <span className="text-sm text-gray-500">
                      {formatDate(summary.created_at)}
                    </span>
                  </div>
                </div>

                {/* Reservation Details */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">予約詳細</h4>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">
                        <strong>予約時間:</strong> {summary.reservation?.duration ? `${summary.reservation.duration * 60}分` : 'N/A'}
                      </p>
                      <p className="text-sm text-gray-600">
                        <strong>予約日時:</strong> {summary.reservation?.scheduled_at ? formatDate(summary.reservation.scheduled_at) : 'N/A'}
                      </p>
                      <p className="text-sm text-gray-600">
                        <strong>開始時間:</strong> {summary.reservation?.started_at ? formatDate(summary.reservation.started_at) : 'N/A'}
                      </p>
                      <p className="text-sm text-gray-600">
                        <strong>終了時間:</strong> {summary.reservation?.ended_at ? formatDate(summary.reservation.ended_at) : 'N/A'}
                      </p>
                    </div>
                  </div>

                  <div>
                    <h4 className="font-semibold text-gray-900 mb-3">ユーザー情報</h4>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">
                        <strong>ゲスト:</strong> {summary.guest?.nickname || 'N/A'} ({summary.guest?.phone || 'N/A'})
                      </p>
                      <p className="text-sm text-gray-600">
                        <strong>キャスト:</strong> {summary.cast?.nickname || 'N/A'} ({summary.cast?.grade || 'N/A'})
                      </p>
                    </div>
                  </div>
                </div>

                {/* Point Summary */}
                <div className="bg-gray-50 rounded-lg p-4 mb-4">
                  <h4 className="font-semibold text-gray-900 mb-3">ポイント使用状況</h4>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">予約時支払い</p>
                      <p className="text-lg font-bold text-blue-600">{summary.reserved_points.toLocaleString()}P</p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">実際の使用</p>
                      <p className="text-lg font-bold text-green-600">{summary.actual_used_points.toLocaleString()}P</p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">超過分</p>
                      <p className="text-lg font-bold text-orange-600">{summary.exceeded_points.toLocaleString()}P</p>
                    </div>
                    <div className="text-center">
                      <p className="text-sm text-gray-600 mb-1">購入分</p>
                      <p className="text-lg font-bold text-purple-600">{summary.bought_points.toLocaleString()}P</p>
                    </div>
                  </div>
                </div>

                {/* Payment Information */}
                {summary.payment_amount_yen > 0 && (
                  <div className="bg-yellow-50 rounded-lg p-4 mb-4">
                    <h4 className="font-semibold text-gray-900 mb-3">支払い情報</h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <p className="text-sm text-gray-600">
                          <strong>支払い金額:</strong>
                          <span className="ml-2 text-lg font-bold text-red-600">
                            {formatCurrency(summary.payment_amount_yen)}
                          </span>
                        </p>
                        <p className="text-sm text-gray-600">
                          <strong>ステータス:</strong>
                          <Badge
                            variant={summary.payment_status === 'pending' ? 'destructive' :
                                   summary.payment_status === 'failed' ? 'destructive' : 'secondary'}
                            className="ml-2"
                          >
                            {summary.payment_status === 'pending' ? '保留中' :
                             summary.payment_status === 'paid' ? '支払い済み' :
                             summary.payment_status === 'refunded' ? '返金済み' :
                             summary.payment_status === 'failed' ? '失敗' :
                             summary.payment_status}
                          </Badge>
                        </p>
                      </div>
                      <div>
                        <p className="text-sm text-gray-600">
                          <strong>Stripe Payment Intent:</strong>
                          {summary.stripe_payment_intent_id ? (
                            <span className="ml-2 font-mono text-xs bg-gray-200 px-2 py-1 rounded">
                              {summary.stripe_payment_intent_id}
                            </span>
                          ) : (
                            <span className="ml-2 text-gray-400">なし</span>
                          )}
                        </p>
                        <p className="text-sm text-gray-600">
                          <strong>支払いID:</strong> {summary.payment_id}
                        </p>
                      </div>
                    </div>

                    {/* Cancel Button for Pending Payments */}
                    {summary.payment_status === 'pending' && summary.stripe_payment_intent_id && (
                      <div className="mt-4 flex justify-end">
                        <Button
                          onClick={() => showCancelConfirmation(summary.payment_id!, summary.payment_amount_yen, summary.cast?.nickname || 'Unknown')}
                          disabled={processing}
                          variant="destructive"
                          size="sm"
                        >
                          {processing ? (
                            <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                          ) : (
                            <RefreshCw className="h-4 w-4 mr-2" />
                          )}
                          支払いをキャンセル
                        </Button>
                      </div>
                    )}
                  </div>
                )}

                {/* Transaction Details */}
                {/* <div className="mt-4">
                  <h4 className="font-semibold text-gray-900 mb-2">取引詳細</h4>
                  <div className="space-y-2">
                    {summary.transactions.map((transaction, index) => (
                      <div key={index} className="flex justify-between items-center py-2 px-3 bg-gray-50 rounded">
                        <div className="flex items-center space-x-3">
                          <Badge variant="outline" className={`${getTypeBadgeColor(transaction.type)}`}>
                            {getTypeLabel(transaction.type)}
                          </Badge>
                          <span className="text-sm text-gray-600">{transaction.description}</span>
                        </div>
                        <span className="text-sm font-medium text-gray-900">
                          {transaction.amount > 0 ? '+' : ''}{transaction.amount.toLocaleString()}P
                        </span>
                      </div>
                    ))}
                  </div>
                </div> */}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Cancel Payment Confirmation Modal */}
      {showCancelModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div className="flex items-center mb-4">
              <AlertTriangle className="h-6 w-6 text-red-500 mr-3" />
              <h3 className="text-lg font-semibold text-gray-900">支払いキャンセル確認</h3>
            </div>

            <div className="mb-6">
              <p className="text-gray-600 mb-2">
                以下の支払いをキャンセルしますか？
              </p>
              <div className="bg-gray-50 rounded-lg p-4">
                <p className="text-sm text-gray-600">
                  <strong>キャスト:</strong> {paymentToCancel?.castName}
                </p>
                <p className="text-sm text-gray-600">
                  <strong>支払い金額:</strong> {formatCurrency(paymentToCancel?.amount || 0)}
                </p>
                <p className="text-sm text-gray-600">
                  <strong>返金ポイント:</strong> {Math.floor((paymentToCancel?.amount || 0) / 1.2).toLocaleString()}P
                </p>
              </div>
              <p className="text-sm text-red-600 mt-2">
                ⚠️ この操作は取り消せません。キャンセル後、ポイントが自動的に返金されます。
              </p>
            </div>

            <div className="flex justify-end space-x-3">
              <Button
                onClick={cancelCancelPayment}
                variant="outline"
                disabled={processing}
              >
                キャンセル
              </Button>
              <Button
                onClick={confirmCancelPayment}
                variant="destructive"
                disabled={processing}
              >
                {processing ? (
                  <>
                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                    処理中...
                  </>
                ) : (
                  '支払いをキャンセル'
                )}
              </Button>
            </div>
          </div>
        </div>
      )}
      </div>
    </AppLayout>
  );
};

export default PointTransactionPage;
