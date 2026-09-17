import { ModuleName } from '../types/module'
import type { SidebarItem } from '../types/SidebarItem'

import { inventoryMenu } from './inventoryMenu'
import { communicationsMenu } from './communicationsMenu'
import { accomplishmentsMenu } from './accomplishmentsMenu'
import { calendarMenu } from './calendarMenu'
import { administratorMenu } from './administratorMenu'
import { edfsMenu } from './edfsMenu'

export const moduleMenus: Record<ModuleName, SidebarItem[]> = {
  [ModuleName.Dashboard]: [],
  [ModuleName.Inventory]: inventoryMenu,
  [ModuleName.Communications]: communicationsMenu,
  [ModuleName.Accomplishments]: accomplishmentsMenu,
  [ModuleName.Calendar]: calendarMenu,
  [ModuleName.Administrator]: administratorMenu,
  [ModuleName.EDFS]: edfsMenu,

  // Mappings for subpages
  [ModuleName.Equipment]: inventoryMenu,
  [ModuleName.JRRS]: inventoryMenu,

  // Future modules
  [ModuleName.Performance]: [],
  [ModuleName.Finances]: []
}