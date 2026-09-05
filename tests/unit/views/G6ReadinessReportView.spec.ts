// tests/unit/views/G6ReadinessReportView.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import G6ReadinessReportView from '@/views/inventory/G6ReadinessReportView.vue'
import * as inventoryService from '@/services/inventoryService'
import type { G6ReadinessReport, ReportingPeriod } from '@/types/inventory'

describe('G6ReadinessReportView.vue — G6 Equipment Readiness Report', () => {
  const mockPeriods: ReportingPeriod[] = [
    { year_month: '2026-09', label: 'September 2026', is_current: true },
    { year_month: '2026-07', label: 'July 2026', is_current: false },
    { year_month: '2026-06', label: 'June 2026', is_current: false }
  ]

  const mockReportData: G6ReadinessReport = {
    period: '2026-09',
    period_label: 'September 2026',
    mode: 'current',
    has_snapshot: true,
    scope: {
      equipment_type_ids: [1, 2],
      description: 'Current dataset scope: ICT (1) and Communications (2)'
    },
    lines: [
      {
        equipment_subtype_id: 1,
        nomenclature: 'Desktop',
        equipment_type_id: 1,
        equipment_type_name: 'ICT',
        required: 25,
        operational: 4,
        repair: 1,
        ber: 1,
        on_hand: 6,
        deficit: 19,
        equipment_rating: 0.24,
        maintenance_rating: 0.6667,
        equipment_redcon: 'R4',
        maintenance_redcon: 'R3'
      },
      {
        equipment_subtype_id: 2,
        nomenclature: 'Printer',
        equipment_type_id: 1,
        equipment_type_name: 'ICT',
        required: 10,
        operational: 3,
        repair: 0,
        ber: 1,
        on_hand: 4,
        deficit: 6,
        equipment_rating: 0.4,
        maintenance_rating: 0.75,
        equipment_redcon: 'R4',
        maintenance_redcon: 'R2'
      },
      {
        equipment_subtype_id: 6,
        nomenclature: 'Laptop',
        equipment_type_id: 1,
        equipment_type_name: 'ICT',
        required: 15,
        operational: 4,
        repair: 1,
        ber: 0,
        on_hand: 5,
        deficit: 10,
        equipment_rating: 0.3333,
        maintenance_rating: 0.8,
        equipment_redcon: 'R4',
        maintenance_redcon: 'R2'
      },
      {
        equipment_subtype_id: 7,
        nomenclature: 'Network Switch',
        equipment_type_id: 1,
        equipment_type_name: 'ICT',
        required: 8,
        operational: 2,
        repair: 0,
        ber: 0,
        on_hand: 2,
        deficit: 6,
        equipment_rating: 0.25,
        maintenance_rating: 1.0,
        equipment_redcon: 'R4',
        maintenance_redcon: 'R1'
      },
      {
        equipment_subtype_id: 11,
        nomenclature: 'Public Address System',
        equipment_type_id: 2,
        equipment_type_name: 'Communications',
        required: 5,
        operational: 3,
        repair: 0,
        ber: 0,
        on_hand: 3,
        deficit: 2,
        equipment_rating: 0.6,
        maintenance_rating: 1.0,
        equipment_redcon: 'R3',
        maintenance_redcon: 'R1'
      }
    ],
    groups: [
      {
        group_id: 1,
        group_name: 'ICT',
        lines: [
          {
            equipment_subtype_id: 1,
            nomenclature: 'Desktop',
            equipment_type_id: 1,
            equipment_type_name: 'ICT',
            required: 25,
            operational: 4,
            repair: 1,
            ber: 1,
            on_hand: 6,
            deficit: 19,
            equipment_rating: 0.24,
            maintenance_rating: 0.6667,
            equipment_redcon: 'R4',
            maintenance_redcon: 'R3'
          }
        ],
        totals: {
          required: 58,
          operational: 13,
          repair: 2,
          ber: 2,
          on_hand: 17,
          deficit: 41
        },
        equipment_rating: 0.3058,
        maintenance_rating: 0.8042,
        equipment_redcon: 'R4',
        maintenance_redcon: 'R2'
      },
      {
        group_id: 2,
        group_name: 'Communications',
        lines: [
          {
            equipment_subtype_id: 11,
            nomenclature: 'Public Address System',
            equipment_type_id: 2,
            equipment_type_name: 'Communications',
            required: 5,
            operational: 3,
            repair: 0,
            ber: 0,
            on_hand: 3,
            deficit: 2,
            equipment_rating: 0.6,
            maintenance_rating: 1.0,
            equipment_redcon: 'R3',
            maintenance_redcon: 'R1'
          }
        ],
        totals: {
          required: 5,
          operational: 3,
          repair: 0,
          ber: 0,
          on_hand: 3,
          deficit: 2
        },
        equipment_rating: 0.6,
        maintenance_rating: 1.0,
        equipment_redcon: 'R3',
        maintenance_redcon: 'R1'
      }
    ],
    summary: {
      totals: {
        required: 63,
        operational: 16,
        repair: 2,
        ber: 2,
        on_hand: 20,
        deficit: 43
      },
      equipment_rating: 0.4529,
      maintenance_rating: 0.9021,
      equipment_redcon: 'R4',
      maintenance_redcon: 'R1'
    }
  }

  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(inventoryService, 'fetchReportingPeriods').mockResolvedValue({
      success: true,
      message: 'Reporting periods retrieved.',
      data: mockPeriods
    })
    vi.spyOn(inventoryService, 'fetchG6Readiness').mockResolvedValue({
      success: true,
      message: 'G6 Equipment Readiness Report retrieved.',
      data: mockReportData
    })
  })

  function mountView() {
    return mount(G6ReadinessReportView, {
      global: {
        stubs: {
          MainLayout: {
            template: '<div class="main-layout-stub"><slot /></div>'
          },
          'ion-icon': true,
          'ion-spinner': true
        }
      }
    })
  }

  // 1. Current readiness renders
  it('1. renders current readiness view on mount', async () => {
    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="report-content"]').exists()).toBe(true)
    expect(wrapper.find('h2').text()).toBe('G6 Equipment Readiness Report')
  })

  // 2. Overall equipment readiness displays API value
  it('2. displays overall equipment readiness API value and REDCON', async () => {
    const wrapper = mountView()
    await flushPromises()

    const kpiVal = wrapper.find('[data-testid="kpi-equipment-value"]')
    expect(kpiVal.text()).toBe('45.29%')

    const kpiRedcon = wrapper.find('[data-testid="kpi-equipment-redcon"]')
    expect(kpiRedcon.text()).toBe('R4')
  })

  // 3. Overall maintenance readiness displays API value
  it('3. displays overall maintenance readiness API value and REDCON', async () => {
    const wrapper = mountView()
    await flushPromises()

    const kpiVal = wrapper.find('[data-testid="kpi-maintenance-value"]')
    expect(kpiVal.text()).toBe('90.21%')

    const kpiRedcon = wrapper.find('[data-testid="kpi-maintenance-redcon"]')
    expect(kpiRedcon.text()).toBe('R1')
  })

  // 4. Group summaries render
  it('4. renders group summaries for ICT and Communications', async () => {
    const wrapper = mountView()
    await flushPromises()

    const groupTable = wrapper.find('[data-testid="group-summary-table"]')
    expect(groupTable.exists()).toBe(true)

    const row1 = wrapper.find('[data-testid="group-row-1"]')
    expect(row1.text()).toContain('ICT Equipment')
    expect(row1.text()).toContain('30.58%')
    expect(row1.text()).toContain('80.42%')

    const row2 = wrapper.find('[data-testid="group-row-2"]')
    expect(row2.text()).toContain('Communications Equipment')
    expect(row2.text()).toContain('60.00%')
    expect(row2.text()).toContain('100.00%')
  })

  // 5. Detailed lines render
  it('5. renders detailed equipment subtype lines in table', async () => {
    const wrapper = mountView()
    await flushPromises()

    const desktopRow = wrapper.find('[data-testid="line-row-1"]')
    expect(desktopRow.exists()).toBe(true)
    expect(desktopRow.text()).toContain('Desktop')
    expect(desktopRow.text()).toContain('25') // required
    expect(desktopRow.text()).toContain('6')  // on_hand
    expect(desktopRow.text()).toContain('24.00%')
    expect(desktopRow.text()).toContain('66.67%')
  })

  // 6. REDCON values render
  it('6. renders REDCON values with appropriate badge classes', async () => {
    const wrapper = mountView()
    await flushPromises()

    const r4Badge = wrapper.find('.redcon-r4')
    expect(r4Badge.exists()).toBe(true)
    expect(r4Badge.text()).toBe('R4')

    const r1Badge = wrapper.find('.redcon-r1')
    expect(r1Badge.exists()).toBe(true)
    expect(r1Badge.text()).toBe('R1')
  })

  // 7. NULL rating renders as N/A rather than 0%
  it('7. renders null ratings as "N/A" rather than 0%', async () => {
    const dataWithNulls: G6ReadinessReport = {
      ...mockReportData,
      summary: {
        totals: { required: 0, operational: 0, repair: 0, ber: 0, on_hand: 0, deficit: 0 },
        equipment_rating: null,
        maintenance_rating: null,
        equipment_redcon: 'R4',
        maintenance_redcon: 'R4'
      }
    }
    vi.spyOn(inventoryService, 'fetchG6Readiness').mockResolvedValue({
      success: true,
      message: 'Retrieved',
      data: dataWithNulls
    })

    const wrapper = mountView()
    await flushPromises()

    const eqVal = wrapper.find('[data-testid="kpi-equipment-value"]')
    expect(eqVal.text()).toBe('N/A')
    expect(eqVal.text()).not.toBe('0%')
    expect(eqVal.text()).not.toBe('0.00%')

    const maintVal = wrapper.find('[data-testid="kpi-maintenance-value"]')
    expect(maintVal.text()).toBe('N/A')
    expect(maintVal.text()).not.toBe('0%')
  })

  // 8. Historical period can be selected
  it('8. triggers data reload when selecting a historical period', async () => {
    const fetchSpy = vi.spyOn(inventoryService, 'fetchG6Readiness')
    const wrapper = mountView()
    await flushPromises()

    const select = wrapper.find<HTMLSelectElement>('[data-testid="period-select"]')
    await select.setValue('2026-07')

    expect(fetchSpy).toHaveBeenCalledWith('2026-07')
  })

  // 9. Missing snapshot displays the appropriate empty state
  it('9. displays missing snapshot alert when has_snapshot is false', async () => {
    vi.spyOn(inventoryService, 'fetchG6Readiness').mockResolvedValue({
      success: true,
      message: 'Retrieved',
      data: {
        period: '2026-08',
        period_label: 'August 2026',
        mode: 'historical',
        has_snapshot: false,
        message: 'No snapshot data recorded for period 2026-08.'
      }
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="missing-snapshot-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="missing-snapshot-state"]').text()).toContain('No snapshot data recorded for period 2026-08')
    expect(wrapper.find('[data-testid="report-content"]').exists()).toBe(false)
  })

  // 10. API error displays an error state
  it('10. displays error state when API call fails', async () => {
    vi.spyOn(inventoryService, 'fetchG6Readiness').mockResolvedValue({
      success: false,
      message: 'Database connection failed.',
      data: null as any
    })

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.find('[data-testid="error-state"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="error-state"]').text()).toContain('Database connection failed.')
  })

  // 11. Loading state works
  it('11. displays loading state before data resolves', () => {
    vi.spyOn(inventoryService, 'fetchG6Readiness').mockImplementation(() => new Promise(() => {}))

    const wrapper = mountView()
    expect(wrapper.find('[data-testid="loading-state"]').exists()).toBe(true)
  })

  // 12. Print controls are hidden during print and handlePrint calls window.print
  it('12. marks interactive controls with print-hide and invokes window.print', async () => {
    const printSpy = vi.spyOn(window, 'print').mockImplementation(() => {})
    const wrapper = mountView()
    await flushPromises()

    const headerBar = wrapper.find('.module-header-bar')
    expect(headerBar.classes()).toContain('print-hide')

    const printBtn = wrapper.find('[data-testid="btn-print"]')
    await printBtn.trigger('click')

    expect(printSpy).toHaveBeenCalled()
  })
})
