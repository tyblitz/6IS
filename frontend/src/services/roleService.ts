// frontend/src/services/roleService.ts
// Frontend Service for 6IS Core Roles & Permissions API Communications

import type { Role, RolePayload, Permission, GroupedModulePermissions } from '../types/permission'
import { apiFetch } from '../utils/api'

function resolveRolesApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/core/roles/index.php`
  }
  return 'http://localhost/6IS/backend/api/core/roles/index.php'
}

function resolvePermissionsApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/core/permissions/index.php`
  }
  return 'http://localhost/6IS/backend/api/core/permissions/index.php'
}

const ROLES_API_URL = resolveRolesApiUrl()
const PERMISSIONS_API_URL = resolvePermissionsApiUrl()

/**
 * Fetches all roles with assigned user counts and permission counts
 */
export async function fetchRoles(): Promise<Role[]> {
  try {
    const res = await apiFetch(ROLES_API_URL, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' }
    })

    if (!res.ok) {
      console.warn(`[roleService] fetchRoles returned status ${res.status}`)
      return []
    }

    const data = await res.json()
    if (data.success && Array.isArray(data.data)) {
      return data.data
    }
    return []
  } catch (err) {
    console.error('[roleService] Error fetching roles:', err)
    return []
  }
}

/**
 * Fetches single role by ID along with its assigned permission IDs
 */
export async function fetchRole(id: number): Promise<Role | null> {
  try {
    const res = await apiFetch(`${ROLES_API_URL}?id=${id}`, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' }
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
    console.error(`[roleService] Error fetching role ${id}:`, err)
    return null
  }
}

/**
 * Creates a new custom role
 */
export async function createRole(payload: RolePayload): Promise<{ success: boolean; message: string; data?: Role; errors?: any }> {
  try {
    const res = await apiFetch(ROLES_API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    return data
  } catch (err: any) {
    console.error('[roleService] Error creating role:', err)
    return {
      success: false,
      message: 'Network error occurred while creating role.',
      errors: { network: err.message }
    }
  }
}

/**
 * Updates role metadata (name, description, is_active)
 */
export async function updateRole(
  id: number,
  payload: Partial<RolePayload>
): Promise<{ success: boolean; message: string; data?: Role; errors?: any }> {
  try {
    const res = await apiFetch(`${ROLES_API_URL}?id=${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    return data
  } catch (err: any) {
    console.error(`[roleService] Error updating role ${id}:`, err)
    return {
      success: false,
      message: 'Network error occurred while updating role.',
      errors: { network: err.message }
    }
  }
}

/**
 * Replaces permission assignments for a role
 */
export async function updateRolePermissions(
  roleId: number,
  permissionIds: number[]
): Promise<{ success: boolean; message: string; data?: any; errors?: any }> {
  try {
    const res = await apiFetch(`${ROLES_API_URL}?id=${roleId}&action=permissions`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ permission_ids: permissionIds })
    })

    const data = await res.json()
    return data
  } catch (err: any) {
    console.error(`[roleService] Error updating role permissions for role ${roleId}:`, err)
    return {
      success: false,
      message: 'Network error occurred while updating permissions.',
      errors: { network: err.message }
    }
  }
}

/**
 * Deletes an unassigned custom role
 */
export async function deleteRole(id: number): Promise<{ success: boolean; message: string; errors?: any }> {
  try {
    const res = await apiFetch(`${ROLES_API_URL}?id=${id}`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' }
    })

    const data = await res.json()
    return data
  } catch (err: any) {
    console.error(`[roleService] Error deleting role ${id}:`, err)
    return {
      success: false,
      message: 'Network error occurred while deleting role.',
      errors: { network: err.message }
    }
  }
}

/**
 * Fetches system permissions catalog grouped by module
 */
export async function fetchPermissions(): Promise<{ list: Permission[]; grouped: GroupedModulePermissions[] }> {
  try {
    const res = await apiFetch(PERMISSIONS_API_URL, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' }
    })

    if (!res.ok) {
      console.warn(`[roleService] fetchPermissions returned status ${res.status}`)
      return { list: [], grouped: [] }
    }

    const data = await res.json()
    if (data.success && data.data) {
      return {
        list: Array.isArray(data.data.list) ? data.data.list : [],
        grouped: Array.isArray(data.data.grouped) ? data.data.grouped : []
      }
    }
    return { list: [], grouped: [] }
  } catch (err) {
    console.error('[roleService] Error fetching permissions catalog:', err)
    return { list: [], grouped: [] }
  }
}
