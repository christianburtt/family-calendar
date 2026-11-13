<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BriefcaseMedical, CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { calendar } from '@/routes';
import medicalRoutes from '@/routes/medical';
import type { NavItem } from '@/types';

const page = usePage();

const hasMedical = computed(() => page.props.modules?.medical === true);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Calendar',
            href: calendar(),
            icon: CalendarDays,
        },
    ];

    if (hasMedical.value) {
        items.push({
            title: 'Medical',
            href: medicalRoutes.index(),
            icon: BriefcaseMedical,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="calendar()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
