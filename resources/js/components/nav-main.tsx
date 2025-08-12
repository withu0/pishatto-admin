import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const [hasUnseenConcierge, setHasUnseenConcierge] = useState(false);

    useEffect(() => {
        const readFlag = () => {
            try {
                if (typeof window !== 'undefined') {
                    setHasUnseenConcierge(localStorage.getItem('concierge:hasUnseen') === '1');
                }
            } catch {}
        };
        readFlag();
        const handler = () => readFlag();
        if (typeof window !== 'undefined') {
            window.addEventListener('concierge:hasUnseenChanged', handler as EventListener);
        }
        return () => {
            if (typeof window !== 'undefined') {
                window.removeEventListener('concierge:hasUnseenChanged', handler as EventListener);
            }
        };
    }, [page.url]);
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const isConcierge = item.href === '/admin/concierge';
                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={page.url.startsWith(item.href)} tooltip={{ children: item.title }}>
                                <Link href={item.href} prefetch className="relative">
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                    {isConcierge && hasUnseenConcierge && (
                                        <span className="absolute -top-1 -right-1 inline-flex h-2 w-2 rounded-full bg-red-500" />
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
