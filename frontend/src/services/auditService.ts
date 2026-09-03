// frontend/src/services/auditService.ts
// Native Fetch Client for 6IS Core Audit Logs API (Phase 4)

import type { AuditApiResponse, AuditFilterParams } from '../types/audit'
import { getStoredCsrfToken } from './authService'

const BASE_URL = '/backend/api/core/audit/index.php'

/**
 * Fetches paginated, filtered audit logs from the Core Audit API.
 * 
 * @param filters AuditFilterParams filter parameters
 * @returns Promise<AuditApiResponse>
 */
export async function fetchAuditLogs(filters: AuditFilterParams = {}): Promise<AuditApiResponse> {
  const params = new URLSearchParams()

  if (filters.page) params.append('page', filters.page.toString())
  if (filters.limit) params.append('limit', filters.limit.toString())
  if (filters.date_from) params.append('date_from', filters.date_from)
  if (filters.date_to) params.append('date_to', filters.date_to)
  if (filters.user_id) params.append('user_id', filters.user_id.toString())
  if (filters.module_key) params.append('module_key', filters.module_key)
  if (filters.action) params.append('action', filters.action)
  if (filters.entity_type) params.append('entity_type', filters.entity_type)
  if (filters.entity_id) params.append('entity_id', filters.entity_id)
  if (filters.search) params.append('search', filters.search)

  const url = `${BASE_URL}?${params.toString()}`

  const headers: Record<string, string> = {
    'Accept': 'application/json'
  }

  const csrf = getStoredCsrfToken()
  if (csrf) {
    headers['X-CSRF-Token'] = csrf
  }

  const response = await fetch(url, {
    method: 'GET',
    headers,
    credentials: 'include'
  })

  if (!response.ok) {
    let errorMsg = `HTTP Error ${response.status}`
    try {
      const errJson = await response.json()
      if (errJson.message) errorMsg = errJson.message
    } catch {
      // Ignore body parse error on non-JSON response
    }
    throw new Error(errorMsg)
  }

  return response.json()
}
