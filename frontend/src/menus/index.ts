import { ModuleName } from '../types/module'
import type { SidebarItem } from '@/types/SidebarItem'

import { inventoryMenu } from './inventoryMenu'
import { communicationsMenu } from './communicationsMenu'

export const moduleMenus: Record<ModuleName, SidebarItem[]> = {
  [ModuleName.Dashboard]: [],
  [ModuleName.Inventory]: inventoryMenu,
  [ModuleName.Communications]: communicationsMenu,

  // Temporary mappings so Inventory subpages use the same menu
  [ModuleName.Equipment]: inventoryMenu,
  [ModuleName.JRRS]: inventoryMenu,
}