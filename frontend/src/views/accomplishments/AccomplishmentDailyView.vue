<template>
  <MainLayout title="Daily Report" username="Admin">
    <div class="report-page-container">

      <!-- Header & Action Bar -->
      <div class="module-header-bar print-hide">
        <div>
          <h2>Daily Report</h2>
          <p class="subtitle">Daily accomplishment records log and management.</p>
        </div>
        <div class="header-action-group">
          <button class="btn-print" type="button" @click="handlePrint">
            <ion-icon :icon="printOutline"></ion-icon>
            <span>Print Report</span>
          </button>
          <button class="add-btn" type="button" @click="openCreateModal">
            <ion-icon :icon="addOutline"></ion-icon>
            <span>Add Activity</span>
          </button>
        </div>
      </div>

      <!-- Printable Document Header (Visible in print) -->
      <div class="printable-header print-only">
        <div class="print-org-title">6IS INTEGRATED INFORMATION SYSTEM</div>
        <div class="print-report-title">DAILY ACCOMPLISHMENT REPORT</div>
        <div class="print-meta">Date: {{ formatDate(filterDate) }} | Generated: {{ formatDateTime(new Date()) }}</div>
      </div>

      <!-- Filter Toolbar -->
      <div class="toolbar-card print-hide">
        <div class="toolbar-grid">
          
          <!-- Date Filter with Calendar Icon -->
          <div class="filter-item date-filter-item">
            <label>Date</label>
            <div class="date-input-container">
              <input 
                ref="dateInputRef" 
                v-model="filterDate" 
                type="date" 
                @change="loadData" 
              />
              <button 
                type="button" 
                class="calendar-icon-btn" 
                title="Open Calendar" 
                @click="openDatePicker"
              >
                <ion-icon :icon="calendarOutline"></ion-icon>
              </button>
            </div>
          </div>

          <!-- Category Filter -->
          <div class="filter-item">
            <label>Category</label>
            <select v-model.number="filterCategoryId" @change="loadData">
              <option :value="0">-Select-</option>
              <option v-for="cat in options.categories" :key="cat.id" :value="cat.id">
                {{ cat.category_code || cat.category_name }}
              </option>
            </select>
          </div>

          <!-- Office Filter -->
          <div class="filter-item">
            <label>Office</label>
            <select v-model.number="filterOfficeId" @change="loadData">
              <option :value="0">-Select-</option>
              <option v-for="off in options.offices" :key="off.id" :value="off.id">
                {{ off.office_code ? `${off.office_name} (${off.office_code})` : off.office_name }}
              </option>
            </select>
          </div>

          <!-- Search Filter -->
          <div class="filter-item search-box">
            <label>Search</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search description or remarks..."
              @keyup.enter="loadData"
            />
          </div>

          <!-- Filter Action Buttons -->
          <div class="filter-actions">
            <button class="btn-filter" type="button" @click="loadData">Apply Filter</button>
            <button class="btn-reset" type="button" @click="resetFilters">Reset</button>
          </div>

        </div>
      </div>

      <!-- Records Table -->
      <div class="table-card">
        <div v-if="loading" class="state-container print-hide">
          <ion-spinner name="crescent"></ion-spinner>
          <span>Loading daily report records...</span>
        </div>

        <div v-else-if="records.length === 0" class="state-container empty-box print-hide">
          <ion-icon :icon="clipboardOutline" class="empty-icon"></ion-icon>
          <p>No accomplishment records found for {{ formatDate(filterDate) }}.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="report-table">
            <thead>
              <tr>
                <th style="width: 160px;" class="sortable-th" @click="toggleSort('office_code')">
                  <div class="th-content">
                    <span>Office</span>
                    <ion-icon :icon="getSortIcon('office_code')" :class="['sort-icon', sortKey === 'office_code' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th style="width: 180px;" class="sortable-th" @click="toggleSort('category_code')">
                  <div class="th-content">
                    <span>Category</span>
                    <ion-icon :icon="getSortIcon('category_code')" :class="['sort-icon', sortKey === 'category_code' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('description')">
                  <div class="th-content">
                    <span>Description</span>
                    <ion-icon :icon="getSortIcon('description')" :class="['sort-icon', sortKey === 'description' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('remarks')">
                  <div class="th-content">
                    <span>Remarks</span>
                    <ion-icon :icon="getSortIcon('remarks')" :class="['sort-icon', sortKey === 'remarks' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-right print-hide" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedItems" :key="item.id" class="clickable-row" @click="navigateToAccDetail(item)">
                <td class="whitespace-nowrap">
                  <span class="office-tag">{{ item.office_code || item.office_name }}</span>
                </td>
                <td class="whitespace-nowrap">
                  <span class="category-tag">{{ item.category_code || item.category_name || '-' }}</span>
                </td>
                <td class="desc-cell">{{ item.description }}</td>
                <td class="remarks-cell">{{ item.remarks || '-' }}</td>
                <td class="text-right actions-cell print-hide" @click.stop>
                  <button class="icon-action-btn view-btn" title="View Details" @click="navigateToAccDetail(item)">
                    <ion-icon :icon="eyeOutline"></ion-icon>
                  </button>
                  <button class="icon-action-btn edit-btn" title="Edit Record" @click="openEditModal(item)">
                    <ion-icon :icon="createOutline"></ion-icon>
                  </button>
                  <button class="icon-action-btn delete-btn" title="Soft Delete" @click="handleDeletePrompt(item)">
                    <ion-icon :icon="trashOutline"></ion-icon>
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

      <!-- Form Modal -->
      <AccomplishmentFormModal
        :is-open="isFormOpen"
        :options="options"
        :edit-data="selectedRecord"
        @close="isFormOpen = false"
        @saved="loadData"
      />

      <!-- Details Modal -->
      <AccomplishmentDetailModal
        :is-open="isDetailOpen"
        :data="selectedRecord"
        @close="isDetailOpen = false"
        @edit="handleEditFromDetail"
      />

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonSpinner, IonIcon, onIonViewWillEnter } from '@ionic/vue'
import {
  addOutline,
  clipboardOutline,
  eyeOutline,
  createOutline,
  trashOutline,
  calendarOutline,
  swapVerticalOutline,
  chevronUpOutline,
  chevronDownOutline,
  printOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { useTablePagination } from '../../composables/useTablePagination'
import TablePagination from '../../components/common/TablePagination.vue'
import AccomplishmentFormModal from '../../components/accomplishments/AccomplishmentFormModal.vue'
import AccomplishmentDetailModal from '../../components/accomplishments/AccomplishmentDetailModal.vue'

import type {
  AccomplishmentItem,
  AccomplishmentOptions
} from '../../types/accomplishment'
import {
  fetchDailyAccomplishments,
  fetchAccomplishmentOptions,
  deleteAccomplishment
} from '../../services/accomplishmentService'
import { formatDate, formatDateTime } from '../../utils/dateUtils'

const route = useRoute()
const router = useRouter()

function navigateToAccDetail(item: AccomplishmentItem) {
  router.push(`/accomplishments/detail/${item.id}`)
}

const loading = ref(true)
const records = ref<AccomplishmentItem[]>([])

const dateInputRef = ref<HTMLInputElement | null>(null)
const filterDate = ref((route.query.date as string) || new Date().toISOString().split('T')[0])
const filterCategoryId = ref(0)
const filterOfficeId = ref(0)
const searchQuery = ref('')

const {
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
} = useTablePagination(records, { pageSize: 10, defaultSortKey: 'office_code', defaultSortOrder: 'asc' })

function getSortIcon(key: string) {
  if (sortKey.value !== key) return swapVerticalOutline
  return sortOrder.value === 'asc' ? chevronUpOutline : chevronDownOutline
}

function openDatePicker() {
  if (dateInputRef.value) {
    if (typeof (dateInputRef.value as any).showPicker === 'function') {
      (dateInputRef.value as any).showPicker()
    } else {
      dateInputRef.value.focus()
      dateInputRef.value.click()
    }
  }
}



const isFormOpen = ref(false)
const isDetailOpen = ref(false)
const selectedRecord = ref<AccomplishmentItem | null>(null)

const options = reactive<AccomplishmentOptions>({
  offices: [],
  categories: []
})

onMounted(() => {
  loadOptions()
  loadData()
})

onIonViewWillEnter(() => {
  loadOptions()
  loadData()
})

watch(() => route.fullPath, () => {
  loadData()
})

async function loadData() {
  loading.value = true
  const res = await fetchDailyAccomplishments(
    filterDate.value,
    filterOfficeId.value > 0 ? filterOfficeId.value : undefined,
    searchQuery.value,
    filterCategoryId.value > 0 ? filterCategoryId.value : undefined
  )
  loading.value = false

  if (res.success && res.data) {
    records.value = res.data.records || []
  }
}

async function loadOptions() {
  const res = await fetchAccomplishmentOptions()
  if (res.success && res.data) {
    options.offices = res.data.offices || []
    options.categories = res.data.categories || []
  }
}

function resetFilters() {
  filterDate.value = new Date().toISOString().split('T')[0]
  filterOfficeId.value = 0
  filterCategoryId.value = 0
  searchQuery.value = ''
  loadData()
}

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

function openEditModal(item: AccomplishmentItem) {
  selectedRecord.value = item
  isFormOpen.value = true
}

function handlePrint() {
  window.print()
}

function handleEditFromDetail(record: AccomplishmentItem) {
  isDetailOpen.value = false
  selectedRecord.value = record
  isFormOpen.value = true
}

async function handleDeletePrompt(item: AccomplishmentItem) {
  if (confirm(`Are you sure you want to delete this accomplishment record?\n\n"${item.description}"`)) {
    const res = await deleteAccomplishment(item.id)
    if (res.success) {
      loadData()
    } else {
      alert(res.message || 'Failed to delete accomplishment record.')
    }
  }
}
</script>

<style scoped>
.report-page-container {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.module-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.module-header-bar h2 {
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

.header-action-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.add-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
  transition: background-color 0.15s ease;
}

.add-btn:hover { background: #1d4ed8; }
.add-btn ion-icon { font-size: 18px; }

.btn-print {
  background: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.15s ease;
}

.btn-print:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.btn-print ion-icon { font-size: 18px; }

.toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.toolbar-grid {
  display: flex;
  gap: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 160px;
  flex: 1;
}

.date-filter-item {
  max-width: 220px;
}

.filter-item.search-box {
  flex: 2;
  min-width: 240px;
}

label {
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.date-input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.date-input-container input[type="date"] {
  width: 100%;
  padding-right: 36px;
}

.calendar-icon-btn {
  position: absolute;
  right: 8px;
  background: transparent;
  border: none;
  color: #2563eb;
  font-size: 18px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
}

.calendar-icon-btn:hover {
  color: #1d4ed8;
}

input[type="date"],
select,
input[type="text"] {
  width: 100%;
  padding: 9px 12px;
  font-size: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

input[type="date"]:focus,
select:focus,
input[type="text"]:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-actions {
  display: flex;
  gap: 10px;
}

.btn-filter {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-filter:hover { background: #1d4ed8; }

.btn-reset {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-reset:hover { background: #e2e8f0; }

.table-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
  padding: 20px;
}

.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #64748b;
  gap: 12px;
  text-align: center;
}

.empty-icon {
  font-size: 48px;
  color: #cbd5e1;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
}

.report-table th {
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.report-table td {
  padding: 14px 12px;
  font-size: 14px;
  color: #334155;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.whitespace-nowrap { white-space: nowrap; }

.office-tag {
  background: #eff6ff;
  color: #2563eb;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.category-tag {
  background: #f1f5f9;
  color: #0f172a;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
}

.desc-cell {
  line-height: 1.5;
  max-width: 450px;
}

.remarks-cell {
  color: #64748b;
  font-size: 13px;
  max-width: 250px;
}

.text-right { text-align: right; }

.actions-cell {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

.icon-action-btn {
  background: #f1f5f9;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.view-btn { color: #2563eb; }
.view-btn:hover { background: #dbeafe; }

.edit-btn { color: #d97706; }
.edit-btn:hover { background: #fef3c7; }

.delete-btn { color: #dc2626; }
.delete-btn:hover { background: #fee2e2; }

/* PRINT MEDIA STYLES */
.printable-header,
.print-only {
  display: none;
}

@media print {
  .print-hide { display: none !important; }
  .print-only { display: block !important; }

  .report-page-container {
    padding: 0;
    max-width: 100%;
  }

  .printable-header {
    margin-bottom: 20px;
    text-align: center;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
  }

  .print-org-title { font-size: 14pt; font-weight: bold; }
  .print-report-title { font-size: 16pt; font-weight: bold; margin-top: 4px; }
  .print-meta { font-size: 10pt; color: #555; margin-top: 4px; }

  .table-card {
    border: none;
    box-shadow: none;
    padding: 0;
  }

  .report-table th, .report-table td {
    border: 1px solid #000 !important;
    padding: 8px !important;
    font-size: 10pt !important;
  }
}
</style>
