import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Save } from 'lucide-react';
import { useState, useEffect } from 'react';

// Common emojis for gifts
const commonEmojis = [
    '🎁', '💝', '💖', '💕', '💗', '💓', '💘', '💞', '💟', '💌',
    '🌹', '🌷', '🌺', '🌸', '🌼', '🌻', '🌞', '🌝', '🌛', '🌜',
    '🔥', '💥', '⚡', '💫', '✨', '💢', '💦', '💨', '💧', '💤',
    '🎊', '🎉', '🎈', '🎂', '🎄', '🎃', '🎗️', '🎟️', '🎫',
    '💎', '👑', '🏆', '🥇', '🥈', '🥉', '⭐', '🌟', '🎖️', '🏅',
    '🎯', '🎪', '🎨', '🎭', '🎬', '🎤', '🎧', '🎵', '🎶', '🎹',
    '🎸', '🎺', '🎻', '🥁', '🎷', '🎼', '🎹', '🎸', '🎺', '🎻',
    '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸',
    '🏊', '🏄', '🚴', '🏇', '🏂', '⛷️', '🏋️', '🤸', '🤺', '🤾',
    '🎮', '🎲', '♟️', '🎯', '🎳', '🎰', '🎪', '🎨', '🎭', '🎬'
];

interface Gift {
    id: number;
    name: string;
    category: string;
    points: number;
    icon: string | null;
    description: string | null;
    created_at: string;
}

interface Props {
    gift: Gift;
    categories: Record<string, string>;
}

export default function EditGift({ gift, categories }: Props) {
    const [formData, setFormData] = useState({
        name: gift.name,
        category: gift.category,
        points: gift.points.toString(),
        icon: gift.icon || '',
        description: gift.description || ''
    });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.put(`/admin/gifts/${gift.id}`, formData, {
            onError: (errors) => {
                setErrors(errors);
            },
            onSuccess: () => {
                // Success will redirect
            }
        });
    };

    const handleInputChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors(prev => ({ ...prev, [field]: '' }));
        }
    };

    const selectEmoji = (emoji: string) => {
        setFormData(prev => ({ ...prev, icon: emoji }));
    };

    return (
        <AppLayout>
            <Head title="ギフト編集" />

            <div className="container mx-auto py-6">
                <div className="flex items-center mb-6">
                    <Link href="/admin/gifts" className="mr-4">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-3xl font-bold">ギフト編集</h1>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>ギフトを編集</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="name">ギフト名 *</Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    placeholder="ギフト名を入力してください"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="category">カテゴリ *</Label>
                                <Select
                                    value={formData.category}
                                    onValueChange={(value) => handleInputChange('category', value)}
                                >
                                    <SelectTrigger className={errors.category ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="カテゴリを選択してください" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(categories).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category && (
                                    <p className="text-sm text-red-500">{errors.category}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="points">ポイント *</Label>
                                <Input
                                    id="points"
                                    type="number"
                                    min="0"
                                    value={formData.points}
                                    onChange={(e) => handleInputChange('points', e.target.value)}
                                    placeholder="ポイントを入力してください"
                                    className={errors.points ? 'border-red-500' : ''}
                                />
                                {errors.points && (
                                    <p className="text-sm text-red-500">{errors.points}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>アイコン (絵文字)</Label>
                                <div className="flex items-center space-x-4">
                                    <div className="text-3xl border rounded-lg p-2 min-w-[60px] text-center">
                                        {formData.icon || '🎁'}
                                    </div>
                                    <Input
                                        value={formData.icon}
                                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => handleInputChange('icon', e.target.value)}
                                        placeholder="絵文字を入力または選択"
                                        className="flex-1"
                                    />
                                </div>
                                <p className="text-sm text-gray-500">
                                    絵文字を直接入力するか、下から選択してください
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label>絵文字選択</Label>
                                <div className="grid grid-cols-10 gap-2 p-4 border rounded-lg max-h-48 overflow-y-auto">
                                    {commonEmojis.map((emoji, index) => (
                                        <button
                                            key={index}
                                            type="button"
                                            onClick={() => selectEmoji(emoji)}
                                            className="text-2xl hover:bg-gray-100 rounded p-1 transition-colors"
                                        >
                                            {emoji}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">説明</Label>
                                <textarea
                                    id="description"
                                    value={formData.description}
                                    onChange={e => handleInputChange('description', e.target.value)}
                                    placeholder="ギフトの説明を入力してください"
                                    className={`w-full border rounded p-2 ${errors.description ? 'border-red-500' : ''}`}
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">{errors.description}</p>
                                )}
                            </div>

                            <div className="flex justify-end space-x-4">
                                <Link href="/admin/gifts">
                                    <Button variant="outline" type="button">
                                        キャンセル
                                    </Button>
                                </Link>
                                <Button type="submit">
                                    <Save className="w-4 h-4 mr-2" />
                                    更新
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
