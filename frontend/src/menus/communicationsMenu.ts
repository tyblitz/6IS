import {
  gridOutline,
  mailUnreadOutline,
  paperPlaneOutline,
  statsChartOutline
} from 'ionicons/icons'

import type { SidebarItem } from '../types/SidebarItem'

export const communicationsMenu: SidebarItem[] = [
  {
    label: 'Overview',
    icon: gridOutline,
    route: '/communications'
  },
  {
    label: 'Incoming',
    icon: mailUnreadOutline,
    route: '/communications/incoming'
  },
  {
    label: 'Outgoing',
    icon: paperPlaneOutline,
    route: '/communications/outgoing'
  },
  {
    label: 'Reports',
    icon: statsChartOutline,
    route: '/communications/reports'
  }
]