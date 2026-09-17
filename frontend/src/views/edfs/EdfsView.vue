<!-- frontend/src/views/edfs/EdfsView.vue -->
<!-- Electronic Document Filing System (EDFS) Account Monitoring Module -->

<template>
  <MainLayout title="EDFS Accounts">
    <div class="edfs-view-container">

      <!-- PAGE HEADER -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-with-badge">
            <h2 class="page-title">EDFS Account Monitoring</h2>
            <span class="system-badge">EDFS</span>
          </div>
          <p class="page-subtitle">
            Directory and credential monitoring for Electronic Document Filing System accounts across offices.
          </p>
        </div>
        <div class="header-actions">
          <button
            v-if="hasPermission('edfs', 'create')"
            class="btn-primary"
            type="button"
            @click="openCreateModal"
          >
            <ion-icon :icon="addOutline" class="btn-icon"></ion-icon>
            Add Account
          </button>
          <button
            class="btn-secondary"
            type="button"
            @click="exportCsv"
          >
            <ion-icon :icon="downloadOutline" class="btn-icon"></ion-icon>
            Export CSV
          </button>
        </div>
      </div>

      <!-- KPI METRIC CARDS -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon-box kpi-blue">
            <ion-icon :icon="documentTextOutline"></ion-icon>
          </div>
          <div class="kpi-content">
            <span class="kpi-label">Total Accounts</span>
            <span class="kpi-value">{{ metrics.total }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box kpi-green">
            <ion-icon :icon="checkmarkCircleOutline"></ion-icon>
          </div>
          <div class="kpi-content">
            <span class="kpi-label">Active Accounts</span>
            <span class="kpi-value">{{ metrics.active }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box kpi-purple">
            <ion-icon :icon="personOutline"></ion-icon>
          </div>
          <div class="kpi-content">
            <span class="kpi-label">Assigned Personnel</span>
            <span class="kpi-value">{{ metrics.assigned }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box kpi-amber">
            <ion-icon :icon="businessOutline"></ion-icon>
          </div>
          <div class="kpi-content">
            <span class="kpi-label">Generic / Desk Roles</span>
            <span class="kpi-value">{{ metrics.generic_desk }}</span>
          </div>
        </div>
      </div>

      <!-- FILTER & SEARCH TOOLBAR -->
      <div class="toolbar-card">
        <div class="search-input-box">
          <ion-icon :icon="searchOutline" class="search-icon"></ion-icon>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name, role, login account, or remarks..."
            class="search-input"
            @input="handleSearchInput"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="clear-search-btn"
            @click="clearSearch"
          >
            <ion-icon :icon="closeCircleOutline"></ion-icon>
          </button>
        </div>

        <div class="filter-controls">
          <!-- Office Filter (Short Name from tbl_offices) -->
          <div class="filter-select-group">
            <label class="filter-label">Office:</label>
            <select v-model="selectedOffice" class="filter-select" @change="applyFilters">
              <option value="">All Offices ({{ offices.length }})</option>
              <option v-for="off in offices" :key="off.office_id" :value="off.office_id">
                {{ off.short_name }} ({{ off.account_count }})
              </option>
            </select>
          </div>

          <!-- Status Filter -->
          <div class="filter-select-group">
            <label class="filter-label">Status:</label>
            <select v-model="selectedStatus" class="filter-select" @change="applyFilters">
              <option value="">All Statuses</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="For Renewal">For Renewal</option>
            </select>
          </div>

          <!-- Type Filter (Personnel vs Generic) -->
          <div class="filter-select-group">
            <label class="filter-label">Account Type:</label>
            <select v-model="selectedType" class="filter-select" @change="applyFilters">
              <option value="">All Types</option>
              <option value="1">Assigned Personnel</option>
              <option value="0">Generic / Desk Roles</option>
            </select>
          </div>
        </div>
      </div>

      <!-- MAIN DATA TABLE -->
      <div class="table-container-card">
        <div v-if="isLoading" class="state-container">
          <div class="spinner"></div>
          <p class="state-text">Loading EDFS accounts...</p>
        </div>

        <div v-else-if="errorMessage" class="state-container state-error">
          <ion-icon :icon="alertCircleOutline" class="state-icon"></ion-icon>
          <p class="state-text">{{ errorMessage }}</p>
          <button class="btn-secondary" type="button" @click="loadData">Try Again</button>
        </div>

        <div v-else-if="accounts.length === 0" class="state-container">
          <ion-icon :icon="documentTextOutline" class="state-icon"></ion-icon>
          <p class="state-title">No Accounts Found</p>
          <p class="state-text">No EDFS accounts match your selected filter criteria.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="edfs-table">
            <thead>
              <tr>
                <th class="th-nr">Nr</th>
                <th class="th-office">Office</th>
                <th class="th-title">Current Title / Role</th>
                <th class="th-personnel">Assigned Personnel</th>
                <th class="th-account">Login Username</th>
                <th class="th-password">Password</th>
                <th class="th-status">Status</th>
                <th class="th-remarks">Remarks</th>
                <th class="th-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in accounts" :key="item.id" class="table-row">
                <td class="td-nr">{{ (pagination.page - 1) * pagination.per_page + idx + 1 }}</td>
                <td class="td-office">
                  <span class="office-tag">{{ item.office_short_name || item.office_name }}</span>
                </td>
                <td class="td-title">
                  <span class="title-bold">{{ item.current_title }}</span>
                </td>
                <td class="td-personnel">
                  <div v-if="item.personnel_name" class="personnel-cell">
                    <ion-icon :icon="personCircleOutline" class="person-icon"></ion-icon>
                    <span>{{ item.personnel_name }}</span>
                  </div>
                  <span v-else class="generic-badge">Generic Desk Role</span>
                </td>
                <td class="td-account">
                  <code v-if="item.username || item.account_name" class="account-code">
                    {{ item.username || item.account_name }}
                  </code>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="td-password">
                  <div v-if="item.password" class="password-cell">
                    <span class="masked-pass">
                      {{ revealedPasswords[item.id] ? item.password : '••••••••' }}
                    </span>
                    <button
                      type="button"
                      class="icon-action-btn"
                      title="Toggle password visibility"
                      @click="togglePassword(item.id)"
                    >
                      <ion-icon :icon="revealedPasswords[item.id] ? eyeOffOutline : eyeOutline"></ion-icon>
                    </button>
                    <button
                      type="button"
                      class="icon-action-btn"
                      title="Copy password"
                      @click="copyToClipboard(item.password, 'Password')"
                    >
                      <ion-icon :icon="copyOutline"></ion-icon>
                    </button>
                  </div>
                  <span v-else class="text-muted text-italic">Not Set</span>
                </td>
                <td class="td-status">
                  <span :class="['status-pill', getStatusClass(item.status)]">
                    {{ item.status }}
                  </span>
                </td>
                <td class="td-remarks">
                  <span :title="item.remarks || ''" class="remarks-text">
                    {{ item.remarks || '—' }}
                  </span>
                </td>
                <td class="td-actions">
                  <div class="action-btn-group">
                    <button
                      v-if="hasPermission('edfs', 'edit')"
                      type="button"
                      class="row-action-btn edit-btn"
                      title="Edit Account"
                      @click="openEditModal(item)"
                    >
                      <ion-icon :icon="createOutline"></ion-icon>
                    </button>
                    <button
                      v-if="hasPermission('edfs', 'delete')"
                      type="button"
                      class="row-action-btn delete-btn"
                      title="Archive / Soft Delete Account"
                      @click="confirmDelete(item)"
                    >
                      <ion-icon :icon="trashOutline"></ion-icon>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PAGINATION BAR -->
        <div v-if="pagination.total_pages > 1" class="pagination-bar">
          <span class="pagination-summary">
            Showing {{ (pagination.page - 1) * pagination.per_page + 1 }} -
            {{ Math.min(pagination.page * pagination.per_page, pagination.total) }} of {{ pagination.total }} records
          </span>
          <div class="pagination-nav">
            <button
              class="page-btn"
              :disabled="pagination.page <= 1"
              @click="changePage(pagination.page - 1)"
            >
              Previous
            </button>
            <span class="page-current">Page {{ pagination.page }} of {{ pagination.total_pages }}</span>
            <button
              class="page-btn"
              :disabled="pagination.page >= pagination.total_pages"
              @click="changePage(pagination.page + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- ADD / EDIT MODAL -->
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3 class="modal-title">{{ isEditing ? 'Edit EDFS Account' : 'Add New EDFS Account' }}</h3>
            <button type="button" class="modal-close-btn" @click="closeModal">
              <ion-icon :icon="closeOutline"></ion-icon>
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="modal-form">
            <div class="form-grid">
              <!-- Office (Selected strictly from tbl_offices, office_name not manually edited) -->
              <div class="form-group">
                <label class="form-label required">Office (Short Name)</label>
                <select v-model="formData.office_id" class="form-input" required>
                  <option :value="null" disabled>Select Office...</option>
                  <option v-for="off in offices" :key="off.office_id" :value="off.office_id">
                    {{ off.short_name }}
                  </option>
                </select>
              </div>

              <!-- Current Title / Role -->
              <div class="form-group">
                <label class="form-label required">Current Title / Role</label>
                <input
                  v-model="formData.current_title"
                  type="text"
                  placeholder="e.g., Message Center, Deputy G4, Chief Clerk"
                  class="form-input"
                  required
                />
              </div>

              <!-- Personnel Name -->
              <div class="form-group">
                <label class="form-label">Assigned Personnel (Rank & Name)</label>
                <input
                  v-model="formData.personnel_name"
                  type="text"
                  placeholder="Leave blank for generic desk / message center role"
                  class="form-input"
                />
              </div>

              <!-- Unified Login Username / Account Name -->
              <div class="form-group">
                <label class="form-label">Login Username / Account Name</label>
                <input
                  v-model="formData.username"
                  type="text"
                  placeholder="e.g., GHQHSCOG4MessageCenter"
                  class="form-input"
                />
              </div>

              <!-- Password -->
              <div class="form-group">
                <label class="form-label">Account Password</label>
                <input
                  v-model="formData.password"
                  type="text"
                  placeholder="Set or update account password"
                  class="form-input"
                />
              </div>

              <!-- Status -->
              <div class="form-group">
                <label class="form-label required">Status</label>
                <select v-model="formData.status" class="form-input" required>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="For Renewal">For Renewal</option>
                </select>
              </div>

              <!-- Remarks -->
              <div class="form-group full-width">
                <label class="form-label">Remarks</label>
                <textarea
                  v-model="formData.remarks"
                  rows="2"
                  placeholder="Notes, replacement records, ticket or authorization references..."
                  class="form-textarea"
                ></textarea>
              </div>
            </div>

            <div v-if="modalError" class="modal-error-box">
              <ion-icon :icon="alertCircleOutline"></ion-icon>
              <span>{{ modalError }}</span>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="isSaving">
                {{ isSaving ? 'Saving...' : (isEditing ? 'Save Changes' : 'Create Account') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- SOFT DELETE CONFIRMATION DIALOG -->
      <div v-if="accountToDelete" class="modal-backdrop" @click.self="accountToDelete = null">
        <div class="modal-dialog modal-dialog-sm">
          <div class="modal-header">
            <h3 class="modal-title text-danger">Archive EDFS Account</h3>
            <button type="button" class="modal-close-btn" @click="accountToDelete = null">
              <ion-icon :icon="closeOutline"></ion-icon>
            </button>
          </div>
          <div class="modal-body-content">
            <p>
              Are you sure you want to archive/delete the account for:
            </p>
            <div class="delete-summary-card">
              <strong>{{ accountToDelete.current_title }}</strong>
              <span>{{ accountToDelete.office_short_name || accountToDelete.office_name }}</span>
              <span v-if="accountToDelete.personnel_name">({{ accountToDelete.personnel_name }})</span>
            </div>
            <p class="delete-warning">
              This record will be soft-deleted and removed from the active monitoring ledger while preserving historical audit logs.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="accountToDelete = null">Cancel</button>
            <button
              type="button"
              class="btn-danger"
              :disabled="isDeleting"
              @click="handleDeleteConfirm"
            >
              {{ isDeleting ? 'Archiving...' : 'Confirm Archive' }}
            </button>
          </div>
        </div>
      </div>

      <!-- TOAST NOTIFICATION -->
      <div v-if="toastMessage" class="toast-notification">
        <ion-icon :icon="checkmarkCircleOutline" class="toast-icon"></ion-icon>
        <span>{{ toastMessage }}</span>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  addOutline,
  downloadOutline,
  searchOutline,
  closeCircleOutline,
  documentTextOutline,
  checkmarkCircleOutline,
  personOutline,
  businessOutline,
  personCircleOutline,
  createOutline,
  trashOutline,
  closeOutline,
  alertCircleOutline,
  eyeOutline,
  eyeOffOutline,
  copyOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { usePermissions } from '../../composables/usePermissions'
import type {
  EdfsAccount,
  EdfsMetrics,
  EdfsOfficeOption,
  EdfsAccountFormData
} from '../../types/edfs'
import {
  fetchEdfsAccounts,
  createEdfsAccount,
  updateEdfsAccount,
  deleteEdfsAccount
} from '../../services/edfsService'

const { hasPermission } = usePermissions()

// State
const accounts = ref<EdfsAccount[]>([])
const offices = ref<EdfsOfficeOption[]>([])
const metrics = reactive<EdfsMetrics>({
  total: 0,
  active: 0,
  inactive: 0,
  for_renewal: 0,
  assigned: 0,
  generic_desk: 0
})

const pagination = reactive({
  total: 0,
  page: 1,
  per_page: 50,
  total_pages: 1
})

const isLoading = ref(true)
const errorMessage = ref<string | null>(null)

// Filters
const searchQuery = ref('')
const selectedOffice = ref('')
const selectedStatus = ref('')
const selectedType = ref('')

// Modal state
const showModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const modalError = ref<string | null>(null)

const formData = reactive<EdfsAccountFormData>({
  office_id: null,
  current_title: '',
  personnel_name: '',
  username: '',
  password: '',
  status: 'Active',
  remarks: ''
})

// Password reveal map
const revealedPasswords = reactive<Record<number, boolean>>({})

// Delete confirm
const accountToDelete = ref<EdfsAccount | null>(null)
const isDeleting = ref(false)

// Toast
const toastMessage = ref<string | null>(null)
let toastTimer: any = null

function showToast(msg: string) {
  toastMessage.value = msg
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = null
  }, 3500)
}

function togglePassword(id: number) {
  revealedPasswords[id] = !revealedPasswords[id]
}

async function copyToClipboard(text: string, label: string) {
  try {
    await navigator.clipboard.writeText(text)
    showToast(`${label} copied to clipboard!`)
  } catch (err) {
    showToast(`Copied: ${text}`)
  }
}

function getStatusClass(status: string) {
  switch (status) {
    case 'Active':
      return 'status-active'
    case 'Inactive':
      return 'status-inactive'
    case 'For Renewal':
      return 'status-renewal'
    default:
      return ''
  }
}

// Data loading
async function loadData() {
  isLoading.value = true
  errorMessage.value = null

  try {
    const res = await fetchEdfsAccounts({
      search: searchQuery.value,
      office: selectedOffice.value,
      status: selectedStatus.value,
      has_personnel: selectedType.value,
      page: pagination.page,
      per_page: pagination.per_page
    })

    accounts.value = res.items
    pagination.total = res.pagination.total
    pagination.page = res.pagination.page
    pagination.per_page = res.pagination.per_page
    pagination.total_pages = res.pagination.total_pages

    metrics.total = res.metrics.total
    metrics.active = res.metrics.active
    metrics.inactive = res.metrics.inactive
    metrics.for_renewal = res.metrics.for_renewal
    metrics.assigned = res.metrics.assigned
    metrics.generic_desk = res.metrics.generic_desk

    if (res.offices) {
      offices.value = res.offices
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to load EDFS accounts.'
  } finally {
    isLoading.value = false
  }
}

let searchDebounceTimer: any = null
function handleSearchInput() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    pagination.page = 1
    loadData()
  }, 300)
}

function clearSearch() {
  searchQuery.value = ''
  pagination.page = 1
  loadData()
}

function applyFilters() {
  pagination.page = 1
  loadData()
}

function changePage(newPage: number) {
  if (newPage >= 1 && newPage <= pagination.total_pages) {
    pagination.page = newPage
    loadData()
  }
}

// Modal open/close
function openCreateModal() {
  isEditing.value = false
  modalError.value = null
  const defaultOfficeId = selectedOffice.value ? parseInt(selectedOffice.value, 10) : (offices.value[0]?.office_id || null)
  Object.assign(formData, {
    id: undefined,
    office_id: defaultOfficeId,
    current_title: '',
    personnel_name: '',
    username: '',
    password: '',
    status: 'Active',
    remarks: ''
  })
  showModal.value = true
}

function openEditModal(item: EdfsAccount) {
  isEditing.value = true
  modalError.value = null
  Object.assign(formData, {
    id: item.id,
    office_id: item.office_id,
    current_title: item.current_title,
    personnel_name: item.personnel_name || '',
    username: item.username || item.account_name || '',
    password: item.password || '',
    status: item.status,
    remarks: item.remarks || ''
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  modalError.value = null
}

async function handleSubmit() {
  if (!formData.office_id || !formData.current_title.trim()) {
    modalError.value = 'Office selection and Current Title are required.'
    return
  }

  isSaving.value = true
  modalError.value = null

  try {
    if (isEditing.value && formData.id) {
      await updateEdfsAccount(formData)
      showToast('EDFS account updated successfully!')
    } else {
      await createEdfsAccount(formData)
      showToast('EDFS account created successfully!')
    }
    closeModal()
    loadData()
  } catch (err: any) {
    modalError.value = err.message || 'Failed to save account.'
  } finally {
    isSaving.value = false
  }
}

// Delete
function confirmDelete(item: EdfsAccount) {
  accountToDelete.value = item
}

async function handleDeleteConfirm() {
  if (!accountToDelete.value) return
  isDeleting.value = true

  try {
    await deleteEdfsAccount(accountToDelete.value.id)
    showToast('EDFS account archived successfully!')
    accountToDelete.value = null
    loadData()
  } catch (err: any) {
    alert(err.message || 'Failed to archive account.')
  } finally {
    isDeleting.value = false
  }
}

// Export CSV
function exportCsv() {
  if (accounts.value.length === 0) {
    showToast('No accounts to export.')
    return
  }

  const headers = ['Nr', 'Office', 'Current Title / Role', 'Assigned Personnel', 'Login Username', 'Status', 'Remarks']
  const rows = accounts.value.map((item, idx) => [
    (idx + 1).toString(),
    `"${(item.office_short_name || item.office_name || '').replace(/"/g, '""')}"`,
    `"${(item.current_title || '').replace(/"/g, '""')}"`,
    `"${(item.personnel_name || '').replace(/"/g, '""')}"`,
    `"${(item.username || item.account_name || '').replace(/"/g, '""')}"`,
    `"${(item.status || '').replace(/"/g, '""')}"`,
    `"${(item.remarks || '').replace(/"/g, '""')}"`
  ])

  const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `EDFS_Accounts_Export_${new Date().toISOString().substring(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  showToast('Exported EDFS accounts to CSV!')
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.edfs-view-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px;
  background-color: var(--color-background);
  min-height: 100vh;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}

.title-with-badge {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  margin: 0;
}

.system-badge {
  background-color: #EFF6FF;
  color: var(--color-primary);
  border: 1px solid #BFDBFE;
  padding: 2px 10px;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  margin: 4px 0 0 0;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.btn-icon {
  font-size: 1.1rem;
}

/* KPI Cards */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 16px;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.kpi-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: var(--radius-sm);
  font-size: 1.5rem;
}

.kpi-blue {
  background-color: #EFF6FF;
  color: #2563EB;
}

.kpi-green {
  background-color: #F0FDF4;
  color: #16A34A;
}

.kpi-purple {
  background-color: #FAF5FF;
  color: #9333EA;
}

.kpi-amber {
  background-color: #FFFBEB;
  color: #D97706;
}

.kpi-content {
  display: flex;
  flex-direction: column;
}

.kpi-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

.kpi-value {
  font-size: 1.6rem;
  font-weight: 800;
  color: var(--color-primary-dark);
  line-height: 1.2;
}

/* Toolbar */
.toolbar-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 14px 20px;
  flex-wrap: wrap;
}

.search-input-box {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 280px;
}

.search-icon {
  position: absolute;
  left: 12px;
  font-size: 1.15rem;
  color: var(--color-text-secondary);
}

.search-input {
  width: 100%;
  padding: 8px 36px 8px 36px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  color: var(--color-text);
  outline: none;
  background-color: #FAFAFA;
}

.search-input:focus {
  border-color: var(--color-primary-light);
  background-color: #FFFFFF;
}

.clear-search-btn {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  font-size: 1.1rem;
  color: var(--color-text-secondary);
  cursor: pointer;
}

.filter-controls {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.filter-select-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filter-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.filter-select {
  padding: 6px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  color: var(--color-text);
  background-color: #FFFFFF;
  outline: none;
  cursor: pointer;
}

.filter-select:focus {
  border-color: var(--color-primary-light);
}

/* Table Card */
.table-container-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.table-responsive {
  overflow-x: auto;
}

.edfs-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
  text-align: left;
}

.edfs-table th {
  background-color: #F8FAFC;
  color: var(--color-primary-dark);
  font-weight: 700;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 14px;
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
}

.edfs-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
}

.table-row:hover {
  background-color: var(--color-surface-hover);
}

.td-nr {
  font-weight: 600;
  color: var(--color-text-secondary);
  width: 40px;
}

.office-tag {
  display: inline-block;
  padding: 3px 8px;
  background-color: #EFF6FF;
  color: var(--color-primary);
  border: 1px solid #DBEAFE;
  border-radius: var(--radius-sm);
  font-weight: 700;
  font-size: 0.75rem;
  white-space: nowrap;
}

.title-bold {
  display: block;
  font-weight: 600;
  color: var(--color-text);
}

.personnel-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
}

.person-icon {
  font-size: 1.25rem;
  color: var(--color-primary);
  flex-shrink: 0;
}

.generic-badge {
  display: inline-block;
  padding: 2px 8px;
  background-color: #F1F5F9;
  color: #64748B;
  border-radius: var(--radius-sm);
  font-size: 0.72rem;
  font-style: italic;
  font-weight: 500;
}

.account-code {
  font-family: monospace;
  font-size: 0.8rem;
  padding: 2px 6px;
  background-color: #F1F5F9;
  border: 1px solid #E2E8F0;
  border-radius: 4px;
  color: #0F172A;
}

.password-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.masked-pass {
  font-family: monospace;
  font-size: 0.85rem;
  color: #475569;
  letter-spacing: 0.1em;
}

.icon-action-btn {
  background: none;
  border: none;
  font-size: 1.05rem;
  color: var(--color-text-secondary);
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
}

.icon-action-btn:hover {
  color: var(--color-primary);
}

.status-pill {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
}

.status-active {
  background-color: #DCFCE7;
  color: #15803D;
  border: 1px solid #BBF7D0;
}

.status-inactive {
  background-color: #F1F5F9;
  color: #64748B;
  border: 1px solid #E2E8F0;
}

.status-renewal {
  background-color: #FEF3C7;
  color: #B45309;
  border: 1px solid #FDE68A;
}

.remarks-text {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  max-width: 220px;
  font-size: 0.8rem;
  color: var(--color-text-secondary);
}

.action-btn-group {
  display: flex;
  gap: 6px;
}

.row-action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background-color: #FFFFFF;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.15s ease;
}

.edit-btn {
  color: var(--color-primary);
}

.edit-btn:hover {
  background-color: #EFF6FF;
  border-color: var(--color-primary-light);
}

.delete-btn {
  color: #DC2626;
}

.delete-btn:hover {
  background-color: #FEF2F2;
  border-color: #F87171;
}

/* Pagination */
.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  border-top: 1px solid var(--color-border);
  background-color: #F8FAFC;
  flex-wrap: wrap;
  gap: 10px;
}

.pagination-summary {
  font-size: 0.8rem;
  color: var(--color-text-secondary);
}

.pagination-nav {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-btn {
  padding: 4px 12px;
  border: 1px solid var(--color-border);
  background-color: #FFFFFF;
  border-radius: var(--radius-sm);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-current {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text);
}

/* States */
.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  gap: 12px;
}

.state-icon {
  font-size: 3rem;
  color: var(--color-text-secondary);
}

.state-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  margin: 0;
}

.state-text {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  margin: 0;
}

.state-error {
  color: #DC2626;
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #E2E8F0;
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}

.modal-dialog {
  background-color: #FFFFFF;
  border-radius: var(--radius-lg);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: 100%;
  max-width: 680px;
  overflow: hidden;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal-dialog-sm {
  max-width: 480px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid var(--color-border);
  background-color: #F8FAFC;
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  margin: 0;
}

.modal-close-btn {
  background: none;
  border: none;
  font-size: 1.4rem;
  color: var(--color-text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-form {
  padding: 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.full-width {
  grid-column: 1 / -1;
}

.form-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-text);
}

.required::after {
  content: ' *';
  color: #DC2626;
}

.form-input, .form-textarea {
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  color: var(--color-text);
  outline: none;
  background-color: #FFFFFF;
}

.form-input:focus, .form-textarea:focus {
  border-color: var(--color-primary-light);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.modal-error-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background-color: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: var(--radius-sm);
  color: #DC2626;
  font-size: 0.85rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
}

.modal-body-content {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.delete-summary-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 16px;
  background-color: #F8FAFC;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
}

.delete-warning {
  color: var(--color-text-secondary);
  font-size: 0.8rem;
  margin: 0;
}

.text-danger {
  color: #DC2626 !important;
}

/* Toast */
.toast-notification {
  position: fixed;
  bottom: 24px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  background-color: #172554;
  color: #FFFFFF;
  padding: 12px 20px;
  border-radius: var(--radius-md);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
  font-size: 0.875rem;
  font-weight: 500;
  z-index: 10000;
  animation: slideUp 0.25s ease-out;
}

.toast-icon {
  font-size: 1.25rem;
  color: #4ADE80;
}

@keyframes slideUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
  }
}
</style>
