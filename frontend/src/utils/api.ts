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

/**
 * Resolves the full API URL for a given endpoint path.
 * Supports:
 * 1. Explicit environment variable: VITE_API_BASE_URL
 * 2. Window location derivation with '/6IS/backend/api' for standard deployment
 * 3. Default fallback for SSR / unit testing environments
 * 
 * @param relativePath Endpoint path (e.g., 'accomplishments/index.php')
 * @returns Fully resolved API URL
 */
export function resolveApiUrl(relativePath: string): string {
  const cleanPath = relativePath.startsWith('/') ? relativePath.slice(1) : relativePath
  
  if (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.VITE_API_BASE_URL) {
    const base = (import.meta.env.VITE_API_BASE_URL as string).replace(/\/+$/, '')
    return `${base}/${cleanPath}`
  }

  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/${cleanPath}`
  }

  return `http://localhost/6IS/backend/api/${cleanPath}`
}
