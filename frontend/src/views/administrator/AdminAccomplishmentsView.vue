<template>
  <MainLayout title="Accomplishments Management">
    <div class="admin-accomplishments-container">
      
      <!-- Header Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Accomplishments Data Management</h2>
          <p class="subtitle">Manage master list of accomplishment records.</p>
        </div>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="searchFilter">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="searchFilter"
              v-model="filterSearch"
              type="text"
              placeholder="Search description, remarks, office..."
              class="input-search"
              @input="handleFilterChange"
            />
          </div>
        </div>

        <div class="filter-group">
          <label for="categoryFilter">Category</label>
          <select id="categoryFilter" v-model="filterCategoryId" class="input-select" @change="handleFilterChange">
            <option :value="0">-Select-</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.category_code ? (cat.category_code + ' - ' + cat.category_name) : cat.category_name }}
            </option>
          </select>
        </div>

        <div class="filter-group">
          <label for="officeFilter">Office</label>
          <select id="officeFilter" v-model="filterOfficeId" class="input-select" @change="handleFilterChange">
            <option :value="0">-Select-</option>
            <option v-for="off in offices" :key="off.id" :value="off.id">
              {{ off.office_abbv || off.office_name }}
            </option>
          </select>
        </div>

        <div class="filter-actions">
          <button v-if="hasActiveFilters" class="reset-filter-btn" @click="resetFilters">
            <ion-icon :icon="closeCircleOutline" />
            <span>Reset</span>
          </button>
        </div>
      </div>

      <!-- Master Entries Data Table -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Master Accomplishments Registry</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading accomplishment records...</p>
        </div>

        <div v-else-if="accomplishments.length === 0" class="empty-state">
          <p>No accomplishment records match the selected filters.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width: 120px;" class="sortable-th" @click="toggleSort('date')">
                  <div class="th-content">
                    <span>Date</span>
                    <ion-icon :icon="getSortIcon('date')" :class="['sort-icon', sortKey === 'date' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('category_code')">
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
                <th style="width: 90px;" class="sortable-th" @click="toggleSort('office_code')">
                  <div class="th-content">
                    <span>Office</span>
                    <ion-icon :icon="getSortIcon('office_code')" :class="['sort-icon', sortKey === 'office_code' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('remarks')">
                  <div class="th-content">
                    <span>Remarks</span>
                    <ion-icon :icon="getSortIcon('remarks')" :class="['sort-icon', sortKey === 'remarks' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center" style="width: 90px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedAccomplishments" :key="item.id">
                <td class="font-bold date-cell">{{ formatDate(item.date) }}</td>
                <td><span class="category-badge">{{ item.category_code || item.category_name || 'General' }}</span></td>
                <td class="font-semibold">{{ item.description }}</td>
                <td><span class="office-tag">{{ item.office_abbv || item.office_code || item.office_name }}</span></td>
                <td class="text-subtle">{{ item.remarks || 'N/A' }}</td>
                <td class="text-center">
                  <div class="icon-actions">
                    <button class="icon-btn edit-icon-btn" @click="openEditModal(item)" title="Edit Accomplishment">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button class="icon-btn delete-icon-btn" @click="handleDelete(item)" title="Delete Accomplishment">
                      <ion-icon :icon="trashOutline" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination Bar -->
          <TablePagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total-items="accomplishments.length"
            :start-index="showingStart"
            :end-index="showingEnd"
            @change-page="goToPage"
          />
        </div>
      </div>

      <!-- Edit Accomplishment Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Edit Accomplishment Record</h3>
            <button class="close-btn" @click="closeModal">&times;</button>
          </div>

          <form @submit.prevent="handleSaveAccomplishment" class="modal-body">
            <div class="form-group">
              <label for="accDate">Accomplishment Date</label>
              <input id="accDate" v-model="form.date" type="date" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="accOffice">Office Assignment</label>
              <select id="accOffice" v-model="form.office_id" class="input-select" required>
                <option value="0" disabled>Select Office...</option>
                <option v-for="off in offices" :key="off.id" :value="off.id">
                  {{ off.office_abbv }} — {{ off.office_name }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="accCategory">Category</label>
              <select id="accCategory" v-model="form.category_id" class="input-select" required>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.category_name }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="accDesc">Description</label>
              <textarea id="accDesc" v-model="form.description" rows="3" placeholder="Enter accomplishment details..." required class="input-textarea"></textarea>
            </div>

            <div class="form-group">
              <label for="accRemarks">Remarks (Optional)</label>
              <input id="accRemarks" v-model="form.remarks" type="text" placeholder="Remarks..." class="input-text" />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="closeModal">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : 'Update Record' }}
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  searchOutline,
  closeCircleOutline,
  createOutline,
  trashOutline,
  swapVerticalOutline,
  chevronUpOutline,
  chevronDownOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import TablePagination from '../../components/common/TablePagination.vue'

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/accomplishments/index.php`
  }
  return 'http://localhost/6IS/backend/api/accomplishments/index.php'
}

const API_BASE_URL = resolveApiUrl()

const accomplishments = ref<any[]>([])
const categories = ref<any[]>([])
const offices = ref<any[]>([])
const loading = ref(true)

// Filter & Search States
const filterSearch = ref('')
const filterCategoryId = ref(0)
const filterOfficeId = ref(0)

const sortKey = ref('date')
const sortOrder = ref<'asc' | 'desc'>('desc')

function toggleSort(key: string) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
  currentPage.value = 1
}

function getSortIcon(key: string) {
  if (sortKey.value !== key) return swapVerticalOutline
  return sortOrder.value === 'asc' ? chevronUpOutline : chevronDownOutline
}

// Pagination States
const currentPage = ref(1)
const itemsPerPage = 10

const sortedAccomplishments = computed(() => {
  const list = [...accomplishments.value]
  if (!sortKey.value) return list
  const k = sortKey.value
  const isAsc = sortOrder.value === 'asc'
  return list.sort((a, b) => {
    const valA = a[k] ?? ''
    const valB = b[k] ?? ''
    const strA = String(valA).toLowerCase()
    const strB = String(valB).toLowerCase()
    if (strA < strB) return isAsc ? -1 : 1
    if (strA > strB) return isAsc ? 1 : -1
    return 0
  })
})

const totalPages = computed(() => Math.ceil(sortedAccomplishments.value.length / itemsPerPage) || 1)

const paginatedAccomplishments = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  return sortedAccomplishments.value.slice(start, start + itemsPerPage)
})

const showingStart = computed(() => {
  if (sortedAccomplishments.value.length === 0) return 0
  return (currentPage.value - 1) * itemsPerPage + 1
})

const showingEnd = computed(() => {
  return Math.min(currentPage.value * itemsPerPage, sortedAccomplishments.value.length)
})

function goToPage(p: number) {
  if (p >= 1 && p <= totalPages.value) {
    currentPage.value = p
  }
}

// Modal States
const showModal = ref(false)
const editId = ref(0)
const saving = ref(false)
const modalError = ref('')

const form = ref({
  office_id: 1,
  category_id: 1,
  date: new Date().toISOString().slice(0, 10),
  description: '',
  remarks: ''
})

const hasActiveFilters = computed(() => {
  return filterSearch.value.trim() !== '' || filterCategoryId.value > 0 || filterOfficeId.value > 0
})

async function loadData() {
  loading.value = true
  try {
    const optRes = await fetch(`${API_BASE_URL}?view=options`, { credentials: 'include' })
    const optData = await optRes.json()
    if (optData.success && optData.data) {
      categories.value = optData.data.categories || []
      offices.value = optData.data.offices || []
    }

    const params = new URLSearchParams({ view: 'all' })
    if (filterSearch.value.trim()) params.append('search', filterSearch.value.trim())
    if (filterCategoryId.value > 0) params.append('category_id', filterCategoryId.value.toString())
    if (filterOfficeId.value > 0) params.append('office_id', filterOfficeId.value.toString())

    const accRes = await fetch(`${API_BASE_URL}?${params.toString()}`, { credentials: 'include' })
    const accData = await accRes.json()
    if (accData.success && accData.data?.records) {
      accomplishments.value = accData.data.records
    } else {
      accomplishments.value = []
    }
  } catch {
    // ignore network error
  }
  loading.value = false
}

let searchDebounceTimer: any = null
function handleFilterChange() {
  currentPage.value = 1
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    loadData()
  }, 250)
}

function resetFilters() {
  filterSearch.value = ''
  filterCategoryId.value = 0
  filterOfficeId.value = 0
  currentPage.value = 1
  loadData()
}

function openEditModal(item: any) {
  editId.value = item.id
  form.value = {
    office_id: item.office_id,
    category_id: item.category_id || 1,
    date: item.date,
    description: item.description,
    remarks: item.remarks || ''
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSaveAccomplishment() {
  saving.value = true
  modalError.value = ''
  try {
    const res = await fetch(`${API_BASE_URL}?id=${editId.value}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      closeModal()
      loadData()
    } else {
      modalError.value = data.message || 'Failed to update accomplishment.'
    }
  } catch (err: any) {
    saving.value = false
    modalError.value = err.message || 'Network error.'
  }
}

async function handleDelete(item: any) {
  if (!confirm(`Are you sure you want to delete accomplishment '${item.description}'?`)) return
  try {
    const res = await fetch(`${API_BASE_URL}?id=${item.id}`, {
      method: 'DELETE',
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) {
      loadData()
    } else {
      alert(data.message || 'Failed to delete accomplishment.')
    }
  } catch (err) {
    alert('Network error.')
  }
}

function formatDate(dateStr: string) {
  if (!dateStr) return 'N/A'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[d.getMonth()]
  const year = d.getFullYear()
  return `${day} ${month} ${year}`
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.admin-accomplishments-container {
  padding: 32px 40px; max-width: 1280px; margin: 0 auto;
}
.header-action-bar {
  display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;
}
.header-action-bar h2 { font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; }
.subtitle { font-size: 14px; color: #64748b; margin: 0; }

/* Filter & Search Bar */
.filter-controls-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 18px 24px;
  margin-bottom: 24px;
  display: flex;
  align-items: flex-end;
  gap: 16px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.02);
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.search-group {
  flex: 1;
  min-width: 260px;
}

.filter-group label {
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: #94a3b8;
  pointer-events: none;
}

.input-search {
  width: 100%;
  padding: 9px 14px 9px 38px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  box-sizing: border-box;
}

.input-search:focus { border-color: #2563eb; }

.input-select {
  padding: 9px 14px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  background: #ffffff;
  min-width: 180px;
  box-sizing: border-box;
}

.input-select:focus { border-color: #2563eb; }

.filter-actions {
  display: flex;
  align-items: center;
}

.reset-filter-btn {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 9px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
}

.reset-filter-btn:hover { background: #e2e8f0; color: #0f172a; }

/* Table Card */
.table-card { background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.table-card-header { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
.table-card-header h3 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.loading-state, .empty-state { text-align: center; padding: 48px; color: #64748b; }
.spinner {
  display: inline-block; width: 24px; height: 24px; border: 3px solid #cbd5e1;
  border-radius: 50%; border-top-color: #2563eb; animation: spin 0.8s linear infinite; margin-bottom: 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th {
  background: #f8fafc; padding: 12px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0;
}
.data-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.date-cell { font-size: 13px; color: #0f172a; white-space: nowrap; }
.text-subtle { color: #64748b; font-size: 13px; }
.text-center { text-align: center; }

.category-badge { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; }
.office-tag { background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }

.icon-actions {
  display: flex;
  gap: 6px;
  justify-content: center;
  align-items: center;
}

.icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s ease;
}

.edit-icon-btn { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.edit-icon-btn:hover { background: #2563eb; color: #ffffff; }

.delete-icon-btn { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.delete-icon-btn:hover { background: #dc2626; color: #ffffff; }

/* Pagination Footer */
.pagination-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 24px;
  border-top: 1px solid #f1f5f9;
  background: #ffffff;
}

.pagination-info {
  font-size: 13px;
  color: #64748b;
}

.pagination-info strong {
  color: #0f172a;
}

.pagination-controls {
  display: flex;
  gap: 6px;
  align-items: center;
}

.page-btn {
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-btn:not(:disabled):hover {
  background: #f1f5f9;
  color: #0f172a;
}

.page-number-btn {
  width: 32px;
  height: 32px;
  font-size: 13px;
  font-weight: 700;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.page-number-btn:hover {
  background: #f1f5f9;
}

.active-page {
  background: #082f6d !important;
  color: #ffffff !important;
  border-color: #082f6d !important;
}

/* Modal */
.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15,23,42,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-card { background: #ffffff; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; }
.input-text, .input-select, .input-textarea { width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; box-sizing: border-box; }
.input-textarea { resize: vertical; font-family: inherit; }
.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
