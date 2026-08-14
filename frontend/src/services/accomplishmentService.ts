// frontend/src/services/accomplishmentService.ts
import type {
  ApiResponse,
  AccomplishmentOptions,
  OverviewSummary,
  AccomplishmentItem,
  ReportData,
  AccomplishmentFormPayload
} from '../types/accomplishment'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/accomplishments/index.php`
  }
  return 'http://localhost/6IS/backend/api/accomplishments/index.php'
}

const API_BASE_URL = resolveApiUrl()

export async function fetchAccomplishmentOptions(): Promise<ApiResponse<AccomplishmentOptions>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=options`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchOverviewSummary(): Promise<ApiResponse<OverviewSummary>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=overview`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchAccomplishmentById(id: number): Promise<ApiResponse<AccomplishmentItem>> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchDailyAccomplishments(
  date?: string,
  officeId?: number,
  search?: string,
  categoryId?: number
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'daily' })
    if (date) params.append('date', date)
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (categoryId && categoryId > 0) params.append('category_id', categoryId.toString())
    if (search) params.append('search', search)

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchMonthlyAccomplishments(
  year?: number,
  month?: number
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'monthly' })
    if (year) params.append('year', year.toString())
    if (month) params.append('month', month.toString())

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch monthly accomplishments.',
      data: { records: [], accomplishments_by_category: [], outgoing_comms_by_category: [], clearances_by_purpose: [], communications_stats: { incoming: 0, outgoing: 0 } },
      errors: { network: err.message }
    }
  }
}

export async function fetchQuarterlyAccomplishments(
  year?: number,
  quarter?: number
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'quarterly' })
    if (year) params.append('year', year.toString())
    if (quarter) params.append('quarter', quarter.toString())

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch quarterly accomplishments.',
      data: { records: [], accomplishments_by_category: [], outgoing_comms_by_category: [], clearances_by_purpose: [], communications_stats: { incoming: 0, outgoing: 0 } },
      errors: { network: err.message }
    }
  }
}

export async function fetchAnnualAccomplishments(
  year?: number
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'annual' })
    if (year) params.append('year', year.toString())

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch annual accomplishments.',
      data: { records: [], accomplishments_by_category: [], outgoing_comms_by_category: [], clearances_by_purpose: [], communications_stats: { incoming: 0, outgoing: 0 } },
      errors: { network: err.message }
    }
  }
}

export async function fetchCustomPeriodAccomplishments(
  startDate: string,
  endDate: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({
      view: 'custom',
      start_date: startDate,
      end_date: endDate
    })

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`, { credentials: 'include' })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch custom period accomplishments.',
      data: { records: [], accomplishments_by_category: [], outgoing_comms_by_category: [], clearances_by_purpose: [], communications_stats: { incoming: 0, outgoing: 0 } },
      errors: { network: err.message }
    }
  }
}

export async function createAccomplishment(payload: AccomplishmentFormPayload): Promise<ApiResponse> {
  try {
    const res = await fetch(API_BASE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function updateAccomplishment(id: number, payload: AccomplishmentFormPayload): Promise<ApiResponse> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function deleteAccomplishment(id: number): Promise<ApiResponse> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`, {
      method: 'DELETE',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}
