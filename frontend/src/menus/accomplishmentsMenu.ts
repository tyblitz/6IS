import { gridOutline, calendarOutline } from 'ionicons/icons'
import type { SidebarItem } from '../types/SidebarItem'

export const accomplishmentsMenu: SidebarItem[] = [
  {
    label: 'Overview',
    icon: gridOutline,
    route: '/accomplishments'
  },
  {
    label: 'Daily Accomplishments',
    icon: calendarOutline,
    route: '/accomplishments/daily'
  }
]
