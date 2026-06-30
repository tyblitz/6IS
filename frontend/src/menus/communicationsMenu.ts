import { chatbubbleOutline } from 'ionicons/icons'

import type { SidebarItem } from '../types/SidebarItem'

export const communicationsMenu: SidebarItem[] = [
    {
        label: 'Overview',
        icon: chatbubbleOutline,
        route: '/communications'
    }
]