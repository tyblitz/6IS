import { ModuleName } from '@/types/module'
import type { SidebarItem } from '@/types/sidebar'

import { inventoryMenu } from './inventoryMenu'
import { communicationsMenu } from './communicationsMenu'

export const moduleMenus: Record<ModuleName, SidebarItem[]> = {
  [ModuleName.Dashboard]: [],
  [ModuleName.Inventory]: inventoryMenu,
  [ModuleName.Communications]: communicationsMenu,
}