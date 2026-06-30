import {
    gridOutline,
    desktopOutline,
    clipboardOutline
} from 'ionicons/icons'

import type { SidebarItem } from '../types/SidebarItem'

export const inventoryMenu: SidebarItem[] = [
    {
        label: 'Overview',
        icon: gridOutline,
        route: '/inventory'
    },
    {
        label: 'ICT Equipment',
        icon: desktopOutline,
        route: '/inventory/equipment'
    },
    {
        label: 'JRRS',
        icon: clipboardOutline,
        route: '/inventory/jrrs'
    }
]