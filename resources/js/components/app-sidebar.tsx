import { Link } from '@inertiajs/react';
import { ArrowLeftRightIcon, BellIcon, BookOpen, Building2, Building2Icon, FolderGit2, HousePlug, HousePlugIcon, LayoutGrid, MegaphoneIcon, NewspaperIcon, RadioTowerIcon, StoreIcon, TagsIcon, UserRoundIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import AppearanceToggleTab from '@/components/appearance-tabs'; // adjust path to match your actual file



const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const catalogNavItems: NavItem[] = [
    {
        title: 'Companies',
        href: '/m/companies',
        icon: Building2Icon,
    },
    {
        title: 'Blogs',
        href: '/m/blogs',
        icon: NewspaperIcon,
    },
    {
        title: 'Affiliate Accounts',
        href: '/m/affiliateAccounts',
        icon: UserRoundIcon,
    },
    {
        title: 'Affiliates Catalog',
        href: '/m/affiliateCatalog',
        icon: StoreIcon,
    },
    {
        title: 'Traffics Catalog',
        href: '/m/trafficCatalog',
        icon: HousePlugIcon,
    },
    {
        title: 'Traffic Accounts',
        href: '/m/trafficAccounts',
        icon: RadioTowerIcon,
    },
    {
        title: 'Offers',
        href: '/m/offers',
        icon: TagsIcon ,
    },
    {
        title: 'Campaigns',
        href: '/m/campaigns',
        icon: MegaphoneIcon,
    },
    {
        title: 'Conversions',
        href: '/conversions',
        icon: ArrowLeftRightIcon,
    },
    {
        title: 'Notifications',
        href: '/notifications',
        icon: BellIcon,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },

    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
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
                <NavMain items={mainNavItems} label="Platform" />
                <NavMain items={catalogNavItems} label="Catalog" />
            </SidebarContent>

            <SidebarFooter>
            <AppearanceToggleTab />
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
