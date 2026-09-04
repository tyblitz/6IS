import {
  gridOutline,
  desktopOutline,
  clipboardOutline,
  radioOutline,
  statsChartOutline
} from 'ionicons/icons'

import type { SidebarItem } from '../types/SidebarItem'

export const inventoryMenu: SidebarItem[] = [
  {
    label: 'Overview',
    icon: gridOutline,
    route: '/inventory'
  },
  {
    label: 'Inventory',
    icon: desktopOutline,
    route: '/inventory/equipment',
    children: [
      {
        label: 'ICT Equipment',
        icon: desktopOutline,
        route: '/inventory/equipment/ict'
      },
      {
        label: 'Communications',
        icon: radioOutline,
        route: '/inventory/equipment/communications'
      }
    ]
  },
  {
    label: 'JRRS',
    icon: clipboardOutline,
    route: '/inventory/jrrs',
    children: [
      {
        label: 'ICT Readiness',
        icon: desktopOutline,
        route: '/inventory/jrrs/ict'
      },
      {
        label: 'Communications',
        icon: radioOutline,
        route: '/inventory/jrrs/communications'
      }
    ]
  },
  {
    label: 'G6 Readiness Report',
    icon: statsChartOutline,
    route: '/inventory/readiness'
  }
]