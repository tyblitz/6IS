// tests/unit/organizationService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchOrganization,
  updateOrganization
} from '@/services/organizationService'
import type { Organization } from '@/types/organization'

const mockOrg: Organization = {
  id: 1,
  name: '6th Infantry Division',
  short_name: '6ID',
  description: 'Kampilan Division',
  address: 'Camp Siongco, Awang, Datu Odin Sinsuat, Maguindanao del Norte',
  contact_number: '+63 917 123 4567',
  email: 'info@6id.mil.ph',
  is_active: 1
}

describe('organizationService Frontend API Communication', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('fetchOrganization() returns the primary organization profile', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Organization details retrieved.',
        data: mockOrg
      })
    })
    global.fetch = fetchMock

    const org = await fetchOrganization()
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/organization/index.php')
    expect(options.credentials).toBe('include')
    expect(org).not.toBeNull()
    expect(org?.name).toBe('6th Infantry Division')
    expect(org?.short_name).toBe('6ID')
  })

  it('updateOrganization() sends PATCH request with payload', async () => {
    const updated = { ...mockOrg, short_name: '6ID-HQ' }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Organization updated successfully.',
        data: updated
      })
    })
    global.fetch = fetchMock

    const res = await updateOrganization({
      name: '6th Infantry Division',
      short_name: '6ID-HQ'
    })

    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/organization/index.php')
    expect(options.method).toBe('PATCH')
    expect(options.credentials).toBe('include')
    expect(JSON.parse(options.body)).toEqual({
      name: '6th Infantry Division',
      short_name: '6ID-HQ'
    })
    expect(res.success).toBe(true)
    expect(res.data?.short_name).toBe('6ID-HQ')
  })
})
