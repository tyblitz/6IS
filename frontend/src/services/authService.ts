// frontend/src/services/authService.ts
// Frontend Service for 6IS Authentication Foundation (Phase 4 Hardened)

import { ref, computed } from 'vue'
import type {
  AuthUser,
  LoginPayload,
  LoginResponse,
  CurrentUserResponse
} from '../types/auth'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/auth/index.php`
  }
  return 'http://localhost/6IS/backend/api/auth/index.php'
}

const API_BASE_URL = resolveApiUrl()

// Reactive state for authenticated user identity and active CSRF token
const currentUser = ref<AuthUser | null>(null)
const csrfToken = ref<string | null>(null)
const isInitialized = ref(false)

export const isAuthenticated = computed(() => currentUser.value !== null)
export const activeUser = computed(() => currentUser.value)

export function getStoredCsrfToken(): string | null {
  return csrfToken.value
}

export function setStoredCsrfToken(token: string | null): void {
  csrfToken.value = token
}

/**
 * Authenticates user credentials with the PHP backend
 */
export async function login(payload: LoginPayload): Promise<LoginResponse> {
  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json'
    }
    if (csrfToken.value) {
      headers['X-CSRF-Token'] = csrfToken.value
    }

    const res = await fetch(API_BASE_URL, {
      method: 'POST',
      headers,
      credentials: 'include',
      body: JSON.stringify(payload)
    })

    const data: LoginResponse = await res.json()
    if (data.csrf_token) {
      csrfToken.value = data.csrf_token
    }
    if (data.success && data.user) {
      currentUser.value = data.user
    }
    return data
  } catch (err: any) {
    console.error('Auth login fetch error:', err)
    return {
      success: false,
      message: 'Network error occurred while connecting to authentication server.',
      user: null,
      errors: { network: err.message }
    }
  }
}

/**
 * Checks server for valid authenticated PHP session and bootstraps CSRF token
 */
export async function fetchCurrentUser(): Promise<AuthUser | null> {
  try {
    const res = await fetch(API_BASE_URL, {
      method: 'GET',
      credentials: 'include'
    })

    const data: CurrentUserResponse = await res.json()
    if (data.csrf_token) {
      csrfToken.value = data.csrf_token
    }
    if (data.authenticated && data.user) {
      currentUser.value = data.user
    } else {
      currentUser.value = null
    }
    isInitialized.value = true
    return currentUser.value
  } catch (err) {
    console.error('Auth fetchCurrentUser error:', err)
    currentUser.value = null
    isInitialized.value = true
    return null
  }
}

/**
 * Destroys backend session and clears frontend user state
 */
export async function logout(): Promise<boolean> {
  try {
    const headers: Record<string, string> = {}
    if (csrfToken.value) {
      headers['X-CSRF-Token'] = csrfToken.value
    }

    await fetch(`${API_BASE_URL}?action=logout`, {
      method: 'POST',
      headers,
      credentials: 'include'
    })
  } catch (err) {
    // Ignore network error during logout
  } finally {
    currentUser.value = null
    csrfToken.value = null
  }
  return true
}

export function getCurrentUserSync(): AuthUser | null {
  return currentUser.value
}
