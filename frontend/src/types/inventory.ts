// frontend/src/types/inventory.ts
// TypeScript definitions for 6IS Inventory Module & Readiness Calculations

export type EquipmentStatus = 'Serviceable' | 'For Repair' | 'For Turn-In / Unserviceable'

export interface EquipmentItem {
  id: number
  office_id: number
  office_abbv: string
  office_name: string
  equipment_type: string
  description: string
  serial_number: string | null
  date_acquired: string | null
  status: EquipmentStatus
}

export interface JrrsItem {
  id: number
  equipment_type: string
  target_quantity: number
  current_quantity: number
  shortage: number
  readiness_pct: number
}

export interface ReportingPeriod {
  year_month: string
  label: string
  is_current: boolean
}

export interface OverviewData {
  period: string
  period_label: string
  is_current: boolean
  maintenance_readiness_pct: number
  equipment_readiness_pct: number
  total_equipment: number
  serviceable_count: number
  for_repair_count: number
  unserviceable_count: number
  type_breakdown: JrrsItem[]
}

export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  errors?: Record<string, string> | null
}
