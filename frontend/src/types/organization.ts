// frontend/src/types/organization.ts
// Type definitions for 6IS Core Organization (Phase 3)

export interface Organization {
  id: number
  name: string
  short_name?: string | null
  description?: string | null
  address?: string | null
  contact_number?: string | null
  email?: string | null
  logo_path?: string | null
  is_active: number | boolean
  created_at?: string
  updated_at?: string
}

export interface OrganizationUpdatePayload {
  name: string
  short_name?: string | null
  description?: string | null
  address?: string | null
  contact_number?: string | null
  email?: string | null
  logo_path?: string | null
  is_active?: number | boolean
}
