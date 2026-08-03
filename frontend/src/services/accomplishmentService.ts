// frontend/src/services/accomplishmentService.ts
import type {
  ApiResponse,
  AccomplishmentOptions,
  OverviewSummary,
  AccomplishmentItem,
  ReportData,
  AccomplishmentFormPayload
} from '../types/accomplishment'

const API_BASE_URL = 'http://localhost/6IS/backend/api/accomplishments/index.php'

export async function fetchAccomplishmentOptions(): Promise<ApiResponse<AccomplishmentOptions>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=options`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchOverviewSummary(): Promise<ApiResponse<OverviewSummary>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=overview`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchAccomplishmentById(id: number): Promise<ApiResponse<AccomplishmentItem>> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchDailyAccomplishments(
  date?: string,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'daily' })
    if (date) params.append('date', date)
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchMonthlyAccomplishments(
  year?: number,
  month?: number,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'monthly' })
    if (year) params.append('year', year.toString())
    if (month) params.append('month', month.toString())
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchQuarterlyAccomplishments(
  year?: number,
  quarter?: number,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'quarterly' })
    if (year) params.append('year', year.toString())
    if (quarter) params.append('quarter', quarter.toString())
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchAnnualAccomplishments(
  year?: number,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'annual' })
    if (year) params.append('year', year.toString())
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function fetchCustomPeriodAccomplishments(
  startDate: string,
  endDate: string,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({
      view: 'custom',
      start_date: startDate,
      end_date: endDate
    })
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetch(`${API_BASE_URL}?${params.toString()}`)
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}

export async function createAccomplishment(payload: AccomplishmentFormPayload): Promise<ApiResponse> {
  try {
    const res = await fetch(API_BASE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
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
      method: 'DELETE'
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to connect to backend.', data: null, errors: { network: err.message } }
  }
}
