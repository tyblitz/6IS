<template>
  <MainLayout title="G6 Equipment Readiness Report">
    <div class="readiness-report-container">
      
      <!-- Top Header & Action Controls (Hidden on Print) -->
      <div class="module-header-bar print-hide">
        <div class="header-titles">
          <h2>G6 Equipment Readiness Report</h2>
          <p class="subtitle">
            Reporting Period: <strong>{{ activePeriodLabel }}</strong> | G6 Responsibility: <strong>ICT and Communications Equipment</strong>
          </p>
        </div>

        <div class="header-controls">
          <!-- Dynamic Period Selector -->
          <div class="period-selector-box">
            <label for="periodSelect" class="period-label">Period:</label>
            <div class="select-wrapper">
              <ion-icon :icon="calendarOutline" class="calendar-icon" />
              <select
                id="periodSelect"
                v-model="selectedPeriod"
                @change="handlePeriodChange"
                class="period-select"
                data-testid="period-select"
              >
                <option
                  v-for="p in periods"
                  :key="p.year_month"
                  :value="p.year_month"
                >
                  {{ p.label }} {{ p.is_current ? '(Current)' : '' }}
                </option>
              </select>
            </div>
          </div>

          <!-- Print Action Button -->
          <button class="btn-print" type="button" @click="handlePrint" data-testid="btn-print">
            <ion-icon :icon="printOutline" />
            <span>Print Report</span>
          </button>
        </div>
      </div>

      <!-- Printable Document Header (Visible only in print) -->
      <div class="printable-header print-only">
        <div class="print-org-title">6IS INTEGRATED INFORMATION SYSTEM</div>
        <div class="print-report-title">G6 EQUIPMENT READINESS REPORT</div>
        <div class="print-meta">
          Reporting Period: <strong>{{ activePeriodLabel }}</strong> &bull;
          G6 Responsibility: <strong>ICT and Communications Equipment</strong> &bull;
          Generated: <strong>{{ currentDateFormatted }} {{ currentTimeFormatted }}</strong>
        </div>
      </div>

      <!-- Historical Snapshot Indicator Banner -->
      <div v-if="reportData && reportData.mode === 'historical' && reportData.has_snapshot" class="historical-banner print-hide">
        <ion-icon :icon="timeOutline" class="banner-icon" />
        <span>Viewing Historical G6 Equipment Readiness Snapshot for <strong>{{ reportData.period_label }}</strong>. Historical data is immutable.</span>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state" data-testid="loading-state">
        <ion-spinner name="crescent" class="loading-spinner"></ion-spinner>
        <p>Loading G6 equipment readiness report...</p>
      </div>

      <!-- API Error State -->
      <div v-else-if="errorMessage" class="error-state" data-testid="error-state">
        <ion-icon :icon="alertCircleOutline" class="error-icon" />
        <div class="error-content">
          <h3>Failed to Load Readiness Report</h3>
          <p>{{ errorMessage }}</p>
          <button class="btn-retry" type="button" @click="loadData">
            <ion-icon :icon="refreshOutline" />
            <span>Retry</span>
          </button>
        </div>
      </div>

      <!-- Missing Snapshot Period State -->
      <div v-else-if="reportData && !reportData.has_snapshot" class="missing-snapshot-card" data-testid="missing-snapshot-state">
        <ion-icon :icon="documentOutline" class="missing-icon" />
        <div class="missing-content">
          <h3>No Snapshot Data Recorded</h3>
          <p>No snapshot data recorded for period <strong>{{ reportData.period }}</strong>.</p>
          <p class="missing-subtext">
            Historical readiness calculations require an archived monthly snapshot. Per operational reporting rules, historical views do not fall back to live inventory.
          </p>
          <button class="btn-return-current" type="button" @click="returnToCurrent">
            <span>Return to Current Period</span>
          </button>
        </div>
      </div>

      <!-- Main Report Content -->
      <div v-else-if="reportData && reportData.has_snapshot" class="report-content" data-testid="report-content">
        
        <!-- Section 1: Executive Summary Cards -->
        <div class="executive-cards-grid">
          
          <!-- Equipment Readiness Card -->
          <div class="kpi-card equipment-kpi" data-testid="kpi-equipment">
            <div class="kpi-card-header">
              <span class="kpi-label">Equipment Readiness Rating</span>
              <span class="redcon-badge" :class="getRedconClass(reportData.summary?.equipment_redcon)" data-testid="kpi-equipment-redcon">
                {{ reportData.summary?.equipment_redcon || 'R4' }}
              </span>
            </div>
            <div class="kpi-card-body">
              <div class="kpi-value" data-testid="kpi-equipment-value">
                {{ formatPercent(reportData.summary?.equipment_rating) }}
              </div>
              <p class="kpi-description">
                Aggregate readiness against approved JRRS target quantities
              </p>
            </div>
            <div class="kpi-card-footer">
              <div class="kpi-meta-item">
                <span class="meta-label">Total On-Hand:</span>
                <span class="meta-val">{{ reportData.summary?.totals.on_hand ?? 0 }}</span>
              </div>
              <div class="kpi-meta-item">
                <span class="meta-label">Required:</span>
                <span class="meta-val">{{ reportData.summary?.totals.required ?? 0 }}</span>
              </div>
              <div class="kpi-meta-item">
                <span class="meta-label">Deficit:</span>
                <span class="meta-val text-deficit">{{ reportData.summary?.totals.deficit ?? 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Maintenance Readiness Card -->
          <div class="kpi-card maintenance-kpi" data-testid="kpi-maintenance">
            <div class="kpi-card-header">
              <span class="kpi-label">Maintenance Readiness Rating</span>
              <span class="redcon-badge" :class="getRedconClass(reportData.summary?.maintenance_redcon)" data-testid="kpi-maintenance-redcon">
                {{ reportData.summary?.maintenance_redcon || 'R4' }}
              </span>
            </div>
            <div class="kpi-card-body">
              <div class="kpi-value" data-testid="kpi-maintenance-value">
                {{ formatPercent(reportData.summary?.maintenance_rating) }}
              </div>
              <p class="kpi-description">
                Proportion of on-hand inventory in operational/serviceable condition
              </p>
            </div>
            <div class="kpi-card-footer">
              <div class="kpi-meta-item">
                <span class="meta-label">Operational:</span>
                <span class="meta-val text-success">{{ reportData.summary?.totals.operational ?? 0 }}</span>
              </div>
              <div class="kpi-meta-item">
                <span class="meta-label">For Repair:</span>
                <span class="meta-val text-warning">{{ reportData.summary?.totals.repair ?? 0 }}</span>
              </div>
              <div class="kpi-meta-item">
                <span class="meta-label">BER / Turn-In:</span>
                <span class="meta-val text-danger">{{ reportData.summary?.totals.ber ?? 0 }}</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Section 2: Group Summary Table -->
        <div class="report-section-card" data-testid="group-summary-section">
          <div class="section-card-header">
            <h3>Group Readiness Summary</h3>
            <span class="section-badge">Unweighted Group Rollup</span>
          </div>

          <div class="table-responsive">
            <table class="report-table group-summary-table" data-testid="group-summary-table">
              <thead>
                <tr>
                  <th class="text-left">Group / Equipment Category</th>
                  <th class="text-right">Required</th>
                  <th class="text-right">Operational</th>
                  <th class="text-right">For Repair</th>
                  <th class="text-right">BER / Turn-In</th>
                  <th class="text-right">On-Hand</th>
                  <th class="text-right">Deficit</th>
                  <th class="text-right">Equipment Rating</th>
                  <th class="text-center">Eq REDCON</th>
                  <th class="text-right">Maintenance Rating</th>
                  <th class="text-center">Maint REDCON</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="grp in reportData.groups"
                  :key="grp.group_id"
                  class="group-row"
                  :data-testid="`group-row-${grp.group_id}`"
                >
                  <td class="font-medium text-left">
                    {{ grp.group_name }} Equipment
                  </td>
                  <td class="text-right">{{ grp.totals.required }}</td>
                  <td class="text-right">{{ grp.totals.operational }}</td>
                  <td class="text-right">{{ grp.totals.repair }}</td>
                  <td class="text-right">{{ grp.totals.ber }}</td>
                  <td class="text-right font-semibold">{{ grp.totals.on_hand }}</td>
                  <td class="text-right text-deficit">{{ grp.totals.deficit }}</td>
                  <td class="text-right font-semibold">
                    {{ formatPercent(grp.equipment_rating) }}
                  </td>
                  <td class="text-center">
                    <span class="redcon-badge" :class="getRedconClass(grp.equipment_redcon)">
                      {{ grp.equipment_redcon }}
                    </span>
                  </td>
                  <td class="text-right font-semibold">
                    {{ formatPercent(grp.maintenance_rating) }}
                  </td>
                  <td class="text-center">
                    <span class="redcon-badge" :class="getRedconClass(grp.maintenance_redcon)">
                      {{ grp.maintenance_redcon }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section 3: Detailed Equipment Readiness Table -->
        <div class="report-section-card" data-testid="detailed-readiness-section">
          <div class="section-card-header">
            <h3>Equipment Subtype Readiness Details</h3>
            <span class="section-badge">{{ reportData.lines?.length || 0 }} Items</span>
          </div>

          <div class="table-responsive">
            <table class="report-table detailed-readiness-table" data-testid="detailed-readiness-table">
              <thead>
                <tr>
                  <th class="text-left">Nomenclature</th>
                  <th class="text-right">Required</th>
                  <th class="text-right">Operational</th>
                  <th class="text-right">For Repair</th>
                  <th class="text-right">BER / Turn-In</th>
                  <th class="text-right">On-Hand</th>
                  <th class="text-right">Deficit</th>
                  <th class="text-right">Equipment Rating</th>
                  <th class="text-center">Eq REDCON</th>
                  <th class="text-right">Maintenance Rating</th>
                  <th class="text-center">Maint REDCON</th>
                </tr>
              </thead>
              <template v-for="grp in reportData.groups" :key="grp.group_id">
                <!-- Group Category Header -->
                <tbody class="group-tbody">
                  <tr class="group-header-row">
                    <td colspan="11" class="group-header-cell">
                      <span class="group-title-tag">{{ grp.group_name }} Equipment</span>
                    </td>
                  </tr>

                  <!-- Item Lines -->
                  <tr
                    v-for="line in grp.lines"
                    :key="line.equipment_subtype_id"
                    class="data-row"
                    :data-testid="`line-row-${line.equipment_subtype_id}`"
                  >
                    <td class="font-medium text-left nomenclature-cell">
                      {{ line.nomenclature }}
                    </td>
                    <td class="text-right">{{ line.required }}</td>
                    <td class="text-right text-success">{{ line.operational }}</td>
                    <td class="text-right text-warning">{{ line.repair }}</td>
                    <td class="text-right text-danger">{{ line.ber }}</td>
                    <td class="text-right font-semibold">{{ line.on_hand }}</td>
                    <td class="text-right text-deficit">{{ line.deficit }}</td>
                    <td class="text-right font-semibold">
                      {{ formatPercent(line.equipment_rating) }}
                    </td>
                    <td class="text-center">
                      <span class="redcon-badge" :class="getRedconClass(line.equipment_redcon)">
                        {{ line.equipment_redcon }}
                      </span>
                    </td>
                    <td class="text-right font-semibold">
                      {{ formatPercent(line.maintenance_rating) }}
                    </td>
                    <td class="text-center">
                      <span class="redcon-badge" :class="getRedconClass(line.maintenance_redcon)">
                        {{ line.maintenance_redcon }}
                      </span>
                    </td>
                  </tr>

                  <!-- Group Subtotal Row -->
                  <tr class="group-subtotal-row">
                    <td class="font-semibold text-left">
                      Subtotal — {{ grp.group_name }} Equipment
                    </td>
                    <td class="text-right font-semibold">{{ grp.totals.required }}</td>
                    <td class="text-right font-semibold text-success">{{ grp.totals.operational }}</td>
                    <td class="text-right font-semibold text-warning">{{ grp.totals.repair }}</td>
                    <td class="text-right font-semibold text-danger">{{ grp.totals.ber }}</td>
                    <td class="text-right font-bold">{{ grp.totals.on_hand }}</td>
                    <td class="text-right font-semibold text-deficit">{{ grp.totals.deficit }}</td>
                    <td class="text-right font-bold">
                      {{ formatPercent(grp.equipment_rating) }}
                    </td>
                    <td class="text-center">
                      <span class="redcon-badge" :class="getRedconClass(grp.equipment_redcon)">
                        {{ grp.equipment_redcon }}
                      </span>
                    </td>
                    <td class="text-right font-bold">
                      {{ formatPercent(grp.maintenance_rating) }}
                    </td>
                    <td class="text-center">
                      <span class="redcon-badge" :class="getRedconClass(grp.maintenance_redcon)">
                        {{ grp.maintenance_redcon }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </template>

              <!-- Overall Grand Totals Row -->
              <tfoot>
                <tr class="grand-total-row" data-testid="grand-total-row">
                  <td class="font-bold text-left uppercase">
                    Overall G6 Readiness (Unweighted)
                  </td>
                  <td class="text-right font-bold">{{ reportData.summary?.totals.required }}</td>
                  <td class="text-right font-bold text-success">{{ reportData.summary?.totals.operational }}</td>
                  <td class="text-right font-bold text-warning">{{ reportData.summary?.totals.repair }}</td>
                  <td class="text-right font-bold text-danger">{{ reportData.summary?.totals.ber }}</td>
                  <td class="text-right font-bold">{{ reportData.summary?.totals.on_hand }}</td>
                  <td class="text-right font-bold text-deficit">{{ reportData.summary?.totals.deficit }}</td>
                  <td class="text-right font-extrabold text-primary">
                    {{ formatPercent(reportData.summary?.equipment_rating) }}
                  </td>
                  <td class="text-center">
                    <span class="redcon-badge" :class="getRedconClass(reportData.summary?.equipment_redcon)">
                      {{ reportData.summary?.equipment_redcon }}
                    </span>
                  </td>
                  <td class="text-right font-extrabold text-primary">
                    {{ formatPercent(reportData.summary?.maintenance_rating) }}
                  </td>
                  <td class="text-center">
                    <span class="redcon-badge" :class="getRedconClass(reportData.summary?.maintenance_redcon)">
                      {{ reportData.summary?.maintenance_redcon }}
                    </span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Section 4: Operational Explanatory Footnotes -->
        <div class="report-footnotes-card print-hide">
          <h4>Reporting & REDCON Criteria</h4>
          <div class="footnotes-grid">
            <div class="footnote-item">
              <strong>Hierarchical Unweighted Rollup:</strong> Group ratings represent the unweighted arithmetic mean of applicable subtype lines; overall readiness represents the unweighted arithmetic mean of applicable groups.
            </div>
            <div class="footnote-item">
              <strong>REDCON Status Scale:</strong>
              <span class="pill-preview redcon-r1">R1 &ge; 85%</span>
              <span class="pill-preview redcon-r2">R2 &ge; 75%</span>
              <span class="pill-preview redcon-r3">R3 &gt; 50%</span>
              <span class="pill-preview redcon-r4">R4 &le; 50% / N/A</span>
            </div>
          </div>
        </div>

      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  IonSpinner,
  IonIcon
} from '@ionic/vue'
import {
  printOutline,
  calendarOutline,
  timeOutline,
  alertCircleOutline,
  refreshOutline,
  documentOutline
} from 'ionicons/icons'

import MainLayout from '@/layouts/MainLayout.vue'
import { fetchG6Readiness, fetchReportingPeriods } from '@/services/inventoryService'
import type { G6ReadinessReport, ReportingPeriod } from '@/types/inventory'
import { formatDate, formatTime } from '@/utils/dateUtils'

// State
const loading = ref(true)
const errorMessage = ref<string | null>(null)
const reportData = ref<G6ReadinessReport | null>(null)
const periods = ref<ReportingPeriod[]>([])
const selectedPeriod = ref<string>('')

// Current generation timestamp
const currentDateFormatted = formatDate(new Date())
const currentTimeFormatted = formatTime(new Date())

// Active period label computed
const activePeriodLabel = computed(() => {
  if (reportData.value?.period_label) {
    return reportData.value.period_label
  }
  const match = periods.value.find(p => p.year_month === selectedPeriod.value)
  return match?.label || selectedPeriod.value || 'Current'
})

/**
 * Format decimal rating (0.0 to 1.0) into percentage string (e.g. "45.29%").
 * Explicitly guards against converting null to 0%.
 */
function formatPercent(val: number | null | undefined): string {
  if (val === null || val === undefined) {
    return '—'
  }
  const pct = val * 100
  return `${pct.toFixed(2)}%`
}

/**
 * Return CSS token class for REDCON indicator
 */
function getRedconClass(redcon?: string | null): string {
  switch (redcon) {
    case 'R1':
      return 'redcon-r1'
    case 'R2':
      return 'redcon-r2'
    case 'R3':
      return 'redcon-r3'
    case 'R4':
    default:
      return 'redcon-r4'
  }
}

/**
 * Load readiness report for the selected period
 */
async function loadData() {
  loading.value = true
  errorMessage.value = null

  try {
    const periodArg = selectedPeriod.value || undefined
    const res = await fetchG6Readiness(periodArg)

    if (res.success && res.data) {
      reportData.value = res.data
      if (!selectedPeriod.value && res.data.period) {
        selectedPeriod.value = res.data.period
      }
    } else {
      errorMessage.value = res.message || 'Failed to retrieve G6 Equipment Readiness Report.'
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'An unexpected error occurred while loading the report.'
  } finally {
    loading.value = false
  }
}

/**
 * Handle period selection change
 */
function handlePeriodChange() {
  loadData()
}

/**
 * Return to the current period
 */
function returnToCurrent() {
  const current = periods.value.find(p => p.is_current)
  if (current) {
    selectedPeriod.value = current.year_month
  } else if (periods.value.length > 0) {
    selectedPeriod.value = periods.value[0].year_month
  }
  loadData()
}

/**
 * Trigger browser print
 */
function handlePrint() {
  window.print()
}

/**
 * Lifecycle hook: Load available periods and initial readiness data
 */
onMounted(async () => {
  try {
    const periodsRes = await fetchReportingPeriods()
    if (periodsRes.success && periodsRes.data && periodsRes.data.length > 0) {
      periods.value = periodsRes.data
      const current = periodsRes.data.find(p => p.is_current)
      selectedPeriod.value = current ? current.year_month : periodsRes.data[0].year_month
    }
  } catch (err) {
    console.warn('Could not pre-load periods:', err)
  }

  await loadData()
})
</script>

<style scoped>
.readiness-report-container {
  padding: 24px;
  max-width: 1440px;
  margin: 0 auto;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header Bar */
.module-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 20px;
}

.header-titles h2 {
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
}

.header-controls {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Period Selector */
.period-selector-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.period-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text, #0F172A);
}

.select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.calendar-icon {
  position: absolute;
  left: 10px;
  font-size: 1rem;
  color: var(--color-text-secondary, #64748B);
  pointer-events: none;
}

.period-select {
  padding: 8px 12px 8px 32px;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text, #0F172A);
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.period-select:focus {
  border-color: var(--color-primary-light, #2563EB);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

/* Buttons */
.btn-print {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  font-size: 0.875rem;
  font-weight: 600;
  color: #FFFFFF;
  background-color: var(--color-primary-light, #2563EB);
  border: none;
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
  transition: background-color 0.2s, transform 0.1s;
}

.btn-print:hover {
  background-color: var(--color-primary, #1E3A8A);
}

.btn-print:active {
  transform: translateY(1px);
}

/* Banners */
.historical-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  margin-bottom: 20px;
  background-color: #EFF6FF;
  border: 1px solid #BFDBFE;
  border-radius: var(--radius-sm, 6px);
  color: #1E40AF;
  font-size: 0.875rem;
}

.historical-banner .banner-icon {
  font-size: 1.25rem;
  color: #2563EB;
}

/* Loading & Error States */
.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-md, 10px);
  text-align: center;
}

.loading-spinner {
  width: 36px;
  height: 36px;
  color: var(--color-primary-light, #2563EB);
  margin-bottom: 12px;
}

.loading-state p {
  color: var(--color-text-secondary, #64748B);
  font-size: 0.875rem;
}

.error-icon {
  font-size: 2.5rem;
  color: #DC2626;
  margin-bottom: 12px;
}

.error-content h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: #991B1B;
  margin: 0 0 6px 0;
}

.error-content p {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #64748B);
  margin: 0 0 16px 0;
}

.btn-retry,
.btn-return-current {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 0.875rem;
  font-weight: 600;
  color: #FFFFFF;
  background-color: var(--color-primary-light, #2563EB);
  border: none;
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
}

/* Missing Snapshot State */
.missing-snapshot-card {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  padding: 32px;
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid #E2E8F0;
  border-left: 5px solid #F59E0B;
  border-radius: var(--radius-md, 10px);
  margin-bottom: 24px;
}

.missing-icon {
  font-size: 2.25rem;
  color: #D97706;
  flex-shrink: 0;
}

.missing-content h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0 0 6px 0;
}

.missing-content p {
  font-size: 0.875rem;
  color: var(--color-text, #0F172A);
  margin: 0 0 6px 0;
}

.missing-subtext {
  color: var(--color-text-secondary, #64748B) !important;
  font-size: 0.8125rem !important;
  margin-bottom: 16px !important;
}

/* Executive Cards Grid */
.executive-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.kpi-card {
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-md, 10px);
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  display: flex;
  flex-direction: column;
}

.kpi-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.kpi-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.kpi-card-body {
  margin-bottom: 16px;
}

.kpi-value {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--color-primary-dark, #172554);
  line-height: 1.1;
  margin-bottom: 6px;
}

.kpi-description {
  font-size: 0.8125rem;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
}

.kpi-card-footer {
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid var(--color-surface-hover, #F1F5F9);
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.kpi-meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.meta-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.meta-val {
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--color-text, #0F172A);
}

/* Sections & Tables */
.report-section-card {
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-md, 10px);
  margin-bottom: 24px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.section-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background-color: #F8FAFC;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
}

.section-card-header h3 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0;
}

.section-badge {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  background-color: #E2E8F0;
  padding: 3px 8px;
  border-radius: var(--radius-sm, 6px);
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.report-table th {
  background-color: #F1F5F9;
  color: #334155;
  font-weight: 700;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  white-space: nowrap;
}

.report-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #E2E8F0;
  color: var(--color-text, #0F172A);
  white-space: nowrap;
}

.group-row:hover,
.data-row:hover {
  background-color: var(--color-surface-hover, #F8FAFC);
}

.group-header-row td {
  background-color: #F8FAFC;
  padding: 10px 14px;
  border-top: 1px solid var(--color-border, #CBD5E1);
  border-bottom: 1px solid #E2E8F0;
}

.group-title-tag {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.group-subtotal-row td {
  background-color: #F8FAFC;
  border-top: 1px solid #CBD5E1;
  border-bottom: 2px solid #CBD5E1;
  font-size: 0.8125rem;
}

.grand-total-row td {
  background-color: #F1F5F9;
  border-top: 2px solid var(--color-primary-dark, #172554);
  border-bottom: 2px solid var(--color-primary-dark, #172554);
  font-size: 0.875rem;
  padding: 12px;
}

/* REDCON Badges */
.redcon-badge {
  display: inline-block;
  padding: 3px 10px;
  font-size: 0.75rem;
  font-weight: 800;
  border-radius: var(--radius-sm, 6px);
  letter-spacing: 0.04em;
  text-align: center;
  min-width: 36px;
}

.redcon-r1 {
  color: #15803D;
  background-color: #DCFCE7;
  border: 1px solid #16A34A;
}

.redcon-r2 {
  color: #1D4ED8;
  background-color: #DBEAFE;
  border: 1px solid #2563EB;
}

.redcon-r3 {
  color: #B45309;
  background-color: #FEF3C7;
  border: 1px solid #D97706;
}

.redcon-r4 {
  color: #B91C1C;
  background-color: #FEE2E2;
  border: 1px solid #DC2626;
}

/* Utility Helpers */
.text-left { text-align: left; }
.text-right { text-align: right; }
.text-center { text-align: center; }

.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; }
.font-extrabold { font-weight: 800; }
.uppercase { text-transform: uppercase; }

.text-success { color: #16A34A; font-weight: 600; }
.text-warning { color: #D97706; font-weight: 600; }
.text-danger { color: #DC2626; font-weight: 600; }
.text-deficit { color: #DC2626; font-weight: 600; }
.text-primary { color: var(--color-primary-dark, #172554); }

/* Footnotes */
.report-footnotes-card {
  background-color: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-md, 10px);
  padding: 16px 20px;
}

.report-footnotes-card h4 {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text, #0F172A);
  margin: 0 0 10px 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.footnotes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 12px;
}

.footnote-item {
  font-size: 0.75rem;
  color: var(--color-text-secondary, #64748B);
  line-height: 1.5;
}

.pill-preview {
  display: inline-block;
  padding: 1px 6px;
  font-size: 0.6875rem;
  font-weight: 700;
  border-radius: 4px;
  margin-left: 4px;
}

/* Print Styles */
.print-only {
  display: none;
}

@media print {
  .print-hide {
    display: none !important;
  }

  .print-only {
    display: block !important;
  }

  .readiness-report-container {
    padding: 0;
    max-width: 100%;
  }

  .printable-header {
    text-align: center;
    border-bottom: 2px solid #000000;
    padding-bottom: 12px;
    margin-bottom: 18px;
  }

  .print-org-title {
    font-size: 11pt;
    font-weight: 700;
    color: #000000;
    letter-spacing: 0.05em;
  }

  .print-report-title {
    font-size: 15pt;
    font-weight: 800;
    color: #000000;
    margin: 4px 0;
  }

  .print-meta {
    font-size: 8.5pt;
    color: #444444;
  }

  .report-section-card {
    border: 1px solid #000000;
    box-shadow: none;
    margin-bottom: 16px;
    page-break-inside: avoid;
  }

  .section-card-header {
    background-color: #EEEEEE !important;
    border-bottom: 1px solid #000000;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .report-table {
    font-size: 7.5pt;
  }

  .report-table th {
    background-color: #EEEEEE !important;
    color: #000000 !important;
    border-bottom: 1px solid #000000;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .report-table td {
    border-bottom: 1px solid #CCCCCC;
  }

  .redcon-badge {
    border: 1px solid #000000 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .executive-cards-grid {
    grid-template-columns: 1fr 1fr;
    page-break-inside: avoid;
  }

  .kpi-card {
    border: 1px solid #000000;
    box-shadow: none;
  }
}
</style>
