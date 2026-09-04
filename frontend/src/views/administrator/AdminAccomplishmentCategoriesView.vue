<template>
  <MainLayout title="Accomplishment Categories">
    <div class="categories-container">
      
      <!-- Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Accomplishment Categories</h2>
          <p class="subtitle">Manage accomplishment categories</p>
        </div>

        <button class="action-main-btn" @click="openCreateModal">
          <ion-icon :icon="addOutline" />
          <span>Add New Category</span>
        </button>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="searchFilter">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="searchFilter"
              v-model="searchQuery"
              type="text"
              placeholder="Search category name or category code..."
              class="input-search"
            />
          </div>
        </div>

        <div v-if="searchQuery" class="filter-actions">
          <button class="reset-filter-btn" @click="searchQuery = ''">
            <ion-icon :icon="closeCircleOutline" />
            <span>Reset</span>
          </button>
        </div>
      </div>

      <!-- Categories Data Table Card -->
      <div class="table-card">

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading categories...</p>
        </div>

        <div v-else-if="filteredAndSortedCategories.length === 0" class="empty-state">
          <p v-if="searchQuery">No categories match your search query "{{ searchQuery }}".</p>
          <p v-else>No accomplishment categories found.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="sortable-th" @click="toggleSort('category_name')">
                  <div class="th-content">
                    <span>Category Name</span>
                    <ion-icon :icon="getSortIcon('category_name')" class="sort-icon" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('category_code')">
                  <div class="th-content">
                    <span>Category Code</span>
                    <ion-icon :icon="getSortIcon('category_code')" class="sort-icon" />
                  </div>
                </th>
                <th class="text-center" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in paginatedItems" :key="cat.id">
                <td class="font-bold">{{ cat.category_name }}</td>
                <td><span class="code-badge">{{ cat.category_code || 'N/A' }}</span></td>
                <td class="text-center">
                  <div class="action-buttons">
                    <button class="icon-btn edit-btn" @click="openEditModal(cat)" title="Edit Category">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button class="icon-btn delete-btn" @click="handleDeleteCategory(cat)" title="Delete Category">
                      <ion-icon :icon="trashOutline" />
                    </button>
                  </div>
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

      <!-- Create / Edit Category Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>{{ isEditMode ? 'Edit Accomplishment Category' : 'Add New Category' }}</h3>
            <button class="close-btn" @click="closeModal">&times;</button>
          </div>

          <form @submit.prevent="handleSave" class="modal-body">
            <div class="form-group">
              <label for="catName">Category Name</label>
              <input
                id="catName"
                v-model="form.category_name"
                type="text"
                required
                placeholder="e.g. Installation of Public Address System (PAS)"
                class="input-text"
              />
            </div>

            <div class="form-group">
              <label for="catCode">Category Code (Short Tag)</label>
              <input
                id="catCode"
                v-model="form.category_code"
                type="text"
                placeholder="e.g. PAS"
                class="input-text"
              />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="closeModal">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : (isEditMode ? 'Update Category' : 'Create Category') }}
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
  createOutline,
  trashOutline,
  searchOutline,
  closeCircleOutline,
  swapVerticalOutline,
  arrowUpOutline,
  arrowDownOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { useTablePagination } from '../../composables/useTablePagination'
import TablePagination from '../../components/common/TablePagination.vue'
import { apiFetch, resolveApiUrl } from '../../utils/api'

interface CategoryItem {
  id: number
  category_name: string
  category_code: string | null
  created_at: string
  updated_at: string
}

const API_BASE_URL = resolveApiUrl('accomplishments/index.php')

const categories = ref<CategoryItem[]>([])
const loading = ref(true)

// Search & Sort States
const searchQuery = ref('')
const sortColumn = ref<'category_name' | 'category_code'>('category_name')
const sortDirection = ref<'asc' | 'desc'>('asc')

const showModal = ref(false)
const isEditMode = ref(false)
const editId = ref(0)
const saving = ref(false)
const modalError = ref('')

const form = ref({
  category_name: '',
  category_code: ''
})

const filteredAndSortedCategories = computed(() => {
  let result = [...categories.value]

  // Filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    result = result.filter(c =>
      c.category_name.toLowerCase().includes(q) ||
      (c.category_code && c.category_code.toLowerCase().includes(q))
    )
  }

  // Sort
  result.sort((a, b) => {
    const valA = (a[sortColumn.value] || '').toString().toLowerCase()
    const valB = (b[sortColumn.value] || '').toString().toLowerCase()
    if (valA < valB) return sortDirection.value === 'asc' ? -1 : 1
    if (valA > valB) return sortDirection.value === 'asc' ? 1 : -1
    return 0
  })

  return result
})

const {
  currentPage,
  totalItems,
  totalPages,
  startIndex,
  endIndex,
  paginatedItems,
  setPage
} = useTablePagination(filteredAndSortedCategories, { pageSize: 10 })

function toggleSort(col: 'category_name' | 'category_code') {
  if (sortColumn.value === col) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = col
    sortDirection.value = 'asc'
  }
}

function getSortIcon(col: 'category_name' | 'category_code') {
  if (sortColumn.value !== col) return swapVerticalOutline
  return sortDirection.value === 'asc' ? arrowUpOutline : arrowDownOutline
}

async function loadCategories() {
  loading.value = true
  try {
    const res = await apiFetch(`${API_BASE_URL}?view=categories`)
    const data = await res.json()
    if (data.success) {
      categories.value = data.data
    }
  } catch {
    // ignore network error
  }
  loading.value = false
}

function openCreateModal() {
  isEditMode.value = false
  editId.value = 0
  form.value = { category_name: '', category_code: '' }
  modalError.value = ''
  showModal.value = true
}

function openEditModal(cat: CategoryItem) {
  isEditMode.value = true
  editId.value = cat.id
  form.value = {
    category_name: cat.category_name,
    category_code: cat.category_code || ''
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSave() {
  saving.value = true
  modalError.value = ''
  const action = isEditMode.value ? 'update_category' : 'create_category'
  const payload = {
    id: editId.value,
    ...form.value
  }

  try {
    const res = await apiFetch(`${API_BASE_URL}?action=${action}`, {
      method: 'POST',
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      closeModal()
      loadCategories()
    } else {
      modalError.value = data.message || 'Failed to save category.'
    }
  } catch (err: any) {
    saving.value = false
    modalError.value = err.message || 'Network error.'
  }
}

async function handleDeleteCategory(cat: CategoryItem) {
  if (!confirm(`Are you sure you want to delete category '${cat.category_name}'?`)) return

  try {
    const res = await apiFetch(`${API_BASE_URL}?action=delete_category`, {
      method: 'POST',
      body: JSON.stringify({ id: cat.id })
    })
    const data = await res.json()
    if (data.success) {
      loadCategories()
    } else {
      alert(data.message || 'Failed to delete category.')
    }
  } catch (err) {
    alert('Network error.')
  }
}

onMounted(() => {
  loadCategories()
})
</script>

<style scoped>
.categories-container {
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

.action-main-btn {
  background: #082f6d;
  color: #ffffff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background 0.15s ease;
}

.action-main-btn:hover {
  background: #1d4ed8;
}

/* Search Bar Card */
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

.input-search:focus {
  border-color: #2563eb;
}

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

.reset-filter-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

/* Table Card */
.table-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
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
  padding: 14px 20px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e2e8f0;
}

.sortable-th {
  cursor: pointer;
  user-select: none;
  transition: background 0.15s ease;
}

.sortable-th:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.th-content {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sort-icon {
  font-size: 16px;
  color: #2563eb;
}

.data-table td {
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}

.font-bold { font-weight: 700; }
.text-center { text-align: center; }

.code-badge {
  background: #f1f5f9;
  color: #0f172a;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  font-family: monospace;
}

.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.icon-btn {
  width: 32px; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff;
  display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s ease; font-size: 16px;
}

.edit-btn { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.edit-btn:hover { background: #2563eb; color: #ffffff; }

.delete-btn { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.delete-btn:hover { background: #dc2626; color: #ffffff; }

/* Modal Styles */
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
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
  max-width: 480px;
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

.input-text {
  width: 100%;
  padding: 10px 14px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  box-sizing: border-box;
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

.save-btn:hover { background: #1d4ed8; }
</style>
