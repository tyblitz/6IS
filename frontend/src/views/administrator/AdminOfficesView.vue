<template>
  <MainLayout title="Office Management">
    <div class="offices-container">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Offices Management</h2>
          <p class="subtitle">Maintain organizational units, official office codes, location addresses, and user associations.</p>
        </div>

        <button v-if="canCreate" class="btn-primary" @click="openCreateModal">
          <ion-icon :icon="addCircleOutline" />
          <span>Add New Office</span>
        </button>
      </div>

      <!-- Filter Controls -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="officeSearch">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="officeSearch"
              v-model="searchQuery"
              type="text"
              placeholder="Search code or office name..."
              class="input-search"
            />
          </div>
        </div>

        <div class="filter-group">
          <label for="officeStatusFilter">Status</label>
          <select id="officeStatusFilter" v-model="filterStatus" class="input-select">
            <option value="all">All Statuses</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
          </select>
        </div>
      </div>

      <!-- Offices Table Card -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Registered Organizational Offices</h3>
          <span class="count-badge">{{ filteredOffices.length }} Total</span>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading offices directory...</p>
        </div>

        <div v-else-if="filteredOffices.length === 0" class="empty-state">
          <ion-icon :icon="businessOutline" class="empty-icon" />
          <p>No organizational offices found matching your criteria.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-code">Code</th>
                <th class="col-name">Office Name</th>
                <th class="col-users text-center">Assigned Users</th>
                <th class="col-contact">Contact Details</th>
                <th class="col-status text-center">Status</th>
                <th class="col-actions text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="office in paginatedOffices" :key="office.id">
                <td>
                  <span class="office-code-badge">{{ office.code }}</span>
                </td>
                <td>
                  <div class="office-name-cell">
                    <span class="office-name-text">{{ office.name }}</span>
                    <span v-if="office.description" class="office-desc-text">{{ office.description }}</span>
                  </div>
                </td>
                <td class="text-center">
                  <span :class="['user-count-pill', (office.user_count || 0) > 0 ? 'pill-has-users' : 'pill-empty']">
                    {{ office.user_count || 0 }} {{ (office.user_count || 0) === 1 ? 'user' : 'users' }}
                  </span>
                </td>
                <td>
                  <div class="contact-cell">
                    <span v-if="office.contact_number" class="contact-line">
                      <ion-icon :icon="callOutline" /> {{ office.contact_number }}
                    </span>
                    <span v-if="office.email" class="contact-line">
                      <ion-icon :icon="mailOutline" /> {{ office.email }}
                    </span>
                    <span v-if="!office.contact_number && !office.email" class="empty-muted">—</span>
                  </div>
                </td>
                <td class="text-center">
                  <span :class="['status-pill', office.is_active ? 'pill-active' : 'pill-inactive']">
                    {{ office.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="text-right actions-cell">
                  <button 
                    v-if="canEdit" 
                    class="action-btn edit-btn" 
                    title="Edit Office" 
                    @click="openEditModal(office)"
                  >
                    <ion-icon :icon="createOutline" />
                    <span>Edit</span>
                  </button>

                  <button
                    v-if="canEdit"
                    :class="['action-btn', office.is_active ? 'deactivate-btn' : 'activate-btn']"
                    :title="office.is_active ? 'Deactivate Office' : 'Activate Office'"
                    @click="handleToggleActive(office)"
                  >
                    <ion-icon :icon="office.is_active ? banOutline : checkmarkCircleOutline" />
                    <span>{{ office.is_active ? 'Deactivate' : 'Activate' }}</span>
                  </button>

                  <button
                    v-if="canDelete"
                    class="action-btn delete-btn"
                    title="Delete Office"
                    @click="promptDelete(office)"
                  >
                    <ion-icon :icon="trashOutline" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TablePagination
          v-if="filteredOffices.length > 0"
          :current-page="currentPage"
          :total-pages="totalPages"
          :total-items="totalItems"
          :start-index="startIndex"
          :end-index="endIndex"
          @change-page="setPage"
        />
      </div>

      <!-- Add / Edit Office Modal -->
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3>{{ isEditMode ? 'Edit Office Details' : 'Register New Office' }}</h3>
            <button class="close-btn" @click="closeModal">&times;</button>
          </div>

          <form @submit.prevent="handleSaveOffice">
            <div class="modal-body">
              <div class="form-row">
                <div class="form-group flex-1">
                  <label for="offCode">Office Code *</label>
                  <input
                    id="offCode"
                    v-model="form.code"
                    type="text"
                    required
                    maxlength="50"
                    placeholder="e.g. OG1, ICTO, HQ"
                    class="input-text uppercase-input"
                  />
                </div>

                <div class="form-group flex-2">
                  <label for="offName">Office Name *</label>
                  <input
                    id="offName"
                    v-model="form.name"
                    type="text"
                    required
                    maxlength="150"
                    placeholder="e.g. Office of the Assistant Chief of Staff for Personnel"
                    class="input-text"
                  />
                </div>
              </div>

              <div class="form-row">
                <div class="form-group flex-1">
                  <label for="offContact">Contact Number</label>
                  <input
                    id="offContact"
                    v-model="form.contact_number"
                    type="text"
                    maxlength="50"
                    placeholder="e.g. +63 912 345 6789"
                    class="input-text"
                  />
                </div>

                <div class="form-group flex-1">
                  <label for="offEmail">Official Email</label>
                  <input
                    id="offEmail"
                    v-model="form.email"
                    type="email"
                    maxlength="100"
                    placeholder="e.g. og1@6id.mil.ph"
                    class="input-text"
                  />
                </div>
              </div>

              <div class="form-group">
                <label for="offAddress">Office Location / Address</label>
                <input
                  id="offAddress"
                  v-model="form.address"
                  type="text"
                  placeholder="e.g. Building 4, Division Headquarters"
                  class="input-text"
                />
              </div>

              <div class="form-group">
                <label for="offDesc">Description / Mandate</label>
                <textarea
                  id="offDesc"
                  v-model="form.description"
                  rows="2"
                  placeholder="Brief functional scope of this office..."
                  class="input-textarea"
                ></textarea>
              </div>

              <div v-if="modalError" class="modal-error">
                <ion-icon :icon="alertCircleOutline" />
                <span>{{ modalError }}</span>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                {{ saving ? 'Saving...' : (isEditMode ? 'Update Office' : 'Create Office') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal && targetOffice" class="modal-backdrop" @click.self="closeDeleteModal">
        <div class="modal-dialog delete-dialog">
          <div class="modal-header">
            <h3 class="danger-title">Delete Office Confirmation</h3>
            <button class="close-btn" @click="closeDeleteModal">&times;</button>
          </div>

          <div class="modal-body">
            <p>
              Are you sure you want to delete office <strong>{{ targetOffice.code }}</strong> ({{ targetOffice.name }})?
            </p>

            <div v-if="(targetOffice.user_count || 0) > 0" class="warning-alert">
              <ion-icon :icon="alertCircleOutline" />
              <div class="warning-alert-content">
                <span>
                  <strong>Warning:</strong> This office currently has <strong>{{ targetOffice.user_count }}</strong> assigned user account(s). 
                  Offices with assigned users cannot be deleted. Please reassign the users or deactivate the office instead.
                </span>
                <div v-if="targetOffice.is_active" class="modal-deactivate-hint">
                  <button type="button" class="btn-warning-sm" @click="handleDeactivateFromModal">
                    <ion-icon :icon="banOutline" />
                    <span>Deactivate Office Instead</span>
                  </button>
                </div>
              </div>
            </div>

            <p class="notice-muted">
              Note: If this office has historical records in other modules, deletion will be rejected to preserve audit integrity. Deactivation is recommended.
            </p>

            <div v-if="deleteError" class="modal-error">
              <ion-icon :icon="alertCircleOutline" />
              <div class="delete-error-details">
                <span class="delete-error-message">{{ deleteError }}</span>
                <div v-if="targetOffice.is_active" class="modal-deactivate-hint">
                  <button type="button" class="btn-warning-sm" @click="handleDeactivateFromModal">
                    <ion-icon :icon="banOutline" />
                    <span>Deactivate Office Instead</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="closeDeleteModal">Cancel</button>
            <button 
              type="button" 
              class="btn-danger" 
              :disabled="deleting || (targetOffice.user_count || 0) > 0" 
              @click="confirmDelete"
            >
              {{ deleting ? 'Deleting...' : 'Delete Office' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  businessOutline,
  addCircleOutline,
  searchOutline,
  createOutline,
  banOutline,
  checkmarkCircleOutline,
  trashOutline,
  callOutline,
  mailOutline,
  alertCircleOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import TablePagination from '../../components/common/TablePagination.vue'
import { usePermissions } from '../../composables/usePermissions'
import { useTablePagination } from '../../composables/useTablePagination'
import {
  fetchOffices,
  createOffice,
  updateOffice,
  deleteOffice,
  toggleOfficeActive
} from '../../services/officeService'
import type { Office } from '../../types/office'

const { hasPermission } = usePermissions()

const canCreate = computed(() => hasPermission('offices', 'create'))
const canEdit = computed(() => hasPermission('offices', 'edit'))
const canDelete = computed(() => hasPermission('offices', 'delete'))

const offices = ref<Office[]>([])
const loading = ref(true)

const searchQuery = ref('')
const filterStatus = ref('all')

const showModal = ref(false)
const isEditMode = ref(false)
const saving = ref(false)
const modalError = ref('')

const showDeleteModal = ref(false)
const targetOffice = ref<Office | null>(null)
const deleting = ref(false)
const deleteError = ref('')

const form = ref({
  id: 0,
  code: '',
  name: '',
  description: '',
  address: '',
  contact_number: '',
  email: ''
})

const filteredOffices = computed(() => {
  return offices.value.filter((off) => {
    // Status filter
    if (filterStatus.value === 'active' && !off.is_active) return false
    if (filterStatus.value === 'inactive' && off.is_active) return false

    // Search query
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      const matchCode = (off.code || '').toLowerCase().includes(q)
      const matchName = (off.name || '').toLowerCase().includes(q)
      const matchDesc = (off.description || '').toLowerCase().includes(q)
      if (!matchCode && !matchName && !matchDesc) return false
    }

    return true
  })
})

const {
  currentPage,
  totalItems,
  totalPages,
  startIndex,
  endIndex,
  paginatedItems: paginatedOffices,
  setPage
} = useTablePagination(filteredOffices, { pageSize: 10, defaultSortKey: 'code', defaultSortOrder: 'asc' })

async function loadOffices() {
  loading.value = true
  try {
    offices.value = await fetchOffices()
  } catch (err) {
    console.error('Error loading offices:', err)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  isEditMode.value = false
  modalError.value = ''
  form.value = {
    id: 0,
    code: '',
    name: '',
    description: '',
    address: '',
    contact_number: '',
    email: ''
  }
  showModal.value = true
}

function openEditModal(office: Office) {
  isEditMode.value = true
  modalError.value = ''
  form.value = {
    id: office.id,
    code: office.code,
    name: office.name,
    description: office.description || '',
    address: office.address || '',
    contact_number: office.contact_number || '',
    email: office.email || ''
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  modalError.value = ''
}

async function handleSaveOffice() {
  modalError.value = ''
  if (!form.value.code.trim()) {
    modalError.value = 'Office code is required.'
    return
  }
  if (!form.value.name.trim()) {
    modalError.value = 'Office name is required.'
    return
  }

  saving.value = true
  try {
    if (isEditMode.value) {
      const res = await updateOffice({
        id: form.value.id,
        code: form.value.code.trim().toUpperCase(),
        name: form.value.name.trim(),
        description: form.value.description.trim() || null,
        address: form.value.address.trim() || null,
        contact_number: form.value.contact_number.trim() || null,
        email: form.value.email.trim() || null
      })

      if (res.success && res.data) {
        const idx = offices.value.findIndex((o) => o.id === form.value.id)
        if (idx !== -1) {
          offices.value[idx] = res.data
        }
        closeModal()
      } else {
        modalError.value = res.message || 'Failed to update office.'
      }
    } else {
      const res = await createOffice({
        code: form.value.code.trim().toUpperCase(),
        name: form.value.name.trim(),
        description: form.value.description.trim() || null,
        address: form.value.address.trim() || null,
        contact_number: form.value.contact_number.trim() || null,
        email: form.value.email.trim() || null
      })

      if (res.success && res.data) {
        offices.value.unshift(res.data)
        closeModal()
      } else {
        modalError.value = res.message || 'Failed to create office.'
      }
    }
  } catch (err: any) {
    modalError.value = err.message || 'Network error saving office.'
  } finally {
    saving.value = false
  }
}

async function handleToggleActive(office: Office) {
  const isCurrentlyActive = typeof office.is_active === 'boolean' ? office.is_active : office.is_active === 1
  const actionText = isCurrentlyActive ? 'deactivate' : 'activate'

  if (!confirm(`Are you sure you want to ${actionText} office "${office.code}"?`)) {
    return
  }

  try {
    const res = await toggleOfficeActive(office.id, isCurrentlyActive)
    if (res.success && res.data) {
      const idx = offices.value.findIndex((o) => o.id === office.id)
      if (idx !== -1) {
        offices.value[idx] = res.data
      }
    } else {
      alert(res.message || `Failed to ${actionText} office.`)
    }
  } catch (err: any) {
    alert(err.message || `Error attempting to ${actionText} office.`)
  }
}

function promptDelete(office: Office) {
  targetOffice.value = office
  deleteError.value = ''
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
  targetOffice.value = null
  deleteError.value = ''
}

async function handleDeactivateFromModal() {
  if (!targetOffice.value) return
  const off = targetOffice.value
  closeDeleteModal()
  await handleToggleActive(off)
}

async function confirmDelete() {
  if (!targetOffice.value) return
  deleting.value = true
  deleteError.value = ''

  try {
    const res = await deleteOffice(targetOffice.value.id)
    if (res.success) {
      offices.value = offices.value.filter((o) => o.id !== targetOffice.value!.id)
      closeDeleteModal()
    } else {
      deleteError.value = res.message || 'Failed to delete office.'
    }
  } catch (err: any) {
    deleteError.value = err.message || 'Error communicating with server.'
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  loadOffices()
})
</script>

<style scoped>
.offices-container {
  padding: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.header-action-bar h2 {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.subtitle {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

/* Filter controls */
.filter-controls-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
  display: flex;
  gap: 1.25rem;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.filter-group label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

.search-group {
  flex: 1;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  color: var(--color-text-secondary);
  font-size: 1.1rem;
}

.input-search {
  width: 100%;
  padding: 0.55rem 0.75rem 0.55rem 2.25rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  outline: none;
  background-color: #FFFFFF;
}

.input-select {
  padding: 0.55rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  outline: none;
  background-color: #FFFFFF;
  min-width: 150px;
}

/* Table Card */
.table-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.table-card-header {
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--color-border);
  background-color: var(--color-surface-hover);
}

.table-card-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.count-badge {
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-sm);
  background-color: #EFF6FF;
  border: 1px solid #BFDBFE;
  color: var(--color-primary-light);
  font-size: 0.75rem;
  font-weight: 700;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 3.5rem 1rem;
  color: var(--color-text-secondary);
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid var(--color-border);
  border-top-color: var(--color-primary-light);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 2.5rem;
  color: #94A3B8;
  margin-bottom: 0.5rem;
}

/* Data Table */
.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.data-table th {
  padding: 0.75rem 1rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
  border-bottom: 1px solid var(--color-border);
  background-color: var(--color-surface-hover);
}

.data-table td {
  padding: 0.85rem 1rem;
  font-size: 0.875rem;
  color: var(--color-text);
  border-bottom: 1px solid #F1F5F9;
  vertical-align: middle;
}

.data-table tbody tr:hover {
  background-color: var(--color-surface-hover);
}

.office-code-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: var(--radius-sm);
  background-color: #EFF6FF;
  border: 1px solid #93C5FD;
  color: var(--color-primary-dark);
  font-size: 0.8125rem;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.office-name-cell {
  display: flex;
  flex-direction: column;
}

.office-name-text {
  font-weight: 600;
  color: var(--color-text);
}

.office-desc-text {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 0.15rem;
}

.user-count-pill {
  display: inline-block;
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 600;
}

.pill-has-users {
  background-color: #EFF6FF;
  color: #1E40AF;
  border: 1px solid #BFDBFE;
}

.pill-empty {
  background-color: #F8FAFC;
  color: #94A3B8;
  border: 1px solid #E2E8F0;
}

.contact-cell {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.contact-line {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8125rem;
  color: var(--color-text-secondary);
}

.contact-line ion-icon {
  font-size: 0.95rem;
  color: var(--color-primary-light);
}

.empty-muted {
  color: #94A3B8;
}

.status-pill {
  display: inline-block;
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 700;
}

.pill-active {
  background-color: #DCFCE7;
  color: #15803D;
  border: 1px solid #16A34A;
}

.pill-inactive {
  background-color: #FEE2E2;
  color: #B91C1C;
  border: 1px solid #DC2626;
}

.text-center {
  text-align: center;
}

.text-right {
  text-align: right;
}

.actions-cell {
  white-space: nowrap;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  margin-left: 0.4rem;
  transition: all 0.15s ease;
}

.edit-btn {
  background-color: #FFFFFF;
  border-color: var(--color-border);
  color: #334155;
}

.edit-btn:hover {
  background-color: var(--color-surface-hover);
  border-color: #94A3B8;
}

.deactivate-btn {
  background-color: #FFFBEB;
  border-color: #FCD34D;
  color: #B45309;
}

.deactivate-btn:hover {
  background-color: #FEF3C7;
}

.activate-btn {
  background-color: #F0FDF4;
  border-color: #86EFAC;
  color: #15803D;
}

.activate-btn:hover {
  background-color: #DCFCE7;
}

.delete-btn {
  background-color: #FEF2F2;
  border-color: #FCA5A5;
  color: #DC2626;
  padding: 0.35rem 0.5rem;
}

.delete-btn:hover {
  background-color: #FEE2E2;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-dialog {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 580px;
  overflow: hidden;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--color-border);
}

.delete-dialog {
  max-width: 480px;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--color-surface-hover);
}

.modal-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.danger-title {
  color: #B91C1C !important;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--color-text-secondary);
  cursor: pointer;
  line-height: 1;
}

.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: flex;
  gap: 1rem;
}

.flex-1 {
  flex: 1;
}

.flex-2 {
  flex: 2;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.input-text,
.input-textarea {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  color: var(--color-text);
  font-family: inherit;
  outline: none;
  background-color: #FFFFFF;
}

.uppercase-input {
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.05em;
}

.input-text:focus,
.input-textarea:focus {
  border-color: var(--color-primary-light);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.input-textarea {
  resize: vertical;
}

.modal-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #FEF2F2;
  border: 1px solid #F87171;
  border-radius: var(--radius-sm);
  color: #991B1B;
  font-size: 0.8125rem;
}

.delete-error-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

.delete-error-message {
  font-size: 0.8125rem;
  line-height: 1.4;
}

.warning-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  padding: 0.85rem;
  background-color: #FFFBEB;
  border: 1px solid #FCD34D;
  border-radius: var(--radius-sm);
  color: #92400E;
  font-size: 0.8125rem;
}

.warning-alert-content {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

.warning-alert ion-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
  color: #D97706;
}

.modal-deactivate-hint {
  margin-top: 0.25rem;
}

.btn-warning-sm {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: #FFFBEB;
  color: #B45309;
  border: 1px solid #D97706;
  border-radius: var(--radius-sm);
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  width: fit-content;
}

.btn-warning-sm:hover {
  background: #FEF3C7;
  color: #92400E;
}

.notice-muted {
  font-size: 0.8125rem;
  color: var(--color-text-secondary);
  line-height: 1.4;
  margin: 0;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--color-border);
  background-color: var(--color-surface-hover);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: var(--color-primary-light);
  color: #FFFFFF;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-primary:hover:not(:disabled) {
  background-color: #1D4ED8;
}

.btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: #FFFFFF;
  color: #334155;
  border: 1px solid var(--color-border);
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-secondary:hover {
  background-color: var(--color-surface-hover);
}

.btn-danger {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: #DC2626;
  color: #FFFFFF;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-danger:hover:not(:disabled) {
  background-color: #B91C1C;
}

.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .filter-controls-card {
    flex-direction: column;
    align-items: stretch;
  }
  .form-row {
    flex-direction: column;
  }
}
</style>
