<template>
  <MainLayout title="User Management">
    <div class="users-container">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>User Accounts Management</h2>
          <p class="subtitle">Maintain user accounts, access roles, active status, and password credentials.</p>
        </div>

        <button class="add-user-btn" @click="openCreateModal">
          <ion-icon :icon="personAddOutline" />
          <span>Add New User</span>
        </button>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="userSearch">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="userSearch"
              type="text"
              v-model="searchQuery"
              placeholder="Search username or full name..."
              class="input-search"
            />
          </div>
        </div>

        <div class="filter-group">
          <label for="userRoleFilter">Role</label>
          <select id="userRoleFilter" v-model="filterRole" class="input-select">
            <option value="all">-Select-</option>
            <option value="Administrator">Administrator</option>
            <option value="User">User</option>
          </select>
        </div>

        <div class="filter-group">
          <label for="userStatusFilter">Status</label>
          <select id="userStatusFilter" v-model="filterStatus" class="input-select">
            <option value="all">-Select-</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive Only</option>
          </select>
        </div>
      </div>

      <!-- Users Table Card -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Registered Users</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading user accounts...</p>
        </div>

        <div v-else-if="filteredUsers.length === 0" class="empty-state">
          <p>No user accounts found matching your filters.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th class="text-center">Role</th>
                <th class="text-center">Status</th>
                <th>Created Date</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in filteredUsers" :key="user.id">
                <td class="font-bold code-text">{{ user.username }}</td>
                <td>{{ user.full_name || 'N/A' }}</td>
                <td class="text-center">
                  <span :class="['role-badge', user.role === 'Administrator' ? 'role-admin' : 'role-user']">
                    {{ user.role }}
                  </span>
                </td>
                <td class="text-center">
                  <span :class="['status-badge', user.is_active === 1 ? 'status-active' : 'status-inactive']">
                    {{ user.is_active === 1 ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>{{ formatDate(user.created_at) }}</td>
                <td class="text-center">
                  <div class="action-buttons">
                    <button class="icon-btn edit-btn" @click="openEditModal(user)" title="Edit User">
                      <ion-icon :icon="createOutline" />
                    </button>

                    <button
                      :class="['icon-btn', user.is_active === 1 ? 'deactivate-btn' : 'activate-btn']"
                      @click="handleToggleActive(user)"
                      :disabled="user.id === currentSessionUserId"
                      :title="user.id === currentSessionUserId ? 'Cannot deactivate your active session' : (user.is_active === 1 ? 'Deactivate account' : 'Activate account')"
                    >
                      <ion-icon :icon="user.is_active === 1 ? banOutline : checkmarkCircleOutline" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Create / Edit User Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>{{ isEditMode ? 'Edit User Account' : 'Create New User Account' }}</h3>
            <button class="close-btn" @click="closeModal">&times;</button>
          </div>

          <form @submit.prevent="handleSubmit" class="modal-body">
            <div class="form-group">
              <label for="username">Username</label>
              <input
                id="username"
                v-model="form.username"
                type="text"
                required
                :disabled="isEditMode"
                placeholder="Enter username (e.g. User02)"
                class="input-text"
              />
            </div>

            <div class="form-group">
              <label for="fullName">Full Name</label>
              <input
                id="fullName"
                v-model="form.full_name"
                type="text"
                placeholder="Enter full name"
                class="input-text"
              />
            </div>

            <div class="form-group">
              <label for="role">Role</label>
              <select id="role" v-model="form.role" class="input-select" required>
                <option value="User">User (Standard Operational Access)</option>
                <option value="Administrator">Administrator (Full Administrative Access)</option>
              </select>
            </div>

            <div class="form-group">
              <label for="password">
                Password {{ isEditMode ? '(Leave blank to keep unchanged)' : '' }}
              </label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                :required="!isEditMode"
                placeholder="Enter secure password"
                class="input-text"
              />
            </div>

            <div v-if="modalError" class="modal-error">
              {{ modalError }}
            </div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="closeModal">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : (isEditMode ? 'Update User' : 'Create User') }}
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
  personAddOutline,
  createOutline,
  banOutline,
  checkmarkCircleOutline,
  searchOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { activeUser } from '../../services/authService'
import {
  fetchUsers,
  createUser,
  updateUser,
  toggleUserActive
} from '../../services/userService'
import type { UserAccount, UserRole } from '../../types/user'

const users = ref<UserAccount[]>([])
const loading = ref(true)

const searchQuery = ref('')
const filterRole = ref('all')
const filterStatus = ref('all')

const filteredUsers = computed(() => {
  return users.value.filter(u => {
    // Search Filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const matchesUser = u.username.toLowerCase().includes(q)
      const matchesName = (u.full_name || '').toLowerCase().includes(q)
      if (!matchesUser && !matchesName) return false
    }

    // Role Filter
    if (filterRole.value !== 'all' && u.role !== filterRole.value) {
      return false
    }

    // Status Filter
    if (filterStatus.value === 'active' && u.is_active !== 1) return false
    if (filterStatus.value === 'inactive' && u.is_active !== 0) return false

    return true
  })
})

const showModal = ref(false)
const isEditMode = ref(false)
const editUserId = ref(0)
const saving = ref(false)
const modalError = ref('')

const form = ref({
  username: '',
  full_name: '',
  password: '',
  role: 'User' as UserRole
})

const currentSessionUserId = computed(() => activeUser.value?.id || 0)

async function loadUsers() {
  loading.value = true
  const res = await fetchUsers()
  if (res.success) {
    users.value = res.data
  }
  loading.value = false
}

function openCreateModal() {
  isEditMode.value = false
  editUserId.value = 0
  form.value = {
    username: '',
    full_name: '',
    password: '',
    role: 'User'
  }
  modalError.value = ''
  showModal.value = true
}

function openEditModal(user: UserAccount) {
  isEditMode.value = true
  editUserId.value = user.id
  form.value = {
    username: user.username,
    full_name: user.full_name || '',
    password: '',
    role: user.role
  }
  modalError.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  modalError.value = ''

  if (isEditMode.value) {
    const res = await updateUser({
      id: editUserId.value,
      full_name: form.value.full_name,
      role: form.value.role,
      password: form.value.password || undefined
    })
    saving.value = false
    if (res.success) {
      closeModal()
      loadUsers()
    } else {
      modalError.value = res.message || 'Failed to update user.'
    }
  } else {
    const res = await createUser({
      username: form.value.username,
      full_name: form.value.full_name,
      password: form.value.password,
      role: form.value.role
    })
    saving.value = false
    if (res.success) {
      closeModal()
      loadUsers()
    } else {
      modalError.value = res.message || 'Failed to create user.'
    }
  }
}

async function handleToggleActive(user: UserAccount) {
  const newActive = user.is_active === 1 ? 0 : 1
  const actionName = newActive === 1 ? 'activate' : 'deactivate'
  if (!confirm(`Are you sure you want to ${actionName} user '${user.username}'?`)) return

  const res = await toggleUserActive(user.id, newActive)
  if (res.success) {
    loadUsers()
  } else {
    alert(res.message || `Failed to ${actionName} user.`)
  }
}

import { formatDate } from '../../utils/dateUtils'

onMounted(() => {
  loadUsers()
})
</script>

<style scoped>
.users-container {
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

.add-user-btn {
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

.add-user-btn:hover {
  background: #1d4ed8;
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

.font-bold { font-weight: 700; }
.code-text { font-family: monospace; color: #0f172a; }
.text-center { text-align: center; }

.role-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.role-admin { background: #f3e8ff; color: #7e22ce; }
.role-user { background: #e0f2fe; color: #0369a1; }

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
}

.status-active { background: #f0fdf4; color: #16a34a; }
.status-inactive { background: #fef2f2; color: #dc2626; }

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

.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 16px;
}

.edit-btn { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.edit-btn:hover { background: #2563eb; color: #ffffff; }

.deactivate-btn { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.deactivate-btn:hover:not(:disabled) { background: #dc2626; color: #ffffff; }

.activate-btn { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.activate-btn:hover { background: #16a34a; color: #ffffff; }

.icon-btn:disabled { opacity: 0.4; cursor: not-allowed; }

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

.input-text, .input-select {
  width: 100%;
  padding: 10px 14px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  box-sizing: border-box;
}

.input-text:disabled { background: #f8fafc; color: #64748b; }
.input-text:focus, .input-select:focus { border-color: #2563eb; }

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
