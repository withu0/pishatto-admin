import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Users, UserCheck, UserPlus, MessageCircle, Gift, Trophy, FileText, Receipt, CreditCard, Megaphone, ListChecks, DollarSign, FileSignature, MessageSquareText, Award, MapPin, Shield, Headphones, Star, ClipboardList, Clock } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'ダッシュボード',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    { title: 'ゲスト一覧', href: '/admin/guests', icon: Users },
    { title: 'キャスト一覧', href: '/admin/casts', icon: UserCheck },
    { title: 'キャスト申請管理', href: '/admin/cast-applications', icon: ClipboardList },
    { title: '身分証明書認証', href: '/admin/identity-verifications', icon: Shield },
    { title: 'ロケーション管理', href: '/admin/locations', icon: MapPin },
    { title: 'バッジ管理', href: '/admin/badges', icon: Award },
    { title: 'グレード管理', href: '/admin/grades', icon: Star },
    { title: '予約応募管理', href: '/admin/reservation-applications', icon: ListChecks },
    { title: 'マッチング履歴管理', href: '/admin/matching-manage', icon: ListChecks },
    { title: 'メッセージ管理', href: '/admin/messages', icon: MessageCircle },
    { title: 'コンシェルジュ管理', href: '/admin/concierge', icon: Headphones },
    { title: 'ギフト管理', href: '/admin/gifts', icon: Gift },
    { title: 'ランキング管理', href: '/admin/ranking', icon: Trophy },
    { title: 'つぶやき管理', href: '/admin/tweets', icon: MessageSquareText },
    { title: '売上管理', href: '/admin/sales', icon: DollarSign },
    { title: '領収書管理', href: '/admin/receipts', icon: Receipt },
    { title: '支払管理', href: '/admin/payments', icon: CreditCard },
    { title: '支払明細管理', href: '/admin/payment-details', icon: FileSignature },
    { title: 'ポイント取引管理', href: '/admin/point-transactions', icon: Clock },
    { title: 'お知らせ配信', href: '/admin/notifications', icon: Megaphone },
];

// const footerNavItems: NavItem[] = [
//     {
//         title: 'Repository',
//         href: 'https://github.com/laravel/react-starter-kit',
//         icon: Folder,
//     },
//     {
//         title: 'Documentation',
//         href: 'https://laravel.com/docs/starter-kits#react',
//         icon: BookOpen,
//     },
// ];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
