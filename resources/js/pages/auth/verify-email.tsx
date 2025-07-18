// Components
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <AuthLayout title="メールアドレスの確認" description="ご登録のメールアドレス宛に確認メールを送信しました。メール内のリンクをクリックして認証を完了してください。">
            <Head title="メールアドレスの確認" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    新しい確認メールを送信しました。
                </div>
            )}

            <form onSubmit={submit} className="space-y-6 text-center">
                <Button disabled={processing} variant="secondary">
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    確認メールを再送信
                </Button>

                <TextLink href={route('logout')} method="post" className="mx-auto block text-sm">
                    ログアウト
                </TextLink>
            </form>
        </AuthLayout>
    );
}
