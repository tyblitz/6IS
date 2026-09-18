// frontend/src/menus/budgetMenu.ts
// Navigation items for Budget & R&M Monitoring

import {
  cashOutline
} from 'ionicons/icons'
import type { SidebarItem } from '../types/SidebarItem'

export const budgetMenu: SidebarItem[] = [
  {
    label: 'Budget & R&M',
    icon: cashOutline,
    route: '/budget'
  }
]
