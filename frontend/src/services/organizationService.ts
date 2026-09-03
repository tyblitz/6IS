// frontend/src/services/organizationService.ts
// Frontend Service for 6IS Core Organization API Communications (Phase 3)

import type { Organization, OrganizationUpdatePayload } from '../types/organization'

function resolveOrgApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/core/organization/index.php`
  }
  return 'http://localhost/6IS/backend/api/core/organization/index.php'
}

const ORG_API_URL = resolveOrgApiUrl()

/**
 * Fetches the active primary organization profile
 */
export async function fetchOrganization(): Promise<Organization | null> {
  try {
    const res = await fetch(ORG_API_URL, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include'
    })

    if (!res.ok) {
      console.warn(`[organizationService] fetchOrganization returned status ${res.status}`)
      return null
    }

    const data = await res.json()
    if (data.success && data.data) {
      return data.data
    }
    return null
  } catch (err) {
    console.error('[organizationService] Error fetching organization profile:', err)
    return null
  }
}

/**
 * Updates the primary organization profile
 */
export async function updateOrganization(
  payload: OrganizationUpdatePayload
): Promise<{ success: boolean; message: string; data?: Organization }> {
  try {
    const res = await fetch(ORG_API_URL, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    return {
      success: !!data.success,
      message: data.message || (data.success ? 'Organization updated successfully.' : 'Failed to update organization.'),
      data: data.data
    }
  } catch (err: any) {
    console.error('[organizationService] Error updating organization:', err)
    return {
      success: false,
      message: err.message || 'Network error updating organization profile.'
    }
  }
}
