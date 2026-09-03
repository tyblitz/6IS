// frontend/src/services/officeService.ts
// Frontend Service for 6IS Core Offices API Communications (Phase 3)

import type { Office, OfficeCreatePayload, OfficeUpdatePayload } from '../types/office'

function resolveOfficesApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/core/offices/index.php`
  }
  return 'http://localhost/6IS/backend/api/core/offices/index.php'
}

const OFFICES_API_URL = resolveOfficesApiUrl()

/**
 * Fetches registered offices, optionally filtered by active status or search query
 */
export async function fetchOffices(activeOnly: boolean = false, search: string = ''): Promise<Office[]> {
  try {
    const params = new URLSearchParams()
    if (activeOnly) params.append('active_only', '1')
    if (search.trim()) params.append('search', search.trim())

    const queryString = params.toString() ? `?${params.toString()}` : ''
    const res = await fetch(`${OFFICES_API_URL}${queryString}`, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })

    if (!res.ok) {
      console.warn(`[officeService] fetchOffices returned status ${res.status}`)
      return []
    }

    const data = await res.json()
    if (data.success && Array.isArray(data.data)) {
      return data.data
    }
    return []
  } catch (err) {
    console.error('[officeService] Error fetching offices:', err)
    return []
  }
}

/**
 * Fetches a single office by ID
 */
export async function fetchOffice(id: number): Promise<Office | null> {
  try {
    const res = await fetch(`${OFFICES_API_URL}?id=${id}`, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })

    if (!res.ok) {
      return null
    }

    const data = await res.json()
    if (data.success && data.data) {
      return data.data
    }
    return null
  } catch (err) {
    console.error(`[officeService] Error fetching office #${id}:`, err)
    return null
  }
}

/**
 * Creates a new office
 */
export async function createOffice(
  payload: OfficeCreatePayload
): Promise<{ success: boolean; message: string; data?: Office }> {
  try {
    const res = await fetch(OFFICES_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    return {
      success: !!data.success,
      message: data.message || (data.success ? 'Office created successfully.' : 'Failed to create office.'),
      data: data.data
    }
  } catch (err: any) {
    console.error('[officeService] Error creating office:', err)
    return {
      success: false,
      message: err.message || 'Network error creating office.'
    }
  }
}

/**
 * Updates an existing office
 */
export async function updateOffice(
  payload: OfficeUpdatePayload
): Promise<{ success: boolean; message: string; data?: Office }> {
  try {
    const res = await fetch(OFFICES_API_URL, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    return {
      success: !!data.success,
      message: data.message || (data.success ? 'Office updated successfully.' : 'Failed to update office.'),
      data: data.data
    }
  } catch (err: any) {
    console.error('[officeService] Error updating office:', err)
    return {
      success: false,
      message: err.message || 'Network error updating office.'
    }
  }
}

/**
 * Toggles an office's active status
 */
export async function toggleOfficeActive(
  id: number,
  currentStatus: boolean | number
): Promise<{ success: boolean; message: string; data?: Office }> {
  const isCurrentlyActive = typeof currentStatus === 'boolean' ? currentStatus : currentStatus === 1
  return updateOffice({
    id,
    is_active: isCurrentlyActive ? 0 : 1
  })
}

/**
 * Deletes an office (enforcing zero-dependency protection)
 */
export async function deleteOffice(
  id: number
): Promise<{ success: boolean; message: string }> {
  try {
    const res = await fetch(OFFICES_API_URL, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id })
    })

    const data = await res.json()
    return {
      success: !!data.success,
      message: data.message || (data.success ? 'Office deleted successfully.' : 'Failed to delete office.')
    }
  } catch (err: any) {
    console.error('[officeService] Error deleting office:', err)
    return {
      success: false,
      message: err.message || 'Network error deleting office.'
    }
  }
}
