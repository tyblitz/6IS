// frontend/src/types/edfs.ts
// Data contracts & type definitions for EDFS Account Monitoring

export interface EdfsAccount {
  id: number
  office_id: number | null
  office_name: string
  office_short_name?: string
  office_code?: string
  full_office_name?: string
  account_name: string | null
  current_title: string
  personnel_name: string | null
  username: string | null
  password?: string | null
  status: 'Active' | 'Inactive' | 'For Renewal'
  remarks: string | null
  deleted_at?: string | null
  created_at: string
  updated_at: string
}

export interface EdfsMetrics {
  total: number
  active: number
  inactive: number
  for_renewal: number
  assigned: number
  generic_desk: number
  offices_count?: number
}

export interface EdfsOfficeOption {
  office_id: number
  short_name: string
  full_name: string
  account_count: number
}

export interface EdfsListResponse {
  items: EdfsAccount[]
  pagination: {
    total: number
    page: number
    per_page: number
    total_pages: number
  }
  metrics: EdfsMetrics
  offices: EdfsOfficeOption[]
}

export interface EdfsAccountFormData {
  id?: number
  office_id: number | null
  office_name?: string
  current_title: string
  personnel_name: string
  username: string
  password?: string
  status: 'Active' | 'Inactive' | 'For Renewal'
  remarks: string
}
