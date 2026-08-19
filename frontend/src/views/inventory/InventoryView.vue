<template>
  <MainLayout title="Inventory Module">
    <div class="inventory-container">
      
      <!-- Top Action Bar with Period Selector -->
      <div class="header-action-bar">
        <div>
          <h2>Inventory Overview</h2>
          <p class="subtitle">ICT equipment readiness and maintenance condition metrics.</p>
        </div>

        <div class="period-selector-wrapper">
          <label for="periodSelect" class="period-label">Reporting Period:</label>
          <div class="select-box">
            <ion-icon :icon="calendarOutline" class="calendar-icon" />
            <select
              id="periodSelect"
              v-model="selectedPeriod"
              @change="handlePeriodChange"
              class="period-select"
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
      </div>

      <!-- Historical Snapshot Indicator Banner -->
      <div v-if="overview && !overview.is_current" class="historical-banner">
        <ion-icon :icon="timeOutline" class="banner-icon" />
        <span>Viewing Historical Inventory Snapshot for <strong>{{ overview.period_label }}</strong>.</span>
      </div>

      <!-- KPI Summary Cards Grid -->
      <div v-if="loading" class="loading-state">
        <span class="spinner"></span>
        <p>Calculating inventory readiness...</p>
      </div>

      <template v-else-if="overview">
        <div class="kpi-grid">

          <!-- Equipment Readiness Card -->
          <div class="kpi-card primary-card">
            <div class="kpi-icon-box blue-icon">
              <ion-icon :icon="desktopOutline" />
            </div>
            <div class="kpi-content">
              <span class="kpi-title">Equipment Readiness</span>
              <div class="kpi-value-row">
                <span class="kpi-number">{{ overview.equipment_readiness_pct }}%</span>
              </div>
              <p class="kpi-desc">Actual vs JRRS Target Quantity</p>
              <div class="progress-bar-bg">
                <div class="progress-bar-fill blue-fill" :style="{ width: Math.min(100, overview.equipment_readiness_pct) + '%' }"></div>
              </div>
            </div>
          </div>

          <!-- Maintenance Readiness Card -->
          <div class="kpi-card success-card">
            <div class="kpi-icon-box green-icon">
              <ion-icon :icon="checkmarkCircleOutline" />
            </div>
            <div class="kpi-content">
              <span class="kpi-title">Maintenance Readiness</span>
              <div class="kpi-value-row">
                <span class="kpi-number">{{ overview.maintenance_readiness_pct }}%</span>
              </div>
              <p class="kpi-desc">Serviceable On-Hand Equipment</p>
              <div class="progress-bar-bg">
                <div class="progress-bar-fill green-fill" :style="{ width: Math.min(100, overview.maintenance_readiness_pct) + '%' }"></div>
              </div>
            </div>
          </div>

          <!-- Total Equipment Card -->
          <div class="kpi-card neutral-card">
            <div class="kpi-icon-box slate-icon">
              <ion-icon :icon="cubeOutline" />
            </div>
            <div class="kpi-content">
              <span class="kpi-title">Total Equipment</span>
              <span class="kpi-number">{{ overview.total_equipment }}</span>
              <p class="kpi-desc">Registered On-Hand Units</p>
            </div>
          </div>

        </div>

        <!-- Status Distribution Grid -->
        <div class="status-summary-grid">
          <div class="status-card serviceable-box">
            <div class="status-header">
              <span class="status-dot green-dot"></span>
              <span class="status-name">Serviceable</span>
            </div>
            <span class="status-count">{{ overview.serviceable_count }}</span>
          </div>

          <div class="status-card repair-box">
            <div class="status-header">
              <span class="status-dot orange-dot"></span>
              <span class="status-name">For Repair</span>
            </div>
            <span class="status-count">{{ overview.for_repair_count }}</span>
          </div>

          <div class="status-card unserviceable-box">
            <div class="status-header">
              <span class="status-dot red-dot"></span>
              <span class="status-name">For Turn-In / Unserviceable</span>
            </div>
            <span class="status-count">{{ overview.unserviceable_count }}</span>
          </div>
        </div>

        <!-- Category Readiness Table -->
        <div class="table-card">
          <div class="table-card-header">
            <h3>Equipment Readiness by Category</h3>
          </div>
          
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Equipment Subtype</th>
                  <th class="text-center">JRRS Target</th>
                  <th class="text-center">Current Quantity</th>
                  <th class="text-center">Shortage</th>
                  <th class="text-center">Readiness %</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in overview.type_breakdown" :key="item.equipment_subtype_id || item.equipment_subtype">
                  <td><span class="category-tag">{{ item.equipment_type }}</span></td>
                  <td class="font-semibold text-primary">{{ item.equipment_subtype }}</td>
                  <td class="text-center font-bold">{{ item.target_quantity }}</td>
                  <td class="text-center">{{ item.current_quantity }}</td>
                  <td class="text-center">
                    <span :class="['badge', item.shortage > 0 ? 'badge-warning' : 'badge-success']">
                      {{ item.shortage }}
                    </span>
                  </td>
                  <td class="text-center">
                    <div class="table-progress-cell">
                      <span class="pct-text">{{ item.readiness_pct }}%</span>
                      <div class="mini-progress-bg">
                        <div class="mini-progress-fill" :style="{ width: Math.min(100, item.readiness_pct) + '%' }"></div>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  calendarOutline,
  timeOutline,
  desktopOutline,
  checkmarkCircleOutline,
  cubeOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { fetchReportingPeriods, fetchInventoryOverview } from '../../services/inventoryService'
import type { ReportingPeriod, OverviewData } from '../../types/inventory'

const periods = ref<ReportingPeriod[]>([])
const selectedPeriod = ref('')
const overview = ref<OverviewData | null>(null)
const loading = ref(true)

async function loadData() {
  loading.value = true
  const periodRes = await fetchReportingPeriods()
  if (periodRes.success && periodRes.data.length > 0) {
    periods.value = periodRes.data
    if (!selectedPeriod.value) {
      selectedPeriod.value = periods.value[0].year_month
    }
  }

  if (selectedPeriod.value) {
    const overviewRes = await fetchInventoryOverview(selectedPeriod.value)
    if (overviewRes.success) {
      overview.value = overviewRes.data
    }
  }
  loading.value = false
}

async function handlePeriodChange() {
  loading.value = true
  const overviewRes = await fetchInventoryOverview(selectedPeriod.value)
  if (overviewRes.success) {
    overview.value = overviewRes.data
  }
  loading.value = false
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.inventory-container {
  padding: 32px 40px;
  max-width: 1280px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 24px;
}

.header-action-bar h2 {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.period-selector-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.period-label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}

.select-box {
  position: relative;
  display: flex;
  align-items: center;
}

.calendar-icon {
  position: absolute;
  left: 12px;
  font-size: 16px;
  color: #64748b;
  pointer-events: none;
}

.period-select {
  padding: 10px 16px 10px 38px;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.period-select:focus {
  border-color: #2563eb;
}

.historical-banner {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}

.banner-icon {
  font-size: 18px;
}

.loading-state {
  text-align: center;
  padding: 48px;
  color: #64748b;
}

.spinner {
  display: inline-block;
  width: 24px;
  height: 24px;
  border: 3px solid #cbd5e1;
  border-radius: 50%;
  border-top-color: #2563eb;
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}

.kpi-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.kpi-icon-box {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}

.blue-icon { background: #eff6ff; color: #2563eb; }
.green-icon { background: #f0fdf4; color: #16a34a; }
.slate-icon { background: #f8fafc; color: #475569; }

.kpi-content {
  flex: 1;
}

.kpi-title {
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  display: block;
  margin-bottom: 4px;
}

.kpi-number {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
}

.kpi-desc {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 10px 0;
}

.progress-bar-bg {
  height: 6px;
  background: #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 6px;
}

.blue-fill { background: #2563eb; }
.green-fill { background: #16a34a; }

.status-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.status-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 16px 20px;
  border: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.green-dot { background: #16a34a; }
.orange-dot { background: #ea580c; }
.red-dot { background: #dc2626; }

.status-name {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.status-count {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
}

.table-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.table-card-header {
  padding: 16px 24px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}

.table-card-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.data-table th {
  background: #f8fafc;
  padding: 12px 20px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e2e8f0;
}

.data-table td {
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}

.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; color: #0f172a; }
.text-primary { color: #2563eb; }
.text-center { text-align: center; }

.category-tag {
  background: #f1f5f9;
  color: #475569;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.badge-success { background: #f0fdf4; color: #16a34a; }
.badge-warning { background: #fff7ed; color: #c2410c; }

.table-progress-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.pct-text {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  min-width: 48px;
  text-align: right;
}

.mini-progress-bg {
  width: 80px;
  height: 6px;
  background: #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
}

.mini-progress-fill {
  height: 100%;
  background: #2563eb;
  border-radius: 6px;
}
</style>