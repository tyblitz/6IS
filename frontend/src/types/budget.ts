// frontend/src/types/budget.ts
// TypeScript contracts for 6IS Budget & R&M Fund Monitoring Module

export interface BudgetScheduleItem {
  id: number
  fiscal_year: number
  office_id: number
  office_code: string
  office_name: string
  paps: string
  allocated_amount: number
  jan: number
  feb: number
  mar: number
  apr: number
  may: number
  jun: number
  jul: number
  aug: number
  sep: number
  oct: number
  nov: number
  dec: number
  schedule_release_count: number
  disbursed_total: number
  remaining_balance: number
  disbursed_months: number[]
  remarks: string | null
  created_at: string
  updated_at: string
}

export interface BudgetDisbursementItem {
  id: number
  schedule_id: number | null
  office_id: number
  office_code: string
  office_name: string
  paps: string
  disbursement_date: string
  particulars: string
  amount_disbursed: number
  received_by: string | null
  chargeability: string | null
  quarter: string
  remarks: string | null
  created_at: string
  updated_at: string
}

export interface BudgetMetrics {
  mooe_total: number
  overall_disbursed_total: number
  unallocated_disbursed_total: number
  overall_remaining_balance: number
  total_scheduled_releases: number
  total_schedule_items: number
}

export interface BudgetScheduleOption {
  id: number
  office_id: number
  fiscal_year: number
  paps: string
  allocated_amount: number
  office_code: string
  office_name: string
}

export interface BudgetOfficeOption {
  id: number
  office_name: string
  office_code: string
  office_abbv: string
}

export interface BudgetOptionsData {
  offices: BudgetOfficeOption[]
  schedules: BudgetScheduleOption[]
}

export interface CreateDisbursementPayload {
  action: 'disbursement'
  schedule_id: number
  disbursement_date: string
  particulars: string
  amount_disbursed: number
  received_by?: string
  chargeability?: string
  remarks?: string
  office_id?: number
}

export interface CreateSchedulePayload {
  action: 'schedule'
  fiscal_year: number
  office_id: number
  paps: string
  allocated_amount: number
  jan: number
  feb: number
  mar: number
  apr: number
  may: number
  jun: number
  jul: number
  aug: number
  sep: number
  oct: number
  nov: number
  dec: number
  remarks?: string
}

export interface UpdateDisbursementPayload {
  id: number
  type: 'disbursement'
  schedule_id?: number
  disbursement_date?: string
  particulars?: string
  amount_disbursed?: number
  received_by?: string
  chargeability?: string
  remarks?: string
}

export interface UpdateSchedulePayload {
  id: number
  type: 'schedule'
  paps?: string
  allocated_amount?: number
  jan?: number
  feb?: number
  mar?: number
  apr?: number
  may?: number
  jun?: number
  jul?: number
  aug?: number
  sep?: number
  oct?: number
  nov?: number
  dec?: number
  remarks?: string
}
