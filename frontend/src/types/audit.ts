// frontend/src/types/audit.ts
// Type definitions for 6IS Core Audit Logging & Governance (Phase 4)

export interface AuditLogEntry {
  id: number
  user_id: number | null
  username: string | null
  full_name: string | null
  action: string
  module_key: string
  entity_type: string
  entity_id: string | null
  description: string | null
  old_values: Record<string, any> | string | null
  new_values: Record<string, any> | string | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface AuditPagination {
  page: number
  limit: number
  total: number
  total_pages: number
}

export interface AuditFilterParams {
  page?: number
  limit?: number
  date_from?: string
  date_to?: string
  user_id?: number
  module_key?: string
  action?: string
  entity_type?: string
  entity_id?: string
  search?: string
}

export interface AuditApiResponse {
  success: boolean
  message: string
  data: AuditLogEntry[]
  pagination: AuditPagination
  errors?: any
}
