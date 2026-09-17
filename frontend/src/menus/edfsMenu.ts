// frontend/src/menus/edfsMenu.ts
// Navigation items for EDFS Account Monitoring

import {
  documentTextOutline
} from 'ionicons/icons'
import type { SidebarItem } from '../types/SidebarItem'

export const edfsMenu: SidebarItem[] = [
  {
    label: 'All Accounts',
    icon: documentTextOutline,
    route: '/edfs'
  }
]
