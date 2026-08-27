import {
  calendarOutline,
  todayOutline
} from 'ionicons/icons'
import type { SidebarItem } from '../types/SidebarItem'

export const calendarMenu: SidebarItem[] = [
  {
    label: 'Month View',
    icon: calendarOutline,
    route: '/calendar'
  },
  {
    label: 'Today',
    icon: todayOutline,
    route: `/calendar/day/${new Date().toISOString().slice(0, 10)}`
  }
]
