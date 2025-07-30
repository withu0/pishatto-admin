import AppLayout from '@/layouts/app-layout';
import { Head, router, Link } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';

// Common emojis for badges
const commonEmojis = [
    '🏆', '🥇', '🥈', '🥉', '⭐', '🌟', '💎', '👑', '🎖️', '🏅',
    '🎯', '🎪', '🎨', '🎭', '🎬', '🎤', '🎧', '🎵', '🎶', '🎹',
    '🎸', '🎺', '🎻', '🥁', '🎷', '🎼', '🎹', '🎸', '🎺', '🎻',
    '💖', '💕', '💗', '💓', '💝', '💘', '💞', '💟', '💌', '💋',
    '🌹', '🌷', '🌺', '🌸', '🌼', '🌻', '🌞', '🌝', '🌛', '🌜',
    '🔥', '💥', '⚡', '💫', '✨', '💢', '💦', '💨', '💧', '💤',
    '🎊', '🎉', '🎈', '🎂', '🎁', '🎄', '🎃', '🎗️', '🎟️', '🎫',
    '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸',
    '🏊', '🏄', '🚴', '🏇', '🏂', '⛷️', '🏋️', '🤸', '🤺', '🤾',
    '🎮', '🎲', '♟️', '🎯', '🎳', '🎰', '🎪', '🎨', '🎭', '🎬'
];

export default function CreateBadge() {
    const [formData, setFormData] = useState({
        name: '',
        icon: '',
        description: ''
    });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        router.post('/admin/badges', formData, {
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
            <Head title="バッジ作成" />

            <div className="container mx-auto py-6">
                <div className="flex items-center mb-6">
                    <Link href="/admin/badges" className="mr-4">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            戻る
                        </Button>
                    </Link>
                    <h1 className="text-3xl font-bold">バッジ作成</h1>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>新しいバッジを作成</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="name">バッジ名 *</Label>
                                <Input
                                    id="name"
                                    value={formData.name}
                                    onChange={(e) => handleInputChange('name', e.target.value)}
                                    placeholder="バッジ名を入力してください"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>アイコン (絵文字)</Label>
                                <div className="flex items-center space-x-4">
                                    <div className="text-3xl border rounded-lg p-2 min-w-[60px] text-center">
                                        {formData.icon || '🏆'}
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
                                <Textarea
                                    id="description"
                                    value={formData.description}
                                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => handleInputChange('description', e.target.value)}
                                    placeholder="バッジの説明を入力してください"
                                    rows={3}
                                    className={errors.description ? 'border-red-500' : ''}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">{errors.description}</p>
                                )}
                            </div>

                            <div className="flex justify-end space-x-4">
                                <Link href="/admin/badges">
                                    <Button variant="outline" type="button">
                                        キャンセル
                                    </Button>
                                </Link>
                                <Button type="submit">
                                    <Save className="w-4 h-4 mr-2" />
                                    作成
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
