// frontend/src/types/office.ts
// Type definitions for 6IS Core Offices (Phase 3)

export interface Office {
  id: number
  organization_id: number
  name: string
  code: string
  description?: string | null
  address?: string | null
  contact_number?: string | null
  email?: string | null
  is_active: number | boolean
  created_at?: string
  updated_at?: string
  user_count?: number
  // Backward compatibility fields
  office_name?: string
  office_code?: string
  office_abbv?: string
}

export interface OfficeCreatePayload {
  name: string
  code: string
  organization_id?: number
  description?: string | null
  address?: string | null
  contact_number?: string | null
  email?: string | null
  is_active?: number | boolean
}

export interface OfficeUpdatePayload {
  id: number
  name?: string
  code?: string
  description?: string | null
  address?: string | null
  contact_number?: string | null
  email?: string | null
  is_active?: number | boolean
}
