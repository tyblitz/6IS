// frontend/src/services/inventoryService.ts
// Frontend Service for 6IS Extensible Inventory Module & Readiness Data

import type {
  ReportingPeriod,
  OverviewData,
  EquipmentItem,
  EquipmentFormPayload,
  OfficeItem,
  JrrsItem,
  EquipmentType,
  EquipmentSubtype,
  EquipmentStatusOption,
  AttributeDefinition,
  ReferenceOptions,
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
 * Fetches Equipment Types
 */
export async function fetchEquipmentTypes(): Promise<ApiResponse<EquipmentType[]>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=equipment_types`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch equipment types.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches Equipment Subtypes (optionally filtered by equipment_type_id)
 */
export async function fetchEquipmentSubtypes(typeId?: number): Promise<ApiResponse<EquipmentSubtype[]>> {
  try {
    const url = typeId ? `${API_BASE_URL}?view=equipment_subtypes&type_id=${typeId}` : `${API_BASE_URL}?view=equipment_subtypes`
    const res = await fetch(url, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch equipment subtypes.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches Equipment Status options
 */
export async function fetchEquipmentStatuses(): Promise<ApiResponse<EquipmentStatusOption[]>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=statuses`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch equipment statuses.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches Attribute Definitions for an equipment subtype
 */
export async function fetchAttributeDefinitions(subtypeId: number): Promise<ApiResponse<AttributeDefinition[]>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=attribute_definitions&subtype_id=${subtypeId}`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch attribute definitions.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches consolidated reference options (types, subtypes, statuses)
 */
export async function fetchReferenceOptions(): Promise<ApiResponse<ReferenceOptions>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=reference_options`, {
      method: 'GET',
      credentials: 'include'
    })
    const json = await res.json()
    if (json.success && json.data) {
      json.data = {
        equipment_types: json.data.equipment_types || json.data.types || [],
        equipment_subtypes: json.data.equipment_subtypes || json.data.subtypes || [],
        statuses: json.data.statuses || [],
        offices: json.data.offices || []
      }
    }
    return json
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch reference options.',
      data: { equipment_types: [], equipment_subtypes: [], statuses: [] },
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
    const json = await res.json()
    if (json.success) {
      if (Array.isArray(json.data)) {
        json.data = {
          period,
          period_label: period,
          is_current: true,
          items: json.data
        }
      } else if (!json.data || !Array.isArray(json.data.items)) {
        json.data = {
          period,
          period_label: period,
          is_current: true,
          items: json.data?.items || []
        }
      }
    }
    return json
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
 * Fetches single equipment record with dynamic attribute values
 */
export async function fetchEquipmentDetail(id: number): Promise<ApiResponse<EquipmentItem>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=equipment&id=${id}`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch equipment details.',
      data: null as any,
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches active offices for dropdown selection
 */
export async function fetchOffices(): Promise<ApiResponse<OfficeItem[]>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=offices`, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch offices.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Creates new equipment entry with dynamic attribute values
 */
export async function createEquipment(payload: EquipmentFormPayload): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=create_equipment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to create equipment record.',
      data: null,
      errors: { network: err.message }
    }
  }
}

/**
 * Updates an existing equipment entry with dynamic attribute values
 */
export async function updateEquipment(payload: EquipmentFormPayload): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=update_equipment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to update equipment record.',
      data: null,
      errors: { network: err.message }
    }
  }
}

/**
 * Soft deletes an equipment entry
 */
export async function deleteEquipment(id: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=delete_equipment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id })
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to delete equipment record.',
      data: null,
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
    const json = await res.json()
    if (json.success) {
      if (Array.isArray(json.data)) {
        json.data = {
          period,
          period_label: period,
          is_current: true,
          items: json.data
        }
      } else if (!json.data || !Array.isArray(json.data.items)) {
        json.data = {
          period,
          period_label: period,
          is_current: true,
          items: json.data?.items || []
        }
      }
    }
    return json
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
 * Updates JRRS target quantity for an equipment subtype (Administrator only)
 */
export async function updateJrrsTarget(subtypeId: number, targetQuantity: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=update_jrrs`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        equipment_subtype_id: subtypeId,
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

/**
 * Save (Create/Update) Equipment Type
 */
export async function saveEquipmentType(payload: { id?: number; name: string; code?: string; is_active?: boolean }): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=save_equipment_type`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to save equipment type.', data: null, errors: { network: err.message } }
  }
}

/**
 * Delete Equipment Type
 */
export async function deleteEquipmentType(id: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=delete_equipment_type`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id })
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to delete equipment type.', data: null, errors: { network: err.message } }
  }
}

/**
 * Save (Create/Update) Equipment Subtype
 */
export async function saveEquipmentSubtype(payload: { id?: number; equipment_type_id: number; name: string; code?: string; is_active?: boolean }): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=save_equipment_subtype`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to save equipment subtype.', data: null, errors: { network: err.message } }
  }
}

/**
 * Delete Equipment Subtype
 */
export async function deleteEquipmentSubtype(id: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=delete_equipment_subtype`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id })
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to delete equipment subtype.', data: null, errors: { network: err.message } }
  }
}

/**
 * Save (Create/Update) Equipment Status
 */
export async function saveEquipmentStatus(payload: { id?: number; name: string; code?: string; is_active?: boolean }): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=save_equipment_status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to save equipment status.', data: null, errors: { network: err.message } }
  }
}

/**
 * Delete Equipment Status
 */
export async function deleteEquipmentStatus(id: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=delete_equipment_status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id })
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to delete equipment status.', data: null, errors: { network: err.message } }
  }
}

/**
 * Save (Create/Update) Attribute Definition
 */
export async function saveAttributeDefinition(payload: {
  id?: number;
  equipment_subtype_id: number;
  attribute_name: string;
  attribute_code?: string;
  data_type: string;
  is_required?: boolean;
  sort_order?: number;
  is_active?: boolean;
}): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=save_attribute_definition`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to save attribute definition.', data: null, errors: { network: err.message } }
  }
}

/**
 * Delete Attribute Definition
 */
export async function deleteAttributeDefinition(id: number): Promise<ApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=delete_attribute_definition`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id })
    })
    return await res.json()
  } catch (err: any) {
    return { success: false, message: 'Failed to delete attribute definition.', data: null, errors: { network: err.message } }
  }
}
