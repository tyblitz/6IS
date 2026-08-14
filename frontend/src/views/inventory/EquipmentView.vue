<template>
  <MainLayout title="Inventory Equipment">
    <div class="equipment-container">
      
      <!-- Top Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>ICT Equipment Registry</h2>
          <p class="subtitle">Complete equipment inventory listings for all offices.</p>
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
      <div v-if="periodInfo && !periodInfo.is_current" class="historical-banner">
        <ion-icon :icon="timeOutline" class="banner-icon" />
        <span>Viewing Historical Equipment Snapshot for <strong>{{ periodInfo.period_label }}</strong>.</span>
      </div>

      <!-- Equipment Table Card -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Equipment Records ({{ equipmentList.length }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading equipment records...</p>
        </div>

        <div v-else-if="equipmentList.length === 0" class="empty-state">
          <p>No equipment records found for this period.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Office</th>
                <th>Equipment Type</th>
                <th>Description</th>
                <th>Serial Number</th>
                <th>Date Acquired</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in equipmentList" :key="item.id">
                <td>
                  <span class="office-tag" :title="item.office_name">{{ item.office_abbv }}</span>
                </td>
                <td class="font-semibold">{{ item.equipment_type }}</td>
                <td>{{ item.description }}</td>
                <td class="code-text">{{ item.serial_number || 'N/A' }}</td>
                <td>{{ item.date_acquired || 'N/A' }}</td>
                <td class="text-center">
                  <span :class="['status-badge', getStatusClass(item.status)]">
                    {{ item.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import { calendarOutline, timeOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { fetchReportingPeriods, fetchEquipmentList } from '../../services/inventoryService'
import type { ReportingPeriod, EquipmentItem, EquipmentStatus } from '../../types/inventory'

const periods = ref<ReportingPeriod[]>([])
const selectedPeriod = ref('')
const equipmentList = ref<EquipmentItem[]>([])
const periodInfo = ref<{ period_label: string; is_current: boolean } | null>(null)
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
    const listRes = await fetchEquipmentList(selectedPeriod.value)
    if (listRes.success) {
      equipmentList.value = listRes.data.items
      periodInfo.value = {
        period_label: listRes.data.period_label,
        is_current: listRes.data.is_current
      }
    }
  }
  loading.value = false
}

async function handlePeriodChange() {
  loading.value = true
  const listRes = await fetchEquipmentList(selectedPeriod.value)
  if (listRes.success) {
    equipmentList.value = listRes.data.items
    periodInfo.value = {
      period_label: listRes.data.period_label,
      is_current: listRes.data.is_current
    }
  }
  loading.value = false
}

function getStatusClass(status: EquipmentStatus): string {
  switch (status) {
    case 'Serviceable':
      return 'status-serviceable'
    case 'For Repair':
      return 'status-repair'
    case 'For Turn-In / Unserviceable':
      return 'status-unserviceable'
    default:
      return ''
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.equipment-container {
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

.loading-state, .empty-state {
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
.code-text { font-family: monospace; font-weight: 600; color: #475569; }
.text-center { text-align: center; }

.office-tag {
  background: #f1f5f9;
  color: #0f172a;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.status-serviceable { background: #f0fdf4; color: #16a34a; }
.status-repair { background: #fff7ed; color: #c2410c; }
.status-unserviceable { background: #fef2f2; color: #dc2626; }
</style>