// frontend/src/types/module.ts
// Module Registry Types & Compile-Time Identifiers for 6IS

export enum ModuleName {
  Dashboard = 'dashboard',
  Inventory = 'inventory',
  Communications = 'communications',
  Accomplishments = 'accomplishments',
  Calendar = 'calendar',
  Administrator = 'administrator',

  // Feature identifiers mapped to Inventory
  Equipment = 'equipment',
  JRRS = 'JRRS',

  // Future modules
  Performance = 'performance',
  Finances = 'finances',
}

export interface SystemModule {
  id: number
  module_key: string
  name: string
  description: string | null
  icon: string | null
  route: string | null
  is_core: boolean
  is_active: boolean
  sort_order: number
  version: string | null
}

export interface ModulesApiResponse {
  success: boolean
  message: string
  data: SystemModule[] | null
  errors?: any
}

export interface ModuleUpdateApiResponse {
  success: boolean
  message: string
  data: SystemModule | null
  errors?: any
}