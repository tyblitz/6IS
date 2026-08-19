<template>
  <MainLayout title="Communication Categories">
    <div class="admin-reference-container">
      
      <!-- Header Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Communication Categories Management</h2>
          <p class="subtitle">Maintain communication document categories (e.g. Disposition Form, Memorandum, SOP).</p>
        </div>

        <div class="header-buttons">
          <button type="button" class="action-main-btn" @click="openAddModal">
            <ion-icon :icon="addOutline" />
            <span>Add Category</span>
          </button>
        </div>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="catSearch">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="catSearch"
              type="text"
              v-model="searchQuery"
              @input="currentPage = 1"
              placeholder="Search category name or code..."
              class="input-search"
            />
          </div>
        </div>

        <div class="filter-group">
          <label for="catStatusFilter">Status</label>
          <select id="catStatusFilter" v-model="filterStatus" @change="currentPage = 1" class="input-select">
            <option value="all">-Select-</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
          </select>
        </div>
      </div>

      <!-- Table Card -->
      <div class="table-card">

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading categories...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="sortedList.length === 0" class="empty-state">
          <p v-if="searchQuery || filterStatus !== 'all'">No categories match your filters.</p>
          <p v-else>No communication categories registered yet.</p>
        </div>

        <!-- Data Table -->
        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="sortable-th" @click="toggleSort('id')">
                  <div class="th-content">
                    <span>ID</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'id' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'id' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th" @click="toggleSort('category_name')">
                  <div class="th-content">
                    <span>Category Name</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'category_name' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'category_name' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th" @click="toggleSort('code')">
                  <div class="th-content">
                    <span>Code / Abbreviation</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'code' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'code' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="sortable-th text-center" @click="toggleSort('is_active')">
                  <div class="th-content center-th">
                    <span>Active Status</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'is_active' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'is_active' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <th class="text-center" style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedList" :key="item.id">
                <td class="font-bold code-font">{{ item.id }}</td>
                <td class="font-bold text-primary">{{ item.category_name }}</td>
                <td class="code-font">{{ item.code || '-' }}</td>
                <td class="text-center">
                  <span :class="['status-badge', item.is_active ? 'status-active' : 'status-inactive']">
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="text-center">
                  <div class="action-buttons">
                    <button class="icon-btn edit-btn" title="Edit Category" @click="openEditModal(item)">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button class="icon-btn delete-btn" title="Delete Category" @click="handleDelete(item)">
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

      <!-- Add / Edit Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>{{ isEdit ? 'Edit Communication Category' : 'Add New Category' }}</h3>
            <button type="button" class="close-btn" @click="showModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSave" class="modal-body">
            <div class="form-group">
              <label for="catName">Category Name <span class="required-star">*</span></label>
              <input id="catName" v-model="form.category_name" type="text" placeholder="e.g. Disposition Form, Memorandum" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="catCode">Abbreviation / Code</label>
              <input id="catCode" v-model="form.code" type="text" placeholder="e.g. DF, MEMO" class="input-text code-font" />
            </div>

            <div class="form-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_active" />
                <span>Is Active Category</span>
              </label>
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : (isEdit ? 'Update Category' : 'Save Category') }}
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
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  addOutline,
  searchOutline,
  swapVerticalOutline,
  arrowUpOutline,
  arrowDownOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'

const router = useRouter()
const items = ref<any[]>([])
const loading = ref(true)
const saving = ref(false)

const searchQuery = ref('')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')
const sortColumn = ref<'id' | 'category_name' | 'code' | 'is_active' | null>('category_name')
const sortDirection = ref<'asc' | 'desc'>('asc')

const currentPage = ref(1)
const pageSize = ref(20)

const showModal = ref(false)
const isEdit = ref(false)
const modalError = ref('')
const form = ref({
  id: 0,
  category_name: '',
  code: '',
  is_active: true
})

function resolveHostUrl(queryOrPath: string): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/communications/index.php${queryOrPath}`
  }
  return `http://localhost/6IS/backend/api/communications/index.php${queryOrPath}`
}

const filteredList = computed(() => {
  return items.value.filter(item => {
    // Search
    const q = searchQuery.value.trim().toLowerCase()
    const matchesSearch = !q ||
      item.category_name.toLowerCase().includes(q) ||
      (item.code || '').toLowerCase().includes(q)
    
    // Status Filter
    let matchesStatus = true
    if (filterStatus.value === 'active') matchesStatus = Boolean(item.is_active)
    if (filterStatus.value === 'inactive') matchesStatus = !Boolean(item.is_active)

    return matchesSearch && matchesStatus
  })
})

const sortedList = computed(() => {
  const list = [...filteredList.value]
  if (!sortColumn.value) return list

  const col = sortColumn.value
  const isAsc = sortDirection.value === 'asc'

  list.sort((a, b) => {
    let valA: any = a[col] ?? ''
    let valB: any = b[col] ?? ''

    if (typeof valA === 'string') {
      const cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
      return isAsc ? cmp : -cmp
    }
    return isAsc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1)
  })

  return list
})

const totalPages = computed(() => Math.ceil(sortedList.value.length / pageSize.value) || 1)
const paginatedList = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return sortedList.value.slice(start, start + pageSize.value)
})
const paginationStart = computed(() => sortedList.value.length === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1)
const paginationEnd = computed(() => Math.min(currentPage.value * pageSize.value, sortedList.value.length))

function toggleSort(col: 'id' | 'category_name' | 'code' | 'is_active') {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = col
    sortDirection.value = 'asc'
  }
}

async function loadData() {
  loading.value = true
  try {
    const res = await fetch(resolveHostUrl('?view=categories'), { credentials: 'include' })
    const data = await res.json()
    if (data.success && Array.isArray(data.data)) {
      items.value = data.data
    }
  } catch (err) {}
  loading.value = false
}

function openAddModal() {
  isEdit.value = false
  form.value = { id: 0, category_name: '', code: '', is_active: true }
  modalError.value = ''
  showModal.value = true
}

function openEditModal(item: any) {
  isEdit.value = true
  form.value = {
    id: item.id,
    category_name: item.category_name,
    code: item.code || '',
    is_active: Boolean(item.is_active)
  }
  modalError.value = ''
  showModal.value = true
}

async function handleSave() {
  if (!form.value.category_name.trim()) {
    modalError.value = 'Category name is required.'
    return
  }

  saving.value = true
  modalError.value = ''

  try {
    const res = await fetch(resolveHostUrl('?action=save_category'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      showModal.value = false
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save category.'
    }
  } catch (err) {
    saving.value = false
    modalError.value = 'Network error.'
  }
}

async function handleDelete(item: any) {
  if (!confirm(`Are you sure you want to delete category '${item.category_name}'?`)) return

  try {
    const res = await fetch(resolveHostUrl('?action=delete_category'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: item.id })
    })
    const data = await res.json()
    if (data.success) loadData()
    else alert(data.message || 'Failed to delete category.')
  } catch (err) {
    alert('Network error.')
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.admin-reference-container {
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

.header-buttons {
  display: flex;
  align-items: center;
  gap: 12px;
}

.action-main-btn {
  background: #082f6d; color: #ffffff; border: none; padding: 10px 18px; border-radius: 8px;
  font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
  box-shadow: 0 2px 6px rgba(8, 47, 109, 0.2); transition: background 0.15s ease;
}
.action-main-btn:hover { background: #1d4ed8; }

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
.text-primary { color: #2563eb; }
.code-font { font-family: monospace; color: #475569; }
.text-center { text-align: center; }

.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.status-active { background: #f0fdf4; color: #16a34a; }
.status-inactive { background: #f1f5f9; color: #64748b; }

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
.modal-card { background: #ffffff; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: #334155; }
.required-star { color: #dc2626; }
.input-text { width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; background: #ffffff; }
.checkbox-group { margin-top: 4px; }
.checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #334155; cursor: pointer; }
.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
