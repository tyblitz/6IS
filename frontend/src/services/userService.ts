// frontend/src/services/userService.ts
// Frontend Service for 6IS User Management

import type { UserAccount, CreateUserPayload, UpdateUserPayload } from '../types/user'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/users/index.php`
  }
  return 'http://localhost/6IS/backend/api/users/index.php'
}

const API_BASE_URL = resolveApiUrl()

export interface UserApiResponse<T> {
  success: boolean
  message: string
  data: T
  errors?: Record<string, string> | null
}

/**
 * Fetches all user accounts (Administrator only)
 */
export async function fetchUsers(): Promise<UserApiResponse<UserAccount[]>> {
  try {
    const res = await fetch(API_BASE_URL, {
      method: 'GET',
      credentials: 'include'
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch user accounts.',
      data: [],
      errors: { network: err.message }
    }
  }
}

/**
 * Creates a new user account (Administrator only)
 */
export async function createUser(payload: CreateUserPayload): Promise<UserApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=create`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to create user account.',
      data: null,
      errors: { network: err.message }
    }
  }
}

/**
 * Updates an existing user account (Administrator only)
 */
export async function updateUser(payload: UpdateUserPayload): Promise<UserApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=update`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to update user account.',
      data: null,
      errors: { network: err.message }
    }
  }
}

/**
 * Toggles user active state (Activate / Deactivate)
 */
export async function toggleUserActive(id: number, isActive: number): Promise<UserApiResponse<null>> {
  try {
    const res = await fetch(`${API_BASE_URL}?action=toggle_active`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id, is_active: isActive })
    })
    return await res.json()
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to toggle user active state.',
      data: null,
      errors: { network: err.message }
    }
  }
}
