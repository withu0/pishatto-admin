import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth, name } = usePage<SharedData>().props;

    return (
        <>
            <Head title="ようこそ" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a]">
                <div className="w-full max-w-md rounded-lg bg-white p-8 shadow-md dark:bg-[#161615] dark:text-[#EDEDEC]">
                    <div className="mb-6 flex flex-col items-center">
                        <span className="mb-2 text-2xl font-bold">{name || 'Pishatto Admin'}</span>
                        <span className="text-lg text-[#706f6c] dark:text-[#A1A09A]">管理者ページへようこそ</span>
                    </div>
                    <div className="flex justify-center gap-4 mt-6">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="inline-block rounded-sm border border-[#19140035] px-6 py-2 text-base font-medium text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                ダッシュボードへ
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="inline-block rounded-sm border border-transparent px-6 py-2 text-base font-medium text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                                >
                                    ログイン
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="inline-block rounded-sm border border-[#19140035] px-6 py-2 text-base font-medium text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                                >
                                    新規登録
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
