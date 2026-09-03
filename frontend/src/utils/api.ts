// frontend/src/utils/api.ts
// Native Fetch Wrapper for 6IS Platform with Automatic CSRF & Session Headers

import { getStoredCsrfToken } from '../services/authService'

/**
 * Standardized fetch client wrapper:
 * - Ensures credentials: 'include' for session cookie transport
 * - Automatically injects 'X-CSRF-Token' header on mutating methods (POST, PUT, PATCH, DELETE)
 * - Sets default Accept: 'application/json'
 * 
 * @param url Request URL
 * @param options Standard RequestInit options
 * @returns Promise<Response>
 */
export async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
  const method = (options.method || 'GET').toUpperCase()
  const headers = new Headers(options.headers || {})

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    const token = getStoredCsrfToken()
    if (token && !headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', token)
    }
  }

  return fetch(url, {
    ...options,
    headers,
    credentials: 'include'
  })
}
