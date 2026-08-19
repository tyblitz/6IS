// frontend/src/types/inventory.ts
// TypeScript definitions for 6IS Extensible Inventory Module & Readiness Calculations

export type AttributeDataType = 'text' | 'number' | 'decimal' | 'date' | 'boolean' | 'select'

export interface EquipmentType {
  id: number
  name: string
  code: string
  is_active?: number | boolean
}

export interface EquipmentSubtype {
  id: number
  equipment_type_id: number
  equipment_type_name?: string
  name: string
  code: string
  is_active?: number | boolean
}

export interface EquipmentStatusOption {
  id: number
  name: string
  code: string
  is_active?: number | boolean
}

export interface AttributeDefinition {
  id: number
  equipment_subtype_id: number
  equipment_subtype_name?: string
  attribute_name: string
  attribute_code: string
  data_type: AttributeDataType
  is_required: number | boolean
  sort_order: number
  is_active?: number | boolean
}

export interface EquipmentAttributeItem {
  attribute_definition_id: number
  attribute_name: string
  attribute_code: string
  data_type: AttributeDataType
  is_required: boolean
  sort_order: number
  value: any
  display_value: string
}

export interface EquipmentItem {
  id: number
  office_id: number
  office_abbv: string
  office_name: string
  equipment_type_id: number
  equipment_type_name: string
  equipment_type_code?: string
  equipment_subtype_id: number
  equipment_subtype_name: string
  equipment_subtype_code?: string
  status_id: number
  status_name: string
  status_code?: string
  description: string
  serial_number: string | null
  date_acquired: string | null
  // Legacy string aliases for backwards compatibility
  equipment_type?: string
  equipment_subtype?: string
  status?: string
  attributes?: EquipmentAttributeItem[]
  attributes_map?: Record<number, any>
}

export interface EquipmentFormPayload {
  id?: number
  office_id: number
  equipment_type_id: number
  equipment_subtype_id: number
  status_id: number
  description?: string
  serial_number: string
  date_acquired: string
  attributes: Record<number, any> // attribute_definition_id => value
}

export interface OfficeItem {
  id: number
  office_name: string
  office_code: string
  office_abbv: string
}

export interface JrrsItem {
  id: number
  equipment_subtype_id: number
  equipment_subtype: string
  equipment_type_id: number
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

export interface ReferenceOptions {
  equipment_types: EquipmentType[]
  equipment_subtypes: EquipmentSubtype[]
  statuses: EquipmentStatusOption[]
}

export interface ApiResponse<T = any> {
  success: boolean
  message: string
  data: T
  errors?: Record<string, string> | null
}
