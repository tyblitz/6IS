// tests/unit/officeService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchOffices,
  fetchOffice,
  createOffice,
  updateOffice,
  toggleOfficeActive,
  deleteOffice
} from '@/services/officeService'
import type { Office } from '@/types/office'

const mockOffices: Office[] = [
  {
    id: 1,
    organization_id: 1,
    name: 'Office of the Assistant Chief of Staff for Personnel, G1',
    code: 'OG1',
    description: 'Personnel branch',
    address: 'HQ Building',
    contact_number: '1234',
    email: 'og1@example.com',
    is_active: 1,
    user_count: 2
  },
  {
    id: 2,
    organization_id: 1,
    name: 'Office of the Assistant Chief of Staff for Intelligence, G2',
    code: 'OG2',
    description: 'Intelligence branch',
    address: 'HQ Building',
    contact_number: '5678',
    email: 'og2@example.com',
    is_active: 0,
    user_count: 0
  }
]

describe('officeService Frontend API Communication', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('fetchOffices() fetches offices list and respects query parameters', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Offices retrieved.',
        data: mockOffices
      })
    })
    global.fetch = fetchMock

    const offices = await fetchOffices(true, 'G1')
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/offices/index.php?active_only=1&search=G1')
    expect(options.credentials).toBe('include')
    expect(offices).toHaveLength(2)
    expect(offices[0].code).toBe('OG1')
  })

  it('fetchOffice(id) fetches a single office by ID', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Office retrieved.',
        data: mockOffices[0]
      })
    })
    global.fetch = fetchMock

    const office = await fetchOffice(1)
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/offices/index.php?id=1')
    expect(office?.code).toBe('OG1')
  })

  it('createOffice() sends POST request with payload', async () => {
    const newOffice: Office = {
      id: 3,
      organization_id: 1,
      name: 'Information Communications Technology Office',
      code: 'ICTO',
      is_active: 1,
      user_count: 0
    }

    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Office created.',
        data: newOffice
      })
    })
    global.fetch = fetchMock

    const res = await createOffice({
      name: 'Information Communications Technology Office',
      code: 'ICTO'
    })

    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/offices/index.php')
    expect(options.method).toBe('POST')
    expect(JSON.parse(options.body)).toEqual({
      name: 'Information Communications Technology Office',
      code: 'ICTO'
    })
    expect(res.success).toBe(true)
    expect(res.data?.code).toBe('ICTO')
  })

  it('updateOffice() sends PATCH request with payload', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Office updated.',
        data: { ...mockOffices[0], name: 'Updated Name' }
      })
    })
    global.fetch = fetchMock

    const res = await updateOffice({
      id: 1,
      name: 'Updated Name'
    })

    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/offices/index.php')
    expect(options.method).toBe('PATCH')
    expect(res.success).toBe(true)
  })

  it('toggleOfficeActive() inverts the office active status', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Office updated.'
      })
    })
    global.fetch = fetchMock

    await toggleOfficeActive(1, 1) // currently active -> set to 0
    const [, options] = fetchMock.mock.calls[0]
    expect(JSON.parse(options.body)).toEqual({
      id: 1,
      is_active: 0
    })
  })

  it('deleteOffice() sends DELETE request with id', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Office deleted.'
      })
    })
    global.fetch = fetchMock

    const res = await deleteOffice(2)
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/offices/index.php')
    expect(options.method).toBe('DELETE')
    expect(JSON.parse(options.body)).toEqual({ id: 2 })
    expect(res.success).toBe(true)
  })
})
