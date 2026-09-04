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
  const headersObj: Record<string, string> = {}

  if (options.headers instanceof Headers) {
    options.headers.forEach((v, k) => { headersObj[k] = v })
  } else if (Array.isArray(options.headers)) {
    options.headers.forEach(([k, v]) => { headersObj[k] = v })
  } else if (options.headers) {
    Object.assign(headersObj, options.headers)
  }

  if (!headersObj['Accept'] && !headersObj['accept']) {
    headersObj['Accept'] = 'application/json'
  }

  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    const token = getStoredCsrfToken()
    if (token && !headersObj['X-CSRF-Token'] && !headersObj['x-csrf-token']) {
      headersObj['X-CSRF-Token'] = token
    }
  }

  return fetch(url, {
    ...options,
    headers: headersObj,
    credentials: 'include'
  })
}

/**
 * Resolves the full API URL for a given endpoint path in a deployment-neutral manner.
 * Supports:
 * 1. Explicit environment variable: VITE_API_BASE_URL (highest priority)
 * 2. Dynamic deployment prefix (root domain, custom subdirectory, or local XAMPP subpath)
 * 3. Fallback for headless / unit test / SSR environments
 * 
 * @param relativePath Endpoint path (e.g., 'accomplishments/index.php')
 * @returns Fully resolved API URL
 */
export function resolveApiUrl(relativePath: string): string {
  const cleanPath = relativePath.startsWith('/') ? relativePath.slice(1) : relativePath

  // 1. Explicit environment variable configuration
  if (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.VITE_API_BASE_URL) {
    const base = (import.meta.env.VITE_API_BASE_URL as string).replace(/\/+$/, '')
    return `${base}/${cleanPath}`
  }

  // 2. Dynamic Browser Location Resolution
  if (typeof window !== 'undefined') {
    const origin = window.location.origin || `${window.location.protocol}//${window.location.host}`
    
    // Determine subdirectory prefix if deployed under subfolder (e.g., /6IS or custom subfolder)
    let pathPrefix = ''
    if (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.BASE_URL && import.meta.env.BASE_URL !== '/') {
      pathPrefix = import.meta.env.BASE_URL.replace(/\/+$/, '')
    } else if (window.location.pathname.startsWith('/6IS')) {
      pathPrefix = '/6IS'
    }

    return `${origin}${pathPrefix}/backend/api/${cleanPath}`
  }

  // 3. Fallback for SSR / Node test execution
  return `http://localhost/backend/api/${cleanPath}`
}
