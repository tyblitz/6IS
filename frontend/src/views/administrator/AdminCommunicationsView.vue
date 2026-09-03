<template>
  <MainLayout title="Communications Management">
    <div class="admin-comms-container">
      
      <!-- Top Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Communications Management</h2>
          <p class="subtitle">Maintain incoming and outgoing communications logs, office assignments, and status.</p>
        </div>

        <div class="action-buttons-group">
          <button class="action-main-btn" @click="openAddCommModal">
            <ion-icon :icon="addOutline" />
            <span>Add Communication</span>
          </button>
        </div>
      </div>

      <!-- Type Filter Tabs: Incoming vs Outgoing -->
      <div class="tab-switcher">
        <button
          :class="['tab-btn', typeTab === 'Incoming' ? 'active-tab' : '']"
          @click="switchTypeTab('Incoming')"
        >
          <ion-icon :icon="arrowDownCircleOutline" />
          <span>Incoming Communications</span>
        </button>

        <button
          :class="['tab-btn', typeTab === 'Outgoing' ? 'active-tab' : '']"
          @click="switchTypeTab('Outgoing')"
        >
          <ion-icon :icon="arrowUpCircleOutline" />
          <span>Outgoing Communications</span>
        </button>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="commSearch">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="commSearch"
              v-model="searchQuery"
              type="text"
              @input="currentPage = 1"
              placeholder="Search subject, office, category, or purpose..."
              class="input-search"
            />
          </div>
        </div>

        <div class="filter-group">
          <label for="commCatFilter">Category</label>
          <select id="commCatFilter" v-model="filterCategory" @change="currentPage = 1" class="input-select">
            <option value="0">-Select-</option>
            <option v-for="cat in categoryList" :key="cat.id" :value="cat.id">{{ cat.code || cat.category_name }}</option>
          </select>
        </div>

        <div class="filter-group">
          <label for="commPurpFilter">Purpose</label>
          <select id="commPurpFilter" v-model="filterPurpose" @change="currentPage = 1" class="input-select">
            <option value="0">-Select-</option>
            <option v-for="p in purposeList" :key="p.id" :value="p.id">{{ p.purpose_name }}</option>
          </select>
        </div>

        <div class="filter-group">
          <label for="commStatFilter">Status</label>
          <select id="commStatFilter" v-model="filterStatus" @change="currentPage = 1" class="input-select">
            <option value="all">-Select-</option>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
            <option value="Released">Released</option>
          </select>
        </div>
      </div>

      <!-- Communications Data Table Card -->
      <div class="table-card">

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading communications...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="sortedList.length === 0" class="empty-state">
          <p v-if="searchQuery || filterCategory > 0 || filterPurpose > 0 || filterStatus !== 'all'">
            No {{ typeTab.toLowerCase() }} communications match your filters.
          </p>
          <p v-else>No {{ typeTab.toLowerCase() }} communications recorded yet.</p>
        </div>

        <!-- Data Table -->
        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="sortable-th" @click="toggleSort('communication_date')">
                  <div class="th-content">
                    <span>Date</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'communication_date' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'communication_date' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th" @click="toggleSort('subject')">
                  <div class="th-content">
                    <span>Subject</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'subject' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'subject' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th" @click="toggleSort('originating_office')">
                  <div class="th-content">
                    <span>Originating Office</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'originating_office' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'originating_office' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th" @click="toggleSort('category_name')">
                  <div class="th-content">
                    <span>Category</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'category_name' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'category_name' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th" @click="toggleSort('purpose_name')">
                  <div class="th-content">
                    <span>Purpose</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'purpose_name' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'purpose_name' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th text-center" @click="toggleSort('status')">
                  <div class="th-content center-th">
                    <span>Status</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'status' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'status' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="text-center" style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedList" :key="item.id">
                <td>{{ formatDate(item.communication_date) }}</td>
                <td class="font-semibold text-primary">{{ item.subject }}</td>
                <td><span class="office-tag">{{ item.originating_office || 'N/A' }}</span></td>
                <td><span class="category-tag">{{ item.category_code || item.category_name || 'N/A' }}</span></td>
                <td>{{ item.purpose_name || 'N/A' }}</td>
                <td class="text-center">
                  <span :class="['status-badge', getStatusBadgeStyle(item.status)]">
                    {{ item.status || 'Pending' }}
                  </span>
                </td>
                <td class="text-center">
                  <div class="action-buttons">
                    <button class="icon-btn edit-btn" title="Edit Communication" @click="openEditCommModal(item)">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button class="icon-btn delete-btn" title="Delete Communication" @click="handleSoftDeleteComm(item)">
                      <ion-icon :icon="trashOutline" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Footer -->
        <div v-if="sortedList.length > 0" class="pagination-footer">
          <div class="pagination-info">
            Showing <strong>{{ paginationStart }}</strong> to <strong>{{ paginationEnd }}</strong> of <strong>{{ sortedList.length }}</strong> items
          </div>
          <div v-if="totalPages > 1" class="pagination-controls">
            <button type="button" class="page-btn nav-btn" :disabled="currentPage <= 1" @click="currentPage--">Previous</button>
            <button v-for="page in totalPages" :key="page" type="button" :class="['page-btn', currentPage === page ? 'active-page' : '']" @click="currentPage = page">{{ page }}</button>
            <button type="button" class="page-btn nav-btn" :disabled="currentPage >= totalPages" @click="currentPage++">Next</button>
          </div>
        </div>
      </div>

      <!-- Add / Edit Communication Modal -->
      <div v-if="showCommModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>{{ isEdit ? 'Edit Communication Record' : 'Add New Communication' }}</h3>
            <button type="button" class="close-btn" @click="showCommModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveComm" class="modal-body">
            <div class="form-group">
              <label for="commType">Communication Type <span class="required-star">*</span></label>
              <select id="commType" v-model="commForm.communication_type" class="input-select" required>
                <option value="Incoming">Incoming Communication</option>
                <option value="Outgoing">Outgoing Communication</option>
              </select>
            </div>

            <div class="form-group">
              <label for="commDate">Communication Date <span class="required-star">*</span></label>
              <input id="commDate" v-model="commForm.communication_date" type="date" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="commSubject">Subject / Title <span class="required-star">*</span></label>
              <input id="commSubject" v-model="commForm.subject" type="text" placeholder="Enter Communication Subject..." required class="input-text" />
            </div>

            <div class="form-group">
              <label for="commOffice">Originating Office <span class="required-star">*</span></label>
              <select id="commOffice" v-model="commForm.office_id" class="input-select" required>
                <option v-for="off in officeList" :key="off.id" :value="off.id">
                  {{ off.office_abbv }} - {{ off.office_name }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="commCat">Category <span class="required-star">*</span></label>
              <select id="commCat" v-model="commForm.category_id" class="input-select" required>
                <option v-for="cat in categoryList" :key="cat.id" :value="cat.id">{{ cat.category_name }}</option>
              </select>
            </div>

            <div class="form-group">
              <label for="commPurpose">Purpose <span class="required-star">*</span></label>
              <select id="commPurpose" v-model="commForm.purpose_id" class="input-select" required>
                <option v-for="p in purposeList" :key="p.id" :value="p.id">{{ p.purpose_name }}</option>
              </select>
            </div>

            <div class="form-group">
              <label for="commStatus">Status <span class="required-star">*</span></label>
              <select id="commStatus" v-model="commForm.status" class="input-select" required>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Released">Released</option>
              </select>
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showCommModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : (isEdit ? 'Update Record' : 'Save Communication') }}
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
  addOutline,
  searchOutline,
  swapVerticalOutline,
  arrowUpOutline,
  arrowDownOutline,
  arrowDownCircleOutline,
  arrowUpCircleOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { formatDate } from '../../utils/dateUtils'

const typeTab = ref<'Incoming' | 'Outgoing'>('Incoming')
const commsList = ref<any[]>([])
const categoryList = ref<any[]>([])
const purposeList = ref<any[]>([])
const officeList = ref<any[]>([])
const loading = ref(true)

const searchQuery = ref('')
const filterCategory = ref(0)
const filterPurpose = ref(0)
const filterStatus = ref('all')

const sortColumn = ref<'communication_date' | 'subject' | 'originating_office' | 'category_name' | 'purpose_name' | 'status' | null>('communication_date')
const sortDirection = ref<'asc' | 'desc'>('desc')

const currentPage = ref(1)
const pageSize = ref(10)

const showCommModal = ref(false)
const isEdit = ref(false)
const saving = ref(false)
const modalError = ref('')

const commForm = ref({
  id: 0,
  communication_type: 'Incoming',
  communication_date: new Date().toISOString().slice(0, 10),
  subject: '',
  office_id: 1,
  category_id: 1,
  purpose_id: 1,
  status: 'Pending'
})

function resolveHostUrl(queryOrPath: string): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/communications/index.php${queryOrPath}`
  }
  return `http://localhost/6IS/backend/api/communications/index.php${queryOrPath}`
}

function resolveInventoryUrl(queryOrPath: string): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/inventory/index.php${queryOrPath}`
  }
  return `http://localhost/6IS/backend/api/inventory/index.php${queryOrPath}`
}

function switchTypeTab(tab: 'Incoming' | 'Outgoing') {
  typeTab.value = tab
  searchQuery.value = ''
  filterCategory.value = 0
  filterPurpose.value = 0
  filterStatus.value = 'all'
  currentPage.value = 1
}

// 1. Filter by active type tab (Incoming vs Outgoing)
const typeFilteredList = computed(() => {
  return commsList.value.filter(item => item.communication_type === typeTab.value)
})

// 2. Filter by search and dropdowns
const filteredList = computed(() => {
  return typeFilteredList.value.filter(item => {
    // Search
    const q = searchQuery.value.trim().toLowerCase()
    const matchesSearch = !q ||
      (item.subject || '').toLowerCase().includes(q) ||
      (item.originating_office || '').toLowerCase().includes(q) ||
      (item.category_name || '').toLowerCase().includes(q) ||
      (item.category_code || '').toLowerCase().includes(q) ||
      (item.purpose_name || '').toLowerCase().includes(q)

    // Category
    let matchesCat = true
    if (filterCategory.value > 0) {
      matchesCat = Number(item.category_id) === Number(filterCategory.value)
    }

    // Purpose
    let matchesPurp = true
    if (filterPurpose.value > 0) {
      matchesPurp = Number(item.purpose_id) === Number(filterPurpose.value)
    }

    // Status
    let matchesStat = true
    if (filterStatus.value !== 'all') {
      matchesStat = item.status === filterStatus.value
    }

    return matchesSearch && matchesCat && matchesPurp && matchesStat
  })
})

// 3. Sorting
const sortedList = computed(() => {
  const list = [...filteredList.value]
  if (!sortColumn.value) return list

  const col = sortColumn.value
  const isAsc = sortDirection.value === 'asc'

  list.sort((a, b) => {
    const valA: any = a[col] ?? ''
    const valB: any = b[col] ?? ''

    if (typeof valA === 'string') {
      const cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
      return isAsc ? cmp : -cmp
    }
    return isAsc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1)
  })

  return list
})

// 4. Pagination
const totalPages = computed(() => Math.ceil(sortedList.value.length / pageSize.value) || 1)
const paginatedList = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return sortedList.value.slice(start, start + pageSize.value)
})
const paginationStart = computed(() => sortedList.value.length === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1)
const paginationEnd = computed(() => Math.min(currentPage.value * pageSize.value, sortedList.value.length))

function toggleSort(col: 'communication_date' | 'subject' | 'originating_office' | 'category_name' | 'purpose_name' | 'status') {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = col
    sortDirection.value = 'asc'
  }
}

function getStatusBadgeStyle(status: string): string {
  const s = (status || '').toLowerCase()
  if (s.includes('completed') || s.includes('released')) return 'status-completed'
  if (s.includes('progress')) return 'status-progress'
  return 'status-pending'
}

async function loadData() {
  loading.value = true
  try {
    const [cRes, catRes, pRes, offRes] = await Promise.all([
      fetch(resolveHostUrl('?view=communications'), { credentials: 'include' }).then(r => r.json()),
      fetch(resolveHostUrl('?view=categories'), { credentials: 'include' }).then(r => r.json()),
      fetch(resolveHostUrl('?view=purposes'), { credentials: 'include' }).then(r => r.json()),
      fetch(resolveInventoryUrl('?view=offices'), { credentials: 'include' }).then(r => r.json())
    ])

    if (cRes.success && Array.isArray(cRes.data)) commsList.value = cRes.data
    if (catRes.success && Array.isArray(catRes.data)) categoryList.value = catRes.data
    if (pRes.success && Array.isArray(pRes.data)) purposeList.value = pRes.data
    if (offRes.success && Array.isArray(offRes.data)) officeList.value = offRes.data
  } catch {
    // ignore network error
  }
  loading.value = false
}

function openAddCommModal() {
  isEdit.value = false
  commForm.value = {
    id: 0,
    communication_type: typeTab.value,
    communication_date: new Date().toISOString().slice(0, 10),
    subject: '',
    office_id: officeList.value[0]?.id || 1,
    category_id: categoryList.value[0]?.id || 1,
    purpose_id: purposeList.value[0]?.id || 1,
    status: typeTab.value === 'Incoming' ? 'Pending' : 'Released'
  }
  modalError.value = ''
  showCommModal.value = true
}

function openEditCommModal(item: any) {
  isEdit.value = true
  commForm.value = {
    id: item.id,
    communication_type: item.communication_type || typeTab.value,
    communication_date: item.communication_date ? item.communication_date.slice(0, 10) : new Date().toISOString().slice(0, 10),
    subject: item.subject || '',
    office_id: item.office_id || 1,
    category_id: item.category_id || 1,
    purpose_id: item.purpose_id || 1,
    status: item.status || 'Pending'
  }
  modalError.value = ''
  showCommModal.value = true
}

async function handleSaveComm() {
  if (!commForm.value.subject.trim()) {
    modalError.value = 'Subject is required.'
    return
  }

  saving.value = true
  modalError.value = ''
  try {
    const action = isEdit.value ? '?action=update_communication' : '?action=create_communication'
    const res = await fetch(resolveHostUrl(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(commForm.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      showCommModal.value = false
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save communication.'
    }
  } catch (err) {
    saving.value = false
    modalError.value = 'Network error.'
  }
}

async function handleSoftDeleteComm(item: any) {
  if (!confirm(`Are you sure you want to soft-delete communication '${item.subject}'? Activity history will remain preserved.`)) return
  try {
    const res = await fetch(resolveHostUrl('?action=delete_communication'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: item.id })
    })
    const data = await res.json()
    if (data.success) loadData()
    else alert(data.message || 'Failed to delete communication.')
  } catch (err) {
    alert('Network error.')
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.admin-comms-container {
  padding: 32px 40px;
  max-width: 1280px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.header-action-bar h2 {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle { font-size: 14px; color: #64748b; margin: 0; }

.action-main-btn {
  background: #082f6d; color: #ffffff; border: none; padding: 10px 18px;
  border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
  box-shadow: 0 2px 6px rgba(8, 47, 109, 0.2); transition: background 0.15s ease;
}
.action-main-btn:hover { background: #1d4ed8; }

.tab-switcher { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.tab-btn {
  background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569; padding: 10px 20px;
  border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.15s ease;
}
.active-tab { background: #eff6ff; border-color: #2563eb; color: #2563eb; }

.table-card {
  background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.filter-controls-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 18px 24px;
  margin-bottom: 24px;
  display: flex;
  align-items: flex-end;
  gap: 16px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
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
  width: 100%;
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
  min-width: 160px;
  box-sizing: border-box;
}

.input-select:focus { border-color: #2563eb; }

.loading-state, .empty-state { text-align: center; padding: 48px; color: #64748b; }
.spinner { display: inline-block; width: 24px; height: 24px; border: 3px solid #cbd5e1; border-radius: 50%; border-top-color: #2563eb; animation: spin 0.8s linear infinite; margin-bottom: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }

.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.data-table th {
  background: #f8fafc; padding: 12px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0;
}
.sortable-th { cursor: pointer; user-select: none; transition: background 0.15s ease; }
.sortable-th:hover { background: #f1f5f9; }
.th-content { display: flex; align-items: center; gap: 6px; }
.center-th { justify-content: center; }
.sort-icon { display: inline-flex; align-items: center; font-size: 14px; color: #2563eb; }
.inactive-sort { color: #cbd5e1; }

.data-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.text-primary { color: #2563eb; }
.code-font { font-family: monospace; color: #475569; }
.text-center { text-align: center; }

.office-tag { background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
.category-tag { background: #f8fafc; border: 1px solid #e2e8f0; color: #1e293b; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; font-family: monospace; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.status-completed { background: #f0fdf4; color: #16a34a; }
.status-progress { background: #fff7ed; color: #c2410c; }
.status-pending { background: #eff6ff; color: #2563eb; }

.action-buttons { display: flex; gap: 8px; justify-content: center; }
.icon-btn {
  width: 32px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease; font-size: 16px;
}
.edit-btn { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
.edit-btn:hover { background: #2563eb; color: #ffffff; }
.delete-btn { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
.delete-btn:hover { background: #dc2626; color: #ffffff; }

.pagination-footer {
  padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
}
.pagination-info { font-size: 13px; color: #64748b; }
.pagination-controls { display: flex; align-items: center; gap: 6px; }
.page-btn {
  min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.page-btn:hover:not(:disabled) { border-color: #2563eb; color: #2563eb; }
.active-page { background: #2563eb; color: #ffffff; border-color: #2563eb; }
.nav-btn { padding: 0 12px; }
.page-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Modal */
.modal-backdrop {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;
}
.modal-card { background: #ffffff; border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; max-height: 80vh; overflow-y: auto; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: #334155; }
.required-star { color: #dc2626; }
.input-text, .input-select { width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; background: #ffffff; }
.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
