<template>
  <MainLayout :title="pageTitle">
    <div class="jrrs-container">
      
      <!-- Top Action Bar with Period Selector -->
      <div class="header-action-bar">
        <div>
          <h2>{{ pageTitle }}</h2>
          <p class="subtitle">{{ pageSubtitle }}</p>
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
        <span>Viewing Historical JRRS Readiness Snapshot for <strong>{{ periodInfo.period_label }}</strong>.</span>
      </div>

      <!-- Admin Status Banner -->
      <div v-if="activeUser?.role === 'Administrator'" class="admin-notice">
        <ion-icon :icon="shieldCheckmarkOutline" class="admin-icon" />
        <span>Administrator Mode — You are authorized to modify approved JRRS target quantities.</span>
      </div>

      <!-- Search & Filter Controls Bar -->
      <div class="table-filter-toolbar">
        <div class="search-box-input">
          <ion-icon :icon="searchOutline" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search category, equipment subtype..."
          />
        </div>
      </div>

      <!-- JRRS Data Table -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Approved Equipment Subtype Targets & Readiness ({{ totalItems }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading JRRS target records...</p>
        </div>

        <div v-else-if="totalItems === 0" class="empty-state">
          <p>No JRRS readiness records found for this period.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th v-if="categoryScope === 'All'" class="sortable-th" @click="toggleSort('equipment_type')">
                  <div class="th-content">
                    <span>Category</span>
                    <ion-icon :icon="getSortIcon('equipment_type')" :class="['sort-icon', sortKey === 'equipment_type' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('equipment_subtype')">
                  <div class="th-content">
                    <span>Equipment Subtype</span>
                    <ion-icon :icon="getSortIcon('equipment_subtype')" :class="['sort-icon', sortKey === 'equipment_subtype' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center sortable-th" @click="toggleSort('target_quantity')">
                  <div class="th-content justify-center">
                    <span>Target Quantity</span>
                    <ion-icon :icon="getSortIcon('target_quantity')" :class="['sort-icon', sortKey === 'target_quantity' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center sortable-th" @click="toggleSort('current_quantity')">
                  <div class="th-content justify-center">
                    <span>Current Quantity</span>
                    <ion-icon :icon="getSortIcon('current_quantity')" :class="['sort-icon', sortKey === 'current_quantity' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center sortable-th" @click="toggleSort('shortage')">
                  <div class="th-content justify-center">
                    <span>Shortage</span>
                    <ion-icon :icon="getSortIcon('shortage')" :class="['sort-icon', sortKey === 'shortage' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center sortable-th" @click="toggleSort('readiness_pct')">
                  <div class="th-content justify-center">
                    <span>Readiness %</span>
                    <ion-icon :icon="getSortIcon('readiness_pct')" :class="['sort-icon', sortKey === 'readiness_pct' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th v-if="activeUser?.role === 'Administrator'" class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedItems" :key="item.id">
                <td v-if="categoryScope === 'All'"><span class="category-tag">{{ item.equipment_type }}</span></td>
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
                <td v-if="activeUser?.role === 'Administrator'" class="text-center">
                  <button class="edit-btn" @click="openEditModal(item)">
                    <ion-icon :icon="createOutline" />
                    <span>Edit Target</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- 10-Item Limit Pagination -->
          <TablePagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total-items="totalItems"
            :start-index="startIndex"
            :end-index="endIndex"
            @change-page="setPage"
          />
        </div>
      </div>

      <!-- Admin Target Edit Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Modify JRRS Target Quantity</h3>
            <button class="close-btn" @click="closeModal">&times;</button>
          </div>

          <form @submit.prevent="saveTarget" class="modal-body">
            <div class="form-group">
              <label>Equipment Subtype</label>
              <input type="text" :value="editItem ? (editItem.equipment_subtype + ' (' + editItem.equipment_type + ')') : ''" disabled class="input-disabled" />
            </div>

            <div class="form-group">
              <label for="targetQty">Target Quantity <span class="required-star">*</span></label>
              <input
                id="targetQty"
                v-model.number="editTargetQty"
                type="number"
                min="0"
                required
                class="input-text"
              />
            </div>

            <div v-if="modalError" class="modal-error">
              {{ modalError }}
            </div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="closeModal">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Target' }}
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router'
import { ref, computed, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  calendarOutline,
  timeOutline,
  shieldCheckmarkOutline,
  createOutline,
  searchOutline,
  swapVerticalOutline,
  chevronUpOutline,
  chevronDownOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { activeUser } from '../../services/authService'
import { useTablePagination } from '../../composables/useTablePagination'
import TablePagination from '../../components/common/TablePagination.vue'
import {
  fetchReportingPeriods,
  fetchJrrsList,
  updateJrrsTarget
} from '../../services/inventoryService'
import type { ReportingPeriod, JrrsItem } from '../../types/inventory'

const route = useRoute()

const categoryScope = computed(() => {
  if (route.path.endsWith('/ict')) return 'ICT'
  if (route.path.endsWith('/communications')) return 'Communications'
  return 'All'
})

const pageTitle = computed(() => {
  if (categoryScope.value === 'ICT') return 'JRRS ICT Equipment Readiness'
  if (categoryScope.value === 'Communications') return 'JRRS Communications Equipment Readiness'
  return 'JRRS Table of Equipment Comparison'
})

const pageSubtitle = computed(() => {
  if (categoryScope.value === 'ICT') return 'Approved ICT targets vs actual equipment readiness metrics.'
  if (categoryScope.value === 'Communications') return 'Approved communications targets vs actual equipment readiness metrics.'
  return 'Approved targets vs actual equipment readiness metrics by subtype.'
})

const periods = ref<ReportingPeriod[]>([])
const selectedPeriod = ref('')
const jrrsList = ref<JrrsItem[]>([])
const periodInfo = ref<{ period_label: string; is_current: boolean } | null>(null)
const loading = ref(true)

const filteredJrrsList = computed(() => {
  return jrrsList.value.filter(item => {
    const typeStr = (item.equipment_type || '').toUpperCase()
    const subTypeStr = (item.equipment_subtype || '').toUpperCase()
    const isComm = typeStr.includes('COMM') || ['MIXER', 'MICROPHONE', 'SPEAKER', 'PUBLIC ADDRESS SYSTEM', 'PAS'].some(k => subTypeStr.includes(k) || typeStr.includes(k))

    if (categoryScope.value === 'ICT') {
      if (isComm) return false
    } else if (categoryScope.value === 'Communications') {
      if (!isComm) return false
    }
    return true
  })
})

const {
  searchQuery,
  currentPage,
  totalItems,
  totalPages,
  startIndex,
  endIndex,
  sortKey,
  sortOrder,
  paginatedItems,
  toggleSort,
  setPage
} = useTablePagination(filteredJrrsList, { pageSize: 10, defaultSortKey: 'equipment_type', defaultSortOrder: 'asc' })

function getSortIcon(key: string) {
  if (sortKey.value !== key) return swapVerticalOutline
  return sortOrder.value === 'asc' ? chevronUpOutline : chevronDownOutline
}

// Admin Modal State
const showModal = ref(false)
const editItem = ref<JrrsItem | null>(null)
const editTargetQty = ref(0)
const saving = ref(false)
const modalError = ref('')

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
    const listRes = await fetchJrrsList(selectedPeriod.value)
    if (listRes.success && listRes.data) {
      jrrsList.value = Array.isArray(listRes.data.items) ? listRes.data.items : (Array.isArray(listRes.data) ? listRes.data : [])
      periodInfo.value = {
        period_label: listRes.data.period_label || selectedPeriod.value,
        is_current: listRes.data.is_current ?? true
      }
    }
  }
  loading.value = false
}

async function handlePeriodChange() {
  loading.value = true
  const listRes = await fetchJrrsList(selectedPeriod.value)
  if (listRes.success && listRes.data) {
    jrrsList.value = Array.isArray(listRes.data.items) ? listRes.data.items : (Array.isArray(listRes.data) ? listRes.data : [])
    periodInfo.value = {
      period_label: listRes.data.period_label || selectedPeriod.value,
      is_current: listRes.data.is_current ?? true
    }
  }
  loading.value = false
}

function openEditModal(item: JrrsItem) {
  editItem.value = item
  editTargetQty.value = item.target_quantity
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editItem.value = null
}

async function saveTarget() {
  if (!editItem.value || editTargetQty.value < 0) return

  saving.value = true
  modalError.value = ''

  const res = await updateJrrsTarget(editItem.value.equipment_subtype_id, editTargetQty.value)
  saving.value = false

  if (res.success) {
    closeModal()
    handlePeriodChange() // Refresh list
  } else {
    modalError.value = res.message || 'Failed to update target.'
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.jrrs-container {
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
  margin-bottom: 20px;
}

.admin-notice {
  background: #faf5ff;
  border: 1px solid #e9d5ff;
  color: #7e22ce;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}

.admin-icon {
  font-size: 18px;
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

.edit-btn {
  background: #f1f5f9;
  color: #2563eb;
  border: 1px solid #cbd5e1;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}

.edit-btn:hover {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

/* Modal Styles */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #94a3b8;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.required-star { color: #dc2626; }

.input-text, .input-disabled {
  width: 100%;
  padding: 10px 14px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  box-sizing: border-box;
}

.input-disabled {
  background: #f8fafc;
  color: #64748b;
}

.input-text:focus {
  border-color: #2563eb;
}

.modal-error {
  background: #fef2f2;
  color: #dc2626;
  padding: 10px;
  border-radius: 6px;
  font-size: 13px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 8px;
}

.cancel-btn {
  padding: 10px 16px;
  background: #f1f5f9;
  color: #475569;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.save-btn {
  padding: 10px 20px;
  background: #082f6d;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.save-btn:hover {
  background: #1d4ed8;
}
</style>