import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRightIcon,
    BarChart3,
    BellIcon,
    Building2Icon,
    LayoutGrid,
    MegaphoneIcon,
    NewspaperIcon,
    RadioTowerIcon,
    Search,
    StoreIcon,
    TagsIcon,
    UserRoundIcon,
} from 'lucide-react';

import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import AppearanceToggleTab from '@/components/appearance-tabs';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Reporting',
        href: '/reporting',
        icon: BarChart3,
    },
        {
        title: 'Inspect ( Future )',
        href: '#',
        icon: Search,
    },
];

const managementNavItems: NavItem[] = [
    {
        title: 'Offers',
        href: '/m/offers',
        icon: TagsIcon,
    },
    {
        title: 'Campaigns',
        href: '/m/campaigns',
        icon: MegaphoneIcon,
    },
];

const activityNavItems: NavItem[] = [
    {
        title: 'Notifications',
        href: '/notifications',
        icon: BellIcon,
    },
    {
        title: 'Conversions',
        href: '/conversions',
        icon: ArrowLeftRightIcon,
    },
];

const accountsContentNavItems: NavItem[] = [
    {
        title: 'Affiliate Accounts',
        href: '/m/affiliateAccounts',
        icon: UserRoundIcon,
    },
    {
        title: 'Traffic Accounts',
        href: '/m/trafficAccounts',
        icon: RadioTowerIcon,
    },
    {
        title: 'Blogs',
        href: '/m/blogs',
        icon: NewspaperIcon,
    },
];

const catalogNavItems: NavItem[] = [
    {
        title: 'Companies',
        href: '/m/companies',
        icon: Building2Icon,
    },
    {
        title: 'Affiliates Catalog',
        href: '/m/affiliateCatalog',
        icon: StoreIcon,
    },
    {
        title: 'Traffics Catalog',
        href: '/m/trafficCatalog',
        icon: RadioTowerIcon,
    },
];

export function AppSidebar() {
    const { unreadNotificationsCount } = usePage<{
        unreadNotificationsCount?: number;
    }>().props;

    const unreadCount = Number(unreadNotificationsCount ?? 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    items={mainNavItems}
                    label="Main Menu"
                />

                <NavMain
                    items={managementNavItems}
                    label="Campaign Management"
                />

                <NavMain
                    items={activityNavItems}
                    label="Activity"
                    notificationCount={unreadCount}
                />

                <NavMain
                    items={accountsContentNavItems}
                    label="Accounts & Content"
                />

                <NavMain
                    items={catalogNavItems}
                    label="Catalog"
                />
            </SidebarContent>

            <SidebarFooter>
                <AppearanceToggleTab />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}