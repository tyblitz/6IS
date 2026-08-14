import type { SidebarItem } from '../types/SidebarItem'
import {
  gridOutline,
  cubeOutline,
  chatbubbleEllipsesOutline,
  clipboardOutline,
  peopleOutline
} from 'ionicons/icons'

export const administratorMenu: SidebarItem[] = [
  {
    label: 'Overview',
    route: '/administrator',
    icon: gridOutline
  },
  {
    label: 'Inventory',
    route: '/administrator/inventory',
    icon: cubeOutline
  },
  {
    label: 'Communications',
    route: '/administrator/communications',
    icon: chatbubbleEllipsesOutline
  },
  {
    label: 'Accomplishments',
    route: '/administrator/accomplishments',
    icon: clipboardOutline
  },
  {
    label: 'Users',
    route: '/administrator/users',
    icon: peopleOutline
  }
]
