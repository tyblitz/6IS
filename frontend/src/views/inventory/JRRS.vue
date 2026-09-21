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
                <td v-if="categoryScope === 'All'"><span class="category-tag">{{ item.category || item.equipment_type }}</span></td>
                <td class="font-semibold text-primary">
                  <div class="item-nomenclature">{{ item.nomenclature || item.equipment_subtype }}</div>
                  <div v-if="item.sub_category" class="item-subcategory">{{ item.sub_category }}</div>
                </td>
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
              <button type="button" class="btn-secondary cancel-btn" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary save-btn" :disabled="saving">
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
    const cat = (item.category || item.equipment_type || '').toUpperCase()
    const isComm = cat.includes('COMM')

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
} = useTablePagination(filteredJrrsList, { pageSize: 15, defaultSortKey: 'sort_order', defaultSortOrder: 'asc' })

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

  const targetId = editItem.value.id || (editItem.value as any).jrrs_id || (editItem.value as any).equipment_subtype_id
  const res = await updateJrrsTarget(targetId, editTargetQty.value)
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
  padding: var(--space-lg, 24px) var(--space-xl, 32px);
  max-width: 1360px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.header-action-bar h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
}

.period-selector-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.period-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--color-text-secondary, #64748B);
  text-transform: uppercase;
  letter-spacing: 0.05em;
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
  color: var(--color-text-secondary, #64748B);
  pointer-events: none;
}

.period-select {
  padding: 8px 16px 8px 38px;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text, #0F172A);
  background: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-sm, 6px);
  outline: none;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.period-select:focus {
  border-color: var(--color-primary-light, #2563EB);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.historical-banner {
  background: var(--color-info-bg, #EFF6FF);
  border: 1px solid var(--color-info-border, #BFDBFE);
  color: var(--color-info-text, #1D4ED8);
  padding: 12px 16px;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

.admin-notice {
  background: var(--color-cat-vtc-bg, #FAF5FF);
  border: 1px solid var(--color-cat-vtc-border, #E9D5FF);
  color: var(--color-cat-vtc, #9333EA);
  padding: 12px 16px;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.8125rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}

.admin-icon,
.banner-icon {
  font-size: 18px;
}

.loading-state {
  text-align: center;
  padding: 48px;
  color: var(--color-text-secondary, #64748B);
}

.spinner {
  display: inline-block;
  width: 24px;
  height: 24px;
  border: 3px solid var(--color-border, #CBD5E1);
  border-radius: 50%;
  border-top-color: var(--color-primary-light, #2563EB);
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; color: var(--color-text, #0F172A); }
.text-primary { color: var(--color-primary-light, #2563EB); }
.text-center { text-align: center; }

.item-nomenclature {
  font-weight: 600;
  color: var(--color-primary, #1E3A8A);
  font-size: 0.875rem;
}

.item-subcategory {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin-top: 2px;
}

.table-progress-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.pct-text {
  font-size: 0.8125rem;
  font-weight: 700;
  color: var(--color-text, #0F172A);
  min-width: 48px;
  text-align: right;
}

.mini-progress-bg {
  width: 80px;
  height: 6px;
  background: var(--color-border, #CBD5E1);
  border-radius: var(--radius-sm, 6px);
  overflow: hidden;
}

.mini-progress-fill {
  height: 100%;
  background: var(--color-primary-light, #2563EB);
  border-radius: var(--radius-sm, 6px);
}
</style>