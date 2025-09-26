import React, { useState, useEffect } from 'react';
import { Clock, User, Calendar, DollarSign, AlertTriangle } from 'lucide-react';

interface ExceededPendingTransaction {
    id: number;
    guest_id: number;
    cast_id: number;
    reservation_id: number;
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
        phone: string;
    };
    reservation: {
        id: number;
        location: string;
        duration: number;
        scheduled_at: string;
    };
}

const ExceededPendingPage: React.FC = () => {
    const [transactions, setTransactions] = useState<ExceededPendingTransaction[]>([]);
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        fetchTransactions();
    }, []);

    const fetchTransactions = async () => {
        try {
            setLoading(true);
            const response = await fetch('/api/admin/exceeded-pending');
            const data = await response.json();
            setTransactions(data.transactions || []);
        } catch (error) {
            console.error('Failed to fetch exceeded pending transactions:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleProcessAll = async () => {
        try {
            setProcessing(true);
            const response = await fetch('/api/admin/exceeded-pending/process-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            
            if (response.ok) {
                alert(`Successfully processed ${data.processed_count} transactions`);
                fetchTransactions(); // Refresh the list
            } else {
                alert('Failed to process transactions');
            }
        } catch (error) {
            console.error('Failed to process transactions:', error);
            alert('Failed to process transactions');
        } finally {
            setProcessing(false);
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString('ja-JP');
    };

    const getDaysPending = (createdAt: string) => {
        const created = new Date(createdAt);
        const now = new Date();
        const diffTime = Math.abs(now.getTime() - created.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-gray-900"></div>
            </div>
        );
    }

    return (
        <div className="p-6">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Exceeded Pending Transactions</h1>
                <button
                    onClick={handleProcessAll}
                    disabled={processing || transactions.length === 0}
                    className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg flex items-center gap-2"
                >
                    {processing ? (
                        <>
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                            Processing...
                        </>
                    ) : (
                        <>
                            <AlertTriangle className="h-4 w-4" />
                            Process All ({transactions.length})
                        </>
                    )}
                </button>
            </div>

            {transactions.length === 0 ? (
                <div className="text-center py-12">
                    <AlertTriangle className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                    <p className="text-gray-500 text-lg">No exceeded pending transactions found</p>
                </div>
            ) : (
                <div className="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul className="divide-y divide-gray-200">
                        {transactions.map((transaction) => (
                            <li key={transaction.id} className="px-6 py-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-4 mb-2">
                                            <div className="flex items-center gap-2">
                                                <DollarSign className="h-4 w-4 text-green-600" />
                                                <span className="text-lg font-semibold text-gray-900">
                                                    {transaction.amount.toLocaleString()} P
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-gray-400" />
                                                <span className="text-sm text-gray-500">
                                                    {getDaysPending(transaction.created_at)} days pending
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                            <div>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <User className="h-4 w-4 text-blue-600" />
                                                    <span className="font-medium text-gray-700">Guest:</span>
                                                </div>
                                                <p className="text-gray-600">
                                                    {transaction.guest.nickname} ({transaction.guest.phone})
                                                </p>
                                            </div>
                                            
                                            <div>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <User className="h-4 w-4 text-purple-600" />
                                                    <span className="font-medium text-gray-700">Cast:</span>
                                                </div>
                                                <p className="text-gray-600">
                                                    {transaction.cast.nickname} ({transaction.cast.phone})
                                                </p>
                                            </div>
                                            
                                            <div>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <Clock className="h-4 w-4 text-orange-600" />
                                                    <span className="font-medium text-gray-700">Reservation:</span>
                                                </div>
                                                <p className="text-gray-600">
                                                    #{transaction.reservation.id} - {transaction.reservation.location}
                                                </p>
                                                <p className="text-gray-500 text-xs">
                                                    {transaction.reservation.duration}h • {formatDate(transaction.reservation.scheduled_at)}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div className="mt-2">
                                            <p className="text-sm text-gray-500">
                                                <span className="font-medium">Description:</span> {transaction.description}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                Created: {formatDate(transaction.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div className="ml-4">
                                        {getDaysPending(transaction.created_at) >= 2 ? (
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Ready for Transfer
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Waiting ({2 - getDaysPending(transaction.created_at)} days left)
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
};

export default ExceededPendingPage;

