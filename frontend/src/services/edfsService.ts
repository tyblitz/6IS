// frontend/src/services/edfsService.ts
// Service layer for interacting with the backend EDFS API

import type {
  EdfsAccount,
  EdfsListResponse,
  EdfsAccountFormData
} from '../types/edfs'
import { apiFetch, resolveApiUrl } from '../utils/api'

const API_URL = resolveApiUrl('edfs/index.php')

export interface EdfsFilterParams {
  search?: string
  office?: string
  status?: string
  has_personnel?: string
  page?: number
  per_page?: number
}

export async function fetchEdfsAccounts(params: EdfsFilterParams = {}): Promise<EdfsListResponse> {
  const query = new URLSearchParams()
  if (params.search) query.append('search', params.search)
  if (params.office) query.append('office', params.office)
  if (params.status) query.append('status', params.status)
  if (params.has_personnel !== undefined && params.has_personnel !== '') {
    query.append('has_personnel', params.has_personnel)
  }
  if (params.page) query.append('page', params.page.toString())
  if (params.per_page) query.append('per_page', params.per_page.toString())

  const url = `${API_URL}${query.toString() ? '?' + query.toString() : ''}`
  const response = await apiFetch(url, { method: 'GET' })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to fetch EDFS accounts.')
  }

  return result.data
}

export async function fetchEdfsAccountById(id: number): Promise<EdfsAccount> {
  const url = `${API_URL}?id=${id}`
  const response = await apiFetch(url, { method: 'GET' })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to fetch EDFS account.')
  }

  return result.data
}

export async function createEdfsAccount(payload: EdfsAccountFormData): Promise<{ id: number }> {
  const response = await apiFetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  const result = await response.json()

  if (!result.success) {
    const errorMsg = result.errors ? Object.values(result.errors).join(', ') : result.message
    throw new Error(errorMsg || 'Failed to create EDFS account.')
  }

  return result.data
}

export async function updateEdfsAccount(payload: EdfsAccountFormData): Promise<void> {
  if (!payload.id) throw new Error('Account ID is required for update.')

  const response = await apiFetch(API_URL, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  const result = await response.json()

  if (!result.success) {
    const errorMsg = result.errors ? Object.values(result.errors).join(', ') : result.message
    throw new Error(errorMsg || 'Failed to update EDFS account.')
  }
}

export async function deleteEdfsAccount(id: number): Promise<void> {
  const response = await apiFetch(`${API_URL}?id=${id}`, {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  })
  const result = await response.json()

  if (!result.success) {
    throw new Error(result.message || 'Failed to delete EDFS account.')
  }
}
