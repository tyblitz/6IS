// tests/unit/moduleService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { fetchModules, toggleModuleActive } from '@/services/moduleService'
import { useModules, isModuleActive } from '@/composables/useModules'
import type { SystemModule } from '@/types/module'

const mockModules: SystemModule[] = [
  {
    id: 1,
    module_key: 'dashboard',
    name: 'Dashboard',
    description: 'Central overview hub',
    icon: 'homeOutline',
    route: '/home',
    is_core: true,
    is_active: true,
    sort_order: 1,
    version: '0.1.0'
  },
  {
    id: 2,
    module_key: 'inventory',
    name: 'Inventory',
    description: 'Equipment readiness tracking',
    icon: 'cubeOutline',
    route: '/inventory',
    is_core: false,
    is_active: true,
    sort_order: 2,
    version: '0.1.0'
  },
  {
    id: 3,
    module_key: 'performance',
    name: 'Performance',
    description: 'KPI metrics module',
    icon: 'speedometerOutline',
    route: null,
    is_core: false,
    is_active: false,
    sort_order: 6,
    version: null
  },
  {
    id: 4,
    module_key: 'administrator',
    name: 'Administrator',
    description: 'System Administration',
    icon: 'settingsOutline',
    route: '/administrator',
    is_core: true,
    is_active: true,
    sort_order: 8,
    version: '0.1.0'
  }
]

describe('Core Module Registry Frontend Services & Composables', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('fetchModules() calls Core API with credentials: include', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Modules retrieved successfully.',
        data: mockModules
      })
    })
    global.fetch = fetchMock

    const result = await fetchModules()
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/modules/index.php')
    expect(options.credentials).toBe('include')
    expect(result).toHaveLength(4)
    expect(result[0].module_key).toBe('dashboard')
  })

  it('toggleModuleActive() sends PATCH with is_active boolean body', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: "Module 'Inventory' deactivated successfully.",
        data: { ...mockModules[1], is_active: false }
      })
    })
    global.fetch = fetchMock

    const res = await toggleModuleActive(2, false)
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('?id=2')
    expect(options.method).toBe('PATCH')
    expect(options.credentials).toBe('include')
    expect(JSON.parse(options.body)).toEqual({ is_active: false })
    expect(res.success).toBe(true)
    expect(res.data?.is_active).toBe(false)
  })

  it('useModules composable correctly resolves core and business module states', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Modules retrieved successfully.',
        data: mockModules
      })
    })

    const { loadModules, isEnabled } = useModules()
    await loadModules(true)

    // Core modules always true
    expect(isEnabled('dashboard')).toBe(true)
    expect(isEnabled('administrator')).toBe(true)
    expect(isEnabled('home')).toBe(true)

    // Active business module
    expect(isEnabled('inventory')).toBe(true)
    // Feature subpages map to inventory
    expect(isEnabled('equipment')).toBe(true)
    expect(isEnabled('jrrs')).toBe(true)

    // Inactive module
    expect(isEnabled('performance')).toBe(false)
  })

  it('isModuleActive helper function returns expected activation status', () => {
    expect(isModuleActive('dashboard')).toBe(true)
    expect(isModuleActive('administrator')).toBe(true)
    expect(isModuleActive('performance')).toBe(false)
  })
})
