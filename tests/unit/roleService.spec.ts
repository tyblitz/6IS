// tests/unit/roleService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchRoles,
  fetchRole,
  createRole,
  updateRolePermissions,
  deleteRole,
  fetchPermissions
} from '@/services/roleService'
import type { Role } from '@/types/permission'

const mockRoles: Role[] = [
  {
    id: 1,
    name: 'Administrator',
    description: 'System Administrator',
    is_system: true,
    is_active: true,
    user_count: 1,
    permission_count: 33
  },
  {
    id: 2,
    name: 'User',
    description: 'Standard User',
    is_system: true,
    is_active: true,
    user_count: 3,
    permission_count: 5
  }
]

describe('roleService Frontend API Communication', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('fetchRoles() fetches roles list with credentials: include', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Roles retrieved.',
        data: mockRoles
      })
    })
    global.fetch = fetchMock

    const roles = await fetchRoles()
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/roles/index.php')
    expect(options.credentials).toBe('include')
    expect(roles).toHaveLength(2)
    expect(roles[0].name).toBe('Administrator')
  })

  it('fetchRole(id) fetches single role details with permission IDs', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: {
          ...mockRoles[1],
          permission_ids: [1, 5, 10]
        }
      })
    })
    global.fetch = fetchMock

    const role = await fetchRole(2)
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('?id=2')
    expect(role?.permission_ids).toEqual([1, 5, 10])
  })

  it('createRole() sends POST with JSON payload', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Role created successfully.',
        data: { id: 3, name: 'Technician', is_system: false, is_active: true }
      })
    })
    global.fetch = fetchMock

    const res = await createRole({ name: 'Technician', description: 'Hardware support', is_active: true })
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/roles/index.php')
    expect(options.method).toBe('POST')
    expect(JSON.parse(options.body)).toEqual({
      name: 'Technician',
      description: 'Hardware support',
      is_active: true
    })
    expect(res.success).toBe(true)
    expect(res.data?.id).toBe(3)
  })

  it('updateRolePermissions() sends PATCH with permission_ids array', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Role permissions updated successfully.'
      })
    })
    global.fetch = fetchMock

    const res = await updateRolePermissions(3, [10, 11, 12])
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('?id=3&action=permissions')
    expect(options.method).toBe('PATCH')
    expect(JSON.parse(options.body)).toEqual({ permission_ids: [10, 11, 12] })
    expect(res.success).toBe(true)
  })

  it('deleteRole() sends DELETE request for target role ID', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Role deleted successfully.'
      })
    })
    global.fetch = fetchMock

    const res = await deleteRole(3)
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('?id=3')
    expect(options.method).toBe('DELETE')
    expect(res.success).toBe(true)
  })

  it('fetchPermissions() retrieves list and grouped catalog', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: {
          list: [{ id: 1, module_key: 'inventory', permission_key: 'view', code: 'inventory.view' }],
          grouped: [{ module_key: 'inventory', module_name: 'Inventory', module_is_active: true, permissions: [] }]
        }
      })
    })
    global.fetch = fetchMock

    const { list, grouped } = await fetchPermissions()
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/permissions/index.php')
    expect(list).toHaveLength(1)
    expect(grouped).toHaveLength(1)
  })
})
