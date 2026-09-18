// frontend/src/services/budgetService.ts
// Service layer for interacting with the backend Budget & R&M API

import type {
  BudgetScheduleItem,
  BudgetDisbursementItem,
  BudgetMetrics,
  BudgetOptionsData,
  CreateDisbursementPayload,
  CreateSchedulePayload,
  UpdateDisbursementPayload,
  UpdateSchedulePayload
} from '../types/budget'
import { apiFetch, resolveApiUrl } from '../utils/api'

const API_URL = resolveApiUrl('budget/index.php')

export interface BudgetScheduleResponse {
  schedules: BudgetScheduleItem[]
  metrics: BudgetMetrics
}

export interface BudgetDisbursementResponse {
  disbursements: BudgetDisbursementItem[]
  total_disbursed: number
  total_count: number
}

export async function fetchBudgetOptions(): Promise<BudgetOptionsData> {
  const url = `${API_URL}?view=options`
  const response = await apiFetch(url, { method: 'GET' })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to fetch budget options.')
  }

  return result.data
}

export async function fetchBudgetSchedules(officeId?: number, fiscalYear?: number): Promise<BudgetScheduleResponse> {
  const query = new URLSearchParams()
  query.append('view', 'schedule')
  if (officeId) query.append('office_id', officeId.toString())
  if (fiscalYear) query.append('fiscal_year', fiscalYear.toString())

  const url = `${API_URL}?${query.toString()}`
  const response = await apiFetch(url, { method: 'GET' })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to fetch budget schedules.')
  }

  return result.data
}

export async function fetchBudgetDisbursements(officeId?: number, search?: string, scheduleId?: number): Promise<BudgetDisbursementResponse> {
  const query = new URLSearchParams()
  query.append('view', 'disbursements')
  if (officeId) query.append('office_id', officeId.toString())
  if (search) query.append('search', search)
  if (scheduleId) query.append('schedule_id', scheduleId.toString())

  const url = `${API_URL}?${query.toString()}`
  const response = await apiFetch(url, { method: 'GET' })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to fetch budget disbursements.')
  }

  return result.data
}

export async function createDisbursement(payload: CreateDisbursementPayload): Promise<{ id: number }> {
  const response = await apiFetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  const result = await response.json()

  if (!result.success) {
    const errorMsg = result.errors ? Object.values(result.errors).join(', ') : result.message
    throw new Error(errorMsg || 'Failed to record disbursement.')
  }

  return result.data
}

export async function createSchedule(payload: CreateSchedulePayload): Promise<{ id: number }> {
  const response = await apiFetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  const result = await response.json()

  if (!result.success) {
    const errorMsg = result.errors ? Object.values(result.errors).join(', ') : result.message
    throw new Error(errorMsg || 'Failed to create budget schedule.')
  }

  return result.data
}

export async function updateDisbursement(payload: UpdateDisbursementPayload): Promise<void> {
  const response = await apiFetch(API_URL, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  const result = await response.json()

  if (!result.success) {
    const errorMsg = result.errors ? Object.values(result.errors).join(', ') : result.message
    throw new Error(errorMsg || 'Failed to update disbursement.')
  }
}

export async function updateSchedule(payload: UpdateSchedulePayload): Promise<void> {
  const response = await apiFetch(API_URL, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  const result = await response.json()

  if (!result.success) {
    const errorMsg = result.errors ? Object.values(result.errors).join(', ') : result.message
    throw new Error(errorMsg || 'Failed to update schedule.')
  }
}

export async function deleteBudgetSchedule(id: number): Promise<void> {
  const response = await apiFetch(`${API_URL}?id=${id}&type=schedule`, {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, type: 'schedule' })
  })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to delete budget schedule.')
  }
}

export async function deleteBudgetDisbursement(id: number): Promise<void> {
  const response = await apiFetch(`${API_URL}?id=${id}&type=disbursement`, {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, type: 'disbursement' })
  })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to delete disbursement.')
  }
}
