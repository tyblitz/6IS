// tests/unit/accomplishmentService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchAccomplishmentOptions,
  fetchDailyAccomplishments,
  fetchMonthlyAccomplishments,
  fetchQuarterlyAccomplishments,
  fetchAnnualAccomplishments,
  fetchCustomPeriodAccomplishments,
  createAccomplishment,
  updateAccomplishment,
  deleteAccomplishment
} from '@/services/accomplishmentService'
import { setStoredCsrfToken } from '@/services/authService'

describe('accomplishmentService API Client', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    setStoredCsrfToken('mock-csrf-token-xyz')
  })

  it('fetchAccomplishmentOptions() calls options endpoint and parses offices and categories', async () => {
    const mockRes = {
      success: true,
      message: 'Options loaded',
      data: {
        offices: [{ id: 1, office_name: 'ICT Office', office_code: 'ICT' }],
        categories: [{ id: 1, category_name: 'Operations', category_code: 'OPS' }]
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await fetchAccomplishmentOptions()
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('accomplishments/index.php?view=options')
    expect(options.credentials).toBe('include')
    expect(res.success).toBe(true)
    expect(res.data?.offices.length).toBe(1)
  })

  it('fetchDailyAccomplishments() constructs query parameters with filters', async () => {
    const mockRes = {
      success: true,
      message: 'Records loaded',
      data: {
        records: [
          { id: 10, office_id: 1, category_id: 1, date: '2026-08-27', description: 'Network maintenance' }
        ]
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await fetchDailyAccomplishments('2026-08-27', 1, 'maintenance', 2)
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('view=daily')
    expect(url).toContain('date=2026-08-27')
    expect(url).toContain('office_id=1')
    expect(url).toContain('search=maintenance')
    expect(url).toContain('category_id=2')
    expect(res.success).toBe(true)
    expect(res.data?.records.length).toBe(1)
  })

  it('fetchMonthlyAccomplishments() queries monthly aggregation rollup', async () => {
    const mockRes = {
      success: true,
      message: 'Monthly summary loaded',
      data: {
        accomplishments_by_category: [{ category_id: 1, category_name: 'OPS', count: 5 }],
        outgoing_comms_by_category: [],
        clearances_by_purpose: []
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await fetchMonthlyAccomplishments(2026, 8)
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('view=monthly')
    expect(url).toContain('year=2026')
    expect(url).toContain('month=8')
    expect(res.success).toBe(true)
    expect(res.data?.accomplishments_by_category.length).toBe(1)
  })

  it('fetchQuarterlyAccomplishments() queries quarterly aggregation rollup', async () => {
    const mockRes = {
      success: true,
      message: 'Quarterly summary loaded',
      data: {
        accomplishments_by_category: [],
        outgoing_comms_by_category: [],
        clearances_by_purpose: []
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await fetchQuarterlyAccomplishments(2026, 3)
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('view=quarterly')
    expect(url).toContain('year=2026')
    expect(url).toContain('quarter=3')
    expect(res.success).toBe(true)
  })

  it('fetchAnnualAccomplishments() queries annual aggregation rollup', async () => {
    const mockRes = {
      success: true,
      message: 'Annual summary loaded',
      data: {
        accomplishments_by_category: [],
        outgoing_comms_by_category: [],
        clearances_by_purpose: []
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await fetchAnnualAccomplishments(2026)
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('view=annual')
    expect(url).toContain('year=2026')
    expect(res.success).toBe(true)
  })

  it('fetchCustomPeriodAccomplishments() queries custom date range rollup', async () => {
    const mockRes = {
      success: true,
      message: 'Custom period summary loaded',
      data: {
        accomplishments_by_category: [],
        outgoing_comms_by_category: [],
        clearances_by_purpose: []
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await fetchCustomPeriodAccomplishments('2026-08-01', '2026-08-31')
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('view=custom')
    expect(url).toContain('start_date=2026-08-01')
    expect(url).toContain('end_date=2026-08-31')
    expect(res.success).toBe(true)
  })

  it('createAccomplishment() sends POST request with payload and headers', async () => {
    const mockRes = {
      success: true,
      message: 'Accomplishment created successfully.',
      data: { id: 101 }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const payload = {
      office_id: 1,
      category_id: 2,
      date: '2026-08-27',
      description: 'New activity entry',
      remarks: 'Completed on schedule'
    }

    const res = await createAccomplishment(payload)
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('accomplishments/index.php')
    expect(options.method).toBe('POST')
    expect(options.headers['Content-Type']).toBe('application/json')
    expect(JSON.parse(options.body)).toEqual(payload)
    expect(res.success).toBe(true)
    expect(res.data?.id).toBe(101)
  })

  it('updateAccomplishment() sends PUT request with updated data', async () => {
    const mockRes = {
      success: true,
      message: 'Accomplishment updated successfully.',
      data: { id: 101 }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const payload = {
      office_id: 1,
      category_id: 2,
      date: '2026-08-27',
      description: 'Updated activity entry',
      remarks: 'Updated remarks'
    }

    const res = await updateAccomplishment(101, payload)
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('accomplishments/index.php?id=101')
    expect(options.method).toBe('PUT')
    expect(res.success).toBe(true)
  })

  it('deleteAccomplishment() sends DELETE request with record ID', async () => {
    const mockRes = {
      success: true,
      message: 'Accomplishment deleted successfully.',
      data: { id: 101 }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockRes
    })
    global.fetch = fetchMock

    const res = await deleteAccomplishment(101)
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('accomplishments/index.php?id=101')
    expect(options.method).toBe('DELETE')
    expect(res.success).toBe(true)
  })
})
