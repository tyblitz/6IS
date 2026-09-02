// tests/unit/usePermissions.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { usePermissions } from '@/composables/usePermissions'
import * as authService from '@/services/authService'
import { ref } from 'vue'
import type { AuthUser } from '@/types/auth'

describe('usePermissions Composable (RBAC)', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('fails closed when activeUser is null (unauthenticated)', () => {
    vi.spyOn(authService, 'activeUser', 'get').mockReturnValue(ref<AuthUser | null>(null))

    const { hasPermission, can, isPermitted, isAdmin, userPermissions } = usePermissions()

    expect(userPermissions.value).toEqual([])
    expect(hasPermission('inventory', 'view')).toBe(false)
    expect(can('roles', 'edit')).toBe(false)
    expect(isPermitted('users.create')).toBe(false)
    expect(isAdmin.value).toBe(false)
  })

  it('fails closed when activeUser has empty permissions array', () => {
    const mockUser: AuthUser = {
      id: 5,
      username: 'restricted_user',
      role: 'Restricted',
      role_id: 10,
      permissions: []
    }
    vi.spyOn(authService, 'activeUser', 'get').mockReturnValue(ref<AuthUser | null>(mockUser))

    const { hasPermission, can, isPermitted, isAdmin } = usePermissions()

    expect(hasPermission('inventory', 'view')).toBe(false)
    expect(can('communications', 'view')).toBe(false)
    expect(isPermitted('dashboard.view')).toBe(false)
    expect(isAdmin.value).toBe(false)
  })

  it('correctly resolves granted permissions and handles case-insensitivity', () => {
    const mockUser: AuthUser = {
      id: 2,
      username: 'user01',
      role: 'User',
      role_id: 2,
      permissions: [
        'inventory.view',
        'communications.view',
        'calendar.view',
        'accomplishments.view',
        'dashboard.view'
      ]
    }
    vi.spyOn(authService, 'activeUser', 'get').mockReturnValue(ref<AuthUser | null>(mockUser))

    const { hasPermission, can, isPermitted, isAdmin } = usePermissions()

    // Granted permissions
    expect(hasPermission('inventory', 'view')).toBe(true)
    expect(hasPermission('INVENTORY', 'VIEW')).toBe(true)
    expect(can('communications', 'view')).toBe(true)
    expect(isPermitted('calendar.view')).toBe(true)
    expect(isAdmin.value).toBe(false)

    // Denied permissions
    expect(hasPermission('inventory', 'create')).toBe(false)
    expect(hasPermission('inventory', 'edit')).toBe(false)
    expect(hasPermission('inventory', 'delete')).toBe(false)
    expect(hasPermission('inventory', 'configure')).toBe(false)
    expect(can('roles', 'view')).toBe(false)
    expect(isPermitted('users.create')).toBe(false)
  })

  it('correctly identifies Administrator identity and permissions', () => {
    const mockAdmin: AuthUser = {
      id: 1,
      username: 'admin01',
      role: 'Administrator',
      role_id: 1,
      permissions: [
        'roles.view',
        'roles.create',
        'roles.edit',
        'roles.delete',
        'roles.configure',
        'inventory.view',
        'inventory.create',
        'inventory.configure'
      ]
    }
    vi.spyOn(authService, 'activeUser', 'get').mockReturnValue(ref<AuthUser | null>(mockAdmin))

    const { hasPermission, can, isPermitted, isAdmin } = usePermissions()

    expect(isAdmin.value).toBe(true)
    expect(hasPermission('roles', 'configure')).toBe(true)
    expect(can('roles', 'delete')).toBe(true)
    expect(isPermitted('inventory.configure')).toBe(true)
  })

  it('respects Configure Independence: configure permission does not grant create/edit/delete', () => {
    const mockConfigUser: AuthUser = {
      id: 3,
      username: 'config_auditor',
      role: 'Auditor',
      role_id: 4,
      permissions: [
        'inventory.view',
        'inventory.configure'
      ]
    }
    vi.spyOn(authService, 'activeUser', 'get').mockReturnValue(ref<AuthUser | null>(mockConfigUser))

    const { hasPermission, can } = usePermissions()

    expect(hasPermission('inventory', 'configure')).toBe(true)
    expect(can('inventory', 'view')).toBe(true)
    expect(can('inventory', 'create')).toBe(false)
    expect(can('inventory', 'edit')).toBe(false)
    expect(can('inventory', 'delete')).toBe(false)
  })
})
