import React, { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Clock, Users, DollarSign, RefreshCw } from 'lucide-react';

interface PointTransaction {
  id: number;
  type: string;
  amount: number;
  description: string;
  created_at: string;
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
  reservation: {
    id: number;
    type: string;
    scheduled_at: string;
  } | null;
}

const PointTransactionPage: React.FC = () => {
  const [transactions, setTransactions] = useState<PointTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    fetchTransactions();
  }, []);

  const fetchTransactions = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch('/api/admin/exceeded-pending', {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });
      
      console.log('API Response Status:', response.status);
      const data = await response.json();
      console.log('API Response Data:', data);
      
      if (data.success) {
        setTransactions(data.data);
      } else {
        setError(data.message || 'ポイント取引データの取得に失敗しました');
      }
    } catch (err) {
      setError('ネットワークエラーが発生しました');
      console.error('Error fetching transactions:', err);
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
        await fetchTransactions(); // Refresh the list
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
            onClick={fetchTransactions}
            variant="outline"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            更新
          </Button>
          <Button
            onClick={processAllTransactions}
            disabled={processing || transactions.length === 0}
            className="bg-blue-600 hover:bg-blue-700"
          >
            {processing ? (
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
            ) : (
              <DollarSign className="h-4 w-4 mr-2" />
            )}
            一括処理 ({transactions.length})
          </Button>
        </div>
      </div>

      {error && (
        <Alert className="border-red-200 bg-red-50">
          <AlertDescription className="text-red-800">
            {error}
          </AlertDescription>
        </Alert>
      )}

      {success && (
        <Alert className="border-green-200 bg-green-50">
          <AlertDescription className="text-green-800">
            {success}
          </AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">取引総数</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{transactions.length}</div>
            <p className="text-xs text-muted-foreground">
              ポイント取引
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">総金額</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {formatCurrency(transactions.reduce((sum, t) => sum + t.amount, 0))}
            </div>
            <p className="text-xs text-muted-foreground">
              総取引金額
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">取引タイプ</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {new Set(transactions.map(t => t.type)).size}
            </div>
            <p className="text-xs text-muted-foreground">
              異なる取引タイプ
            </p>
          </CardContent>
        </Card>
      </div>

      {transactions.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <Clock className="h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-lg font-semibold text-gray-600 mb-2">
              ポイント取引はありません
            </h3>
            <p className="text-gray-500 text-center">
              現在、ポイント取引データはありません。
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {transactions.map((transaction) => (
            <Card key={transaction.id} className="hover:shadow-md transition-shadow">
              <CardContent className="p-6">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center space-x-3 mb-3">
                      <Badge variant="outline" className={`${getTypeBadgeColor(transaction.type)}`}>
                        {getTypeLabel(transaction.type)}
                      </Badge>
                      <span className="text-sm text-gray-500">
                        ID: {transaction.id}
                      </span>
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-2">ゲスト情報</h4>
                        <p className="text-sm text-gray-600">
                          <strong>名前:</strong> {transaction.guest?.nickname || 'N/A'}
                        </p>
                        <p className="text-sm text-gray-600">
                          <strong>電話:</strong> {transaction.guest?.phone || 'N/A'}
                        </p>
                      </div>
                      
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-2">キャスト情報</h4>
                        <p className="text-sm text-gray-600">
                          <strong>名前:</strong> {transaction.cast?.nickname || 'N/A'}
                        </p>
                        <p className="text-sm text-gray-600">
                          <strong>グレード:</strong> {transaction.cast?.grade || 'N/A'}
                        </p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-1">金額</h4>
                        <p className="text-lg font-bold text-orange-600">
                          {formatCurrency(transaction.amount)}
                        </p>
                      </div>
                      
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-1">作成日時</h4>
                        <p className="text-sm text-gray-600">
                          {formatDate(transaction.created_at)}
                        </p>
                      </div>
                      
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-1">取引タイプ</h4>
                        <p className="text-sm text-gray-600">
                          {getTypeLabel(transaction.type)}
                        </p>
                      </div>
                    </div>
                    
                    {transaction.description && (
                      <div className="mt-4">
                        <h4 className="font-semibold text-gray-900 mb-1">説明</h4>
                        <p className="text-sm text-gray-600">{transaction.description}</p>
                      </div>
                    )}
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