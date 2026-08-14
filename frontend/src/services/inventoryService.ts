// frontend/src/services/inventoryService.ts
// Frontend Service for 6IS Inventory Module & Readiness Data

import type {
  ReportingPeriod,
  OverviewData,
  EquipmentItem,
  JrrsItem,
  ApiResponse
} from '../types/inventory'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/inventory/index.php`
  }
  return 'http://localhost/6IS/backend/api/inventory/index.php'
}

const API_BASE_URL = resolveApiUrl()

/**
 * Fetches available reporting periods (current month + historical snapshot months)
 */
export async function fetchReportingPeriods(): Promise<ApiResponse<ReportingPeriod[]>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=periods`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch reporting periods.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches Inventory Overview summary & readiness percentages for selected period
 */
export async function fetchInventoryOverview(period: string): Promise<ApiResponse<OverviewData>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=overview&period=${encodeURIComponent(period)}`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch inventory overview data.',
      data: {
        period,
        period_label: period,
        is_current: true,
        maintenance_readiness_pct: 0,
        equipment_readiness_pct: 0,
        total_equipment: 0,
        serviceable_count: 0,
        for_repair_count: 0,
        unserviceable_count: 0,
        type_breakdown: []
      },
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches equipment registry records for selected period
 */
export async function fetchEquipmentList(period: string): Promise<ApiResponse<{ period: string; period_label: string; is_current: boolean; items: EquipmentItem[] }>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=equipment&period=${encodeURIComponent(period)}`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch equipment records.',
      data: { period, period_label: period, is_current: true, items: [] },
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches JRRS target comparison & readiness % for selected period
 */
export async function fetchJrrsList(period: string): Promise<ApiResponse<{ period: string; period_label: string; is_current: boolean; items: JrrsItem[] }>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=jrrs&period=${encodeURIComponent(period)}`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch JRRS records.',
      data: { period, period_label: period, is_current: true, items: [] },
      errors: { network: err.message }
    }
  }
}

/**
 * Updates JRRS target quantity for an equipment type (Administrator only)
 */
export async function updateJrrsTarget(equipmentType: string, targetQuantity: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=update_jrrs`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        equipment_type: equipmentType,
        target_quantity: targetQuantity
      })
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to update JRRS target quantity.',
      data: null,
      errors: { network: err.message }
    }
  }
}

/**
 * Generates historical snapshot for a period (Administrator only)
 */
export async function generateSnapshot(yearMonth: string): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=generate_snapshot`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ year_month: yearMonth })
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to generate historical snapshot.',
      data: null,
      errors: { network: err.message }
    }
  }
}
