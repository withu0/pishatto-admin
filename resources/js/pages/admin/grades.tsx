import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Trophy, Users, TrendingUp, RefreshCw } from 'lucide-react';
import axios from 'axios';

interface Guest {
    id: number;
    nickname: string;
    grade: string;
    grade_points: number;
    grade_updated_at: string;
    points: number;
}

interface GradeStats {
    total_guests: number;
    grade_distribution: Record<string, number>;
    grade_info: {
        thresholds: Record<string, number>;
        names: Record<string, string>;
        benefits: Record<string, any>;
    };
}

interface PageProps {
    guests: {
        data: Guest[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    gradeStats: GradeStats;
}

const GradeManagement: React.FC<PageProps> = ({ guests, gradeStats }) => {
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [guestsData, setGuestsData] = useState(guests);
    const [stats, setStats] = useState(gradeStats);

    const gradeColors = {
        green: 'bg-green-500',
        orange: 'bg-orange-500',
        bronze: 'bg-amber-600',
        silver: 'bg-gray-400',
        gold: 'bg-yellow-500',
        platinum: 'bg-purple-500',
        centurion: 'bg-black',
    };

    const gradeIcons = {
        green: '🟢',
        orange: '🟠',
        bronze: '🥉',
        silver: '🥈',
        gold: '🥇',
        platinum: '💎',
        centurion: '👑',
    };

    const updateAllGrades = async () => {
        setLoading(true);
        try {
            const response = await axios.post('/admin/grades/update-all');
            setMessage({ type: 'success', text: response.data.message });
            // Refresh the page data
            window.location.reload();
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to update grades' });
        } finally {
            setLoading(false);
        }
    };

    const updateGuestGrade = async (guestId: number) => {
        try {
            const response = await axios.post('/admin/grades/update-guest', { guest_id: guestId });
            setMessage({ type: 'success', text: response.data.message });
            // Refresh the page data
            window.location.reload();
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to update guest grade' });
        }
    };

    const getGradePercentage = (grade: string) => {
        const total = stats.total_guests;
        const count = stats.grade_distribution[grade] || 0;
        return total > 0 ? (count / total) * 100 : 0;
    };

    return (
        <AppLayout>
            <Head title="Grade Management" />
            
            <div className="container mx-auto py-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-3xl font-bold">Grade Management</h1>
                    <Button onClick={updateAllGrades} disabled={loading}>
                        <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                        Update All Grades
                    </Button>
                </div>

                {message && (
                    <Alert className={`mb-6 ${message.type === 'success' ? 'border-green-500' : 'border-red-500'}`}>
                        <AlertDescription>{message.text}</AlertDescription>
                    </Alert>
                )}

                {/* Grade Statistics */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Guests</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_guests}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Highest Grade</CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {Object.keys(stats.grade_distribution).length > 0 
                                    ? stats.grade_names[Object.keys(stats.grade_distribution)[0]] 
                                    : 'N/A'}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Average Points</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {guestsData.data.length > 0 
                                    ? Math.round(guestsData.data.reduce((sum, guest) => sum + guest.grade_points, 0) / guestsData.data.length).toLocaleString()
                                    : '0'}P
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Grade Distribution</CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{Object.keys(stats.grade_distribution).length}</div>
                            <p className="text-xs text-muted-foreground">Different grades</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Grade Distribution Chart */}
                <Card className="mb-8">
                    <CardHeader>
                        <CardTitle>Grade Distribution</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {Object.entries(stats.grade_distribution).map(([grade, count]) => (
                                <div key={grade} className="flex items-center space-x-4">
                                    <div className="flex items-center space-x-2 w-24">
                                        <span className="text-2xl">{gradeIcons[grade as keyof typeof gradeIcons]}</span>
                                        <span className="font-medium">{stats.grade_names[grade]}</span>
                                    </div>
                                    <div className="flex-1">
                                        <Progress value={getGradePercentage(grade)} className="h-2" />
                                    </div>
                                    <div className="w-16 text-right">
                                        <Badge variant="secondary">{count}</Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Guests Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Guest Grades</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Guest</TableHead>
                                    <TableHead>Grade</TableHead>
                                    <TableHead>Grade Points</TableHead>
                                    <TableHead>Total Points</TableHead>
                                    <TableHead>Last Updated</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {guestsData.data.map((guest) => (
                                    <TableRow key={guest.id}>
                                        <TableCell className="font-medium">{guest.nickname}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center space-x-2">
                                                <span className="text-xl">{gradeIcons[guest.grade as keyof typeof gradeIcons]}</span>
                                                <Badge className={gradeColors[guest.grade as keyof typeof gradeColors]}>
                                                    {stats.grade_names[guest.grade]}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell>{guest.grade_points.toLocaleString()}P</TableCell>
                                        <TableCell>{guest.points.toLocaleString()}P</TableCell>
                                        <TableCell>
                                            {guest.grade_updated_at 
                                                ? new Date(guest.grade_updated_at).toLocaleDateString()
                                                : 'Never'
                                            }
                                        </TableCell>
                                        <TableCell>
                                            <Button 
                                                size="sm" 
                                                variant="outline"
                                                onClick={() => updateGuestGrade(guest.id)}
                                            >
                                                Update
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
};

export default GradeManagement; 