// tests/unit/budgetService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  fetchBudgetOptions,
  fetchBudgetSchedules,
  fetchBudgetDisbursements,
  createDisbursement,
  createSchedule,
  updateDisbursement,
  updateSchedule,
  deleteBudgetSchedule,
  deleteBudgetDisbursement
} from '@/services/budgetService'

describe('budgetService Frontend API Communication', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('fetchBudgetOptions() fetches options with credentials: include', async () => {
    const mockOptions = {
      offices: [{ id: 1, office_name: 'OG1', office_code: 'OG1', office_abbv: 'OG1' }],
      schedules: [{ id: 1, office_id: 1, fiscal_year: 2026, paps: 'R & M of ICT Equipments', allocated_amount: 6500, office_code: 'OG1', office_name: 'OG1' }]
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Options fetched.',
        data: mockOptions
      })
    })
    global.fetch = fetchMock

    const options = await fetchBudgetOptions()
    expect(fetchMock).toHaveBeenCalled()
    const [url, opts] = fetchMock.mock.calls[0]
    expect(url).toContain('backend/api/budget/index.php?view=options')
    expect(opts.credentials).toBe('include')
    expect(options.offices).toHaveLength(1)
    expect(options.schedules).toHaveLength(1)
  })

  it('fetchBudgetSchedules() requests view=schedule', async () => {
    const mockData = {
      schedules: [],
      metrics: {
        mooe_total: 667875.00,
        overall_disbursed_total: 148408.00,
        unallocated_disbursed_total: 0,
        overall_remaining_balance: 519467.00,
        total_scheduled_releases: 38,
        total_schedule_items: 22
      }
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Schedules fetched.',
        data: mockData
      })
    })
    global.fetch = fetchMock

    const res = await fetchBudgetSchedules()
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('backend/api/budget/index.php?view=schedule')
    expect(res.metrics.mooe_total).toBe(667875.00)
    expect(res.metrics.overall_remaining_balance).toBe(519467.00)
  })

  it('fetchBudgetDisbursements() requests view=disbursements with filters', async () => {
    const mockData = {
      disbursements: [],
      total_disbursed: 148408.00,
      total_count: 14
    }
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Disbursements fetched.',
        data: mockData
      })
    })
    global.fetch = fetchMock

    const res = await fetchBudgetDisbursements(5, 'OG6')
    expect(fetchMock).toHaveBeenCalled()
    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('view=disbursements')
    expect(url).toContain('office_id=5')
    expect(url).toContain('search=OG6')
    expect(res.total_disbursed).toBe(148408.00)
  })

  it('createDisbursement() posts new scheduled disbursement', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Disbursement recorded.',
        data: { id: 101 }
      })
    })
    global.fetch = fetchMock

    const res = await createDisbursement({
      action: 'disbursement',
      schedule_id: 1,
      disbursement_date: '2026-06-15',
      particulars: 'R/M - ICT - Q1 OG6',
      amount_disbursed: 4500.00
    })
    expect(fetchMock).toHaveBeenCalled()
    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.method).toBe('POST')
    const body = JSON.parse(opts.body)
    expect(body.action).toBe('disbursement')
    expect(body.schedule_id).toBe(1)
    expect(body.amount_disbursed).toBe(4500.00)
    expect(res.id).toBe(101)
  })

  it('createSchedule() posts new schedule allocation', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Schedule created.',
        data: { id: 50 }
      })
    })
    global.fetch = fetchMock

    const res = await createSchedule({
      action: 'schedule',
      fiscal_year: 2026,
      office_id: 1,
      paps: 'R & M of ICT Equipments',
      allocated_amount: 6500.00,
      jan: 0, feb: 0, mar: 0, apr: 1, may: 0, jun: 0,
      jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0
    })
    expect(fetchMock).toHaveBeenCalled()
    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.method).toBe('POST')
    const body = JSON.parse(opts.body)
    expect(body.action).toBe('schedule')
    expect(body.allocated_amount).toBe(6500.00)
    expect(body.apr).toBe(1)
    expect(res.id).toBe(50)
  })

  it('updateDisbursement() sends PUT request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Disbursement updated.'
      })
    })
    global.fetch = fetchMock

    await updateDisbursement({
      id: 1,
      type: 'disbursement',
      amount_disbursed: 4800.00
    })
    expect(fetchMock).toHaveBeenCalled()
    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.method).toBe('PUT')
  })

  it('updateSchedule() sends PUT request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Schedule updated.'
      })
    })
    global.fetch = fetchMock

    await updateSchedule({
      id: 1,
      type: 'schedule',
      allocated_amount: 7000.00
    })
    expect(fetchMock).toHaveBeenCalled()
    const [, opts] = fetchMock.mock.calls[0]
    expect(opts.method).toBe('PUT')
  })

  it('deleteBudgetSchedule(id) sends DELETE request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Schedule deleted.'
      })
    })
    global.fetch = fetchMock

    await deleteBudgetSchedule(5)
    expect(fetchMock).toHaveBeenCalled()
    const [url, opts] = fetchMock.mock.calls[0]
    expect(url).toContain('id=5&type=schedule')
    expect(opts.method).toBe('DELETE')
  })

  it('deleteBudgetDisbursement(id) sends DELETE request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        message: 'Disbursement deleted.'
      })
    })
    global.fetch = fetchMock

    await deleteBudgetDisbursement(12)
    expect(fetchMock).toHaveBeenCalled()
    const [url, opts] = fetchMock.mock.calls[0]
    expect(url).toContain('id=12&type=disbursement')
    expect(opts.method).toBe('DELETE')
  })
})
