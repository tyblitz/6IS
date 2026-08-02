import { ModuleName } from '../types/module'
import type { SidebarItem } from '../types/SidebarItem'

import { inventoryMenu } from './inventoryMenu'
import { communicationsMenu } from './communicationsMenu'
import { accomplishmentsMenu } from './accomplishmentsMenu'

export const moduleMenus: Record<ModuleName, SidebarItem[]> = {
  [ModuleName.Dashboard]: [],
  [ModuleName.Inventory]: inventoryMenu,
  [ModuleName.Communications]: communicationsMenu,
  [ModuleName.Accomplishments]: accomplishmentsMenu,

  // Mappings for subpages
  [ModuleName.Equipment]: inventoryMenu,
  [ModuleName.JRRS]: inventoryMenu,
}