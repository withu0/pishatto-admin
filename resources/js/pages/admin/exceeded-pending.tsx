import React, { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Clock, Users, DollarSign, RefreshCw } from 'lucide-react';

interface ExceededPendingTransaction {
  id: number;
  amount: number;
  description: string;
  created_at: string;
  guest: {
    id: number;
    nickname: string;
    phone: string;
  };
  cast: {
    id: number;
    nickname: string;
    grade: string;
  };
  reservation: {
    id: number;
    type: string;
    scheduled_at: string;
  };
}

const ExceededPendingPage: React.FC = () => {
  const [transactions, setTransactions] = useState<ExceededPendingTransaction[]>([]);
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
        setError(data.message || 'Failed to fetch transactions');
      }
    } catch (err) {
      setError('Network error occurred');
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
        setSuccess(`Successfully processed ${data.processed_count} transactions`);
        await fetchTransactions(); // Refresh the list
      } else {
        setError(data.message || 'Failed to process transactions');
      }
    } catch (err) {
      setError('Network error occurred');
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
      return 'Ready for auto-transfer';
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

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="h-8 w-8 animate-spin" />
        <span className="ml-2">Loading exceeded pending transactions...</span>
      </div>
    );
  }

  return (
    <AppLayout>
      <Head title="超過時間管理" />
      <div className="container mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Exceeded Pending Transactions</h1>
          <p className="text-gray-600 mt-2">
            Manage exceeded time charges that are pending transfer to casts
          </p>
        </div>
        <div className="flex space-x-2">
          <Button
            onClick={fetchTransactions}
            variant="outline"
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
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
            Process All ({transactions.length})
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
            <CardTitle className="text-sm font-medium">Total Pending</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{transactions.length}</div>
            <p className="text-xs text-muted-foreground">
              Exceeded time transactions
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Amount</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {formatCurrency(transactions.reduce((sum, t) => sum + t.amount, 0))}
            </div>
            <p className="text-xs text-muted-foreground">
              Pending transfer amount
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Auto-Transfer</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {transactions.filter(t => {
                const created = new Date(t.created_at);
                const twoDaysLater = new Date(created.getTime() + 2 * 24 * 60 * 60 * 1000);
                return new Date() >= twoDaysLater;
              }).length}
            </div>
            <p className="text-xs text-muted-foreground">
              Ready for auto-transfer
            </p>
          </CardContent>
        </Card>
      </div>

      {transactions.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-12">
            <Clock className="h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-lg font-semibold text-gray-600 mb-2">
              No Exceeded Pending Transactions
            </h3>
            <p className="text-gray-500 text-center">
              There are currently no exceeded time charges pending transfer.
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
                      <Badge variant="outline" className="bg-orange-100 text-orange-800">
                        Exceeded Pending
                      </Badge>
                      <span className="text-sm text-gray-500">
                        ID: {transaction.id}
                      </span>
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-2">Guest Information</h4>
                        <p className="text-sm text-gray-600">
                          <strong>Name:</strong> {transaction.guest?.nickname || 'N/A'}
                        </p>
                        <p className="text-sm text-gray-600">
                          <strong>Phone:</strong> {transaction.guest?.phone || 'N/A'}
                        </p>
                      </div>
                      
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-2">Cast Information</h4>
                        <p className="text-sm text-gray-600">
                          <strong>Name:</strong> {transaction.cast?.nickname || 'N/A'}
                        </p>
                        <p className="text-sm text-gray-600">
                          <strong>Grade:</strong> {transaction.cast?.grade || 'N/A'}
                        </p>
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-1">Amount</h4>
                        <p className="text-lg font-bold text-orange-600">
                          {formatCurrency(transaction.amount)}
                        </p>
                      </div>
                      
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-1">Created</h4>
                        <p className="text-sm text-gray-600">
                          {formatDate(transaction.created_at)}
                        </p>
                      </div>
                      
                      <div>
                        <h4 className="font-semibold text-gray-900 mb-1">Auto-Transfer</h4>
                        <p className="text-sm text-gray-600">
                          {getTimeUntilAutoTransfer(transaction.created_at)}
                        </p>
                      </div>
                    </div>
                    
                    {transaction.description && (
                      <div className="mt-4">
                        <h4 className="font-semibold text-gray-900 mb-1">Description</h4>
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

export default ExceededPendingPage;