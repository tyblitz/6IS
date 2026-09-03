// frontend/src/services/moduleService.ts
// Frontend Service for 6IS Core Module Registry API Communications (Phase 4 Hardened)

import type { SystemModule, ModulesApiResponse, ModuleUpdateApiResponse } from '../types/module'
import { apiFetch } from '../utils/api'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/core/modules/index.php`
  }
  return 'http://localhost/6IS/backend/api/core/modules/index.php'
}

const API_BASE_URL = resolveApiUrl()

/**
 * Fetches the complete official module registry from the Core backend
 */
export async function fetchModules(): Promise<SystemModule[]> {
  try {
    const res = await apiFetch(API_BASE_URL, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    })

    if (!res.ok) {
      console.warn(`[moduleService] fetchModules failed with HTTP status ${res.status}`)
      return []
    }

    const data: ModulesApiResponse = await res.json()
    if (data.success && Array.isArray(data.data)) {
      return data.data
    }
    return []
  } catch (err) {
    console.error('[moduleService] Network error fetching modules:', err)
    return []
  }
}

/**
 * Toggles a module's active state via PATCH request (Administrator only)
 * 
 * @param moduleId Database ID of the target module
 * @param isActive New activation state (true = active, false = disabled)
 */
export async function toggleModuleActive(
  moduleId: number,
  isActive: boolean
): Promise<ModuleUpdateApiResponse> {
  try {
    const res = await apiFetch(`${API_BASE_URL}?id=${moduleId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ is_active: isActive })
    })

    const data: ModuleUpdateApiResponse = await res.json()
    return data
  } catch (err: any) {
    console.error('[moduleService] Error toggling module state:', err)
    return {
      success: false,
      message: 'Network error occurred while updating module state.',
      data: null,
      errors: { network: err.message }
    }
  }
}
