import type { SidebarItem } from '../types/SidebarItem'
import {
  gridOutline,
  cubeOutline,
  layersOutline,
  optionsOutline,
  listOutline,
  chatbubbleEllipsesOutline,
  folderOutline,
  bookmarkOutline,
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
    icon: cubeOutline,
    children: [
      {
        label: 'Equipment Registry',
        route: '/administrator/inventory',
        icon: cubeOutline
      },
      {
        label: 'Equipment Types',
        route: '/administrator/inventory/types',
        icon: layersOutline
      },
      {
        label: 'Equipment Subtypes',
        route: '/administrator/inventory/subtypes',
        icon: gridOutline
      },
      {
        label: 'Equipment Statuses',
        route: '/administrator/inventory/statuses',
        icon: optionsOutline
      },
      {
        label: 'Equipment Attributes',
        route: '/administrator/inventory/attributes',
        icon: listOutline
      }
    ]
  },
  {
    label: 'Communications',
    route: '/administrator/communications',
    icon: chatbubbleEllipsesOutline,
    children: [
      {
        label: 'Communications Log',
        route: '/administrator/communications',
        icon: chatbubbleEllipsesOutline
      },
      {
        label: 'Categories',
        route: '/administrator/communications/categories',
        icon: folderOutline
      },
      {
        label: 'Purposes',
        route: '/administrator/communications/purposes',
        icon: bookmarkOutline
      },
      {
        label: 'Statuses',
        route: '/administrator/communications/statuses',
        icon: optionsOutline
      }
    ]
  },
  {
    label: 'Accomplishments',
    route: '/administrator/accomplishments',
    icon: clipboardOutline,
    children: [
      {
        label: 'Accomplishments Log',
        route: '/administrator/accomplishments',
        icon: clipboardOutline
      },
      {
        label: 'Categories',
        route: '/administrator/accomplishments/categories',
        icon: folderOutline
      }
    ]
  },
  {
    label: 'Users',
    route: '/administrator/users',
    icon: peopleOutline
  },
  {
    label: 'Modules',
    route: '/administrator/modules',
    icon: gridOutline
  }
]
