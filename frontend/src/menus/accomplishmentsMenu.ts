import {
  gridOutline,
  todayOutline,
  calendarOutline,
  pieChartOutline,
  ribbonOutline,
  timeOutline
} from 'ionicons/icons'
import type { SidebarItem } from '../types/SidebarItem'

export const accomplishmentsMenu: SidebarItem[] = [
  {
    label: 'Overview',
    icon: gridOutline,
    route: '/accomplishments'
  },
  {
    label: 'Daily Report',
    icon: todayOutline,
    route: '/accomplishments/daily'
  },
  {
    label: 'Monthly Report',
    icon: calendarOutline,
    route: '/accomplishments/monthly'
  },
  {
    label: 'Quarterly Report',
    icon: pieChartOutline,
    route: '/accomplishments/quarterly'
  },
  {
    label: 'Annual Report',
    icon: ribbonOutline,
    route: '/accomplishments/annual'
  },
  {
    label: 'Custom Period Report',
    icon: timeOutline,
    route: '/accomplishments/custom'
  }
]
