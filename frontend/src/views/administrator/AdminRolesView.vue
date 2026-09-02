<!-- frontend/src/views/administrator/AdminRolesView.vue -->
<!-- 6IS Core Roles & Permissions Management View (Phase 2) -->
<template>
  <MainLayout title="Role & Permission Management">
    <div class="admin-roles-container">
      
      <!-- Page Header Bar -->
      <div class="header-bar">
        <div class="header-titles">
          <div class="header-breadcrumb-tag">
            <router-link to="/administrator" class="back-link">
              <ion-icon :icon="arrowBackOutline" />
              <span>System Administration</span>
            </router-link>
          </div>
          <h2>Role & Permission Management</h2>
          <p class="subtitle">
            Configure system and custom user roles, manage granular permissions, and inspect authorization matrices across 6IS modules.
          </p>
        </div>

        <!-- Summary KPI Counter Pills -->
        <div class="summary-kpis">
          <div class="kpi-pill">
            <span class="kpi-num">{{ totalRolesCount }}</span>
            <span class="kpi-txt">Total Roles</span>
          </div>
          <div class="kpi-pill active-pill">
            <span class="kpi-num">{{ activeRolesCount }}</span>
            <span class="kpi-txt">Active</span>
          </div>
          <div class="kpi-pill system-pill">
            <span class="kpi-num">{{ systemRolesCount }}</span>
            <span class="kpi-txt">System Roles</span>
          </div>
        </div>
      </div>

      <!-- Action Toolbar -->
      <div class="toolbar-card">
        <div class="toolbar-info">
          <ion-icon :icon="shieldCheckmarkOutline" class="info-icon" />
          <span>Centralized Core RBAC: Authorization decisions are resolved authoritative from server-side role relationships.</span>
        </div>
        <div class="toolbar-actions">
          <button 
            v-if="can('roles', 'create')" 
            class="btn btn-primary" 
            @click="openCreateRoleModal"
          >
            <ion-icon :icon="addOutline" />
            <span>Create Custom Role</span>
          </button>
        </div>
      </div>

      <!-- Alert / Notice Messages -->
      <div v-if="successMessage" class="alert-box success-alert">
        <ion-icon :icon="checkmarkCircleOutline" />
        <span>{{ successMessage }}</span>
        <button class="close-alert-btn" @click="successMessage = ''">&times;</button>
      </div>

      <div v-if="errorMessage" class="alert-box danger-alert">
        <ion-icon :icon="alertCircleOutline" />
        <span>{{ errorMessage }}</span>
        <button class="close-alert-btn" @click="errorMessage = ''">&times;</button>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="loading-state">
        <ion-spinner name="crescent" color="primary"></ion-spinner>
        <p>Loading roles and permissions registry...</p>
      </div>

      <!-- Roles Table Card -->
      <div v-else class="roles-table-card">
        <div class="card-header-bar">
          <div class="card-title-group">
            <h3>Registered Roles</h3>
            <span class="table-subtitle">{{ roles.length }} roles defined in system</span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="roles-table">
            <thead>
              <tr>
                <th>Role Name</th>
                <th>Description</th>
                <th>Status</th>
                <th>Assigned Users</th>
                <th>Permissions</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="role in roles" :key="role.id" :class="{ 'system-role-row': role.is_system }">
                <!-- Role Name & Badge -->
                <td>
                  <div class="role-name-cell">
                    <span class="role-name">{{ role.name }}</span>
                    <span v-if="role.is_system" class="badge badge-system" title="Protected System Role">
                      <ion-icon :icon="lockClosedOutline" /> System
                    </span>
                  </div>
                </td>

                <!-- Description -->
                <td class="role-desc-cell">
                  {{ role.description || 'No description provided.' }}
                </td>

                <!-- Status -->
                <td>
                  <span :class="['badge', role.is_active ? 'badge-success' : 'badge-danger']">
                    {{ role.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>

                <!-- User Count -->
                <td>
                  <span class="counter-badge">
                    <ion-icon :icon="peopleOutline" />
                    {{ role.user_count ?? 0 }} user{{ (role.user_count ?? 0) === 1 ? '' : 's' }}
                  </span>
                </td>

                <!-- Permission Count -->
                <td>
                  <span class="counter-badge perm-badge">
                    <ion-icon :icon="keyOutline" />
                    {{ role.permission_count ?? 0 }} permission{{ (role.permission_count ?? 0) === 1 ? '' : 's' }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="text-right">
                  <div class="action-buttons-group">
                    <!-- Matrix / Permissions Button -->
                    <button 
                      class="btn btn-secondary btn-sm" 
                      title="Manage Role Permissions"
                      @click="openPermissionsMatrix(role)"
                    >
                      <ion-icon :icon="keyOutline" />
                      <span>Permissions</span>
                    </button>

                    <!-- Edit Details Button -->
                    <button 
                      v-if="can('roles', 'edit')" 
                      class="btn btn-secondary btn-sm"
                      title="Edit Role Metadata"
                      @click="openEditRoleModal(role)"
                    >
                      <ion-icon :icon="createOutline" />
                      <span>Edit</span>
                    </button>

                    <!-- Delete Button (Only for custom unassigned roles) -->
                    <button 
                      v-if="can('roles', 'delete') && !role.is_system" 
                      class="btn btn-danger btn-sm"
                      title="Delete Role"
                      @click="confirmDeleteRole(role)"
                    >
                      <ion-icon :icon="trashOutline" />
                      <span>Delete</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- MODAL: Create / Edit Role Metadata                                   -->
      <!-- ===================================================================== -->
      <div v-if="showRoleModal" class="modal-backdrop" @click.self="closeRoleModal">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3>{{ isEditingRole ? 'Edit Role' : 'Create Custom Role' }}</h3>
            <button class="close-btn" @click="closeRoleModal">&times;</button>
          </div>

          <div class="modal-body">
            <div v-if="selectedRole?.is_system" class="notice-box">
              <ion-icon :icon="informationCircleOutline" />
              <span><strong>System Role Notice:</strong> Core system roles cannot be renamed or deactivated during Phase 2.</span>
            </div>

            <div class="form-group">
              <label for="role-name">Role Name <span class="required">*</span></label>
              <input 
                id="role-name"
                v-model="roleForm.name" 
                type="text" 
                class="form-control" 
                placeholder="e.g. Officer, Auditor, Technician"
                :disabled="selectedRole?.is_system"
                required
              />
            </div>

            <div class="form-group">
              <label for="role-desc">Description</label>
              <textarea 
                id="role-desc"
                v-model="roleForm.description" 
                class="form-control" 
                rows="3" 
                placeholder="Describe the operational responsibilities and purpose of this role..."
              ></textarea>
            </div>

            <div class="form-group-checkbox">
              <label class="checkbox-label">
                <input 
                  type="checkbox" 
                  v-model="roleForm.is_active" 
                  :disabled="selectedRole?.is_system"
                />
                <span class="custom-checkbox-text">Role is Active</span>
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeRoleModal">Cancel</button>
            <button 
              class="btn btn-primary" 
              :disabled="isSavingRole || !roleForm.name.trim()"
              @click="saveRoleForm"
            >
              <ion-spinner v-if="isSavingRole" name="crescent" class="spinner-sm" />
              <span>{{ isEditingRole ? 'Update Role' : 'Create Role' }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ===================================================================== -->
      <!-- MODAL: Permissions Matrix                                            -->
      <!-- ===================================================================== -->
      <div v-if="showMatrixModal" class="modal-backdrop" @click.self="closeMatrixModal">
        <div class="modal-dialog modal-dialog-lg">
          <div class="modal-header">
            <div>
              <h3>Role Permissions: <span class="highlight-role">{{ matrixRole?.name }}</span></h3>
              <p class="modal-subtitle">Configure granular access capabilities across system modules for this role.</p>
            </div>
            <button class="close-btn" @click="closeMatrixModal">&times;</button>
          </div>

          <div class="modal-body modal-body-scrollable">
            <div v-if="isMatrixLoading" class="loading-state">
              <ion-spinner name="crescent" color="primary"></ion-spinner>
              <p>Loading permission assignments...</p>
            </div>

            <div v-else>
              <!-- Matrix Controls & Legend -->
              <div class="matrix-controls-bar">
                <div class="matrix-legend">
                  <span class="legend-item"><span class="legend-box checked"></span> Granted</span>
                  <span class="legend-item"><span class="legend-box unchecked"></span> Denied</span>
                  <span class="legend-item"><span class="legend-box na">-</span> Not Applicable</span>
                </div>
                <div class="matrix-bulk-actions">
                  <button class="btn btn-secondary btn-sm" @click="selectAllPermissions">Grant All</button>
                  <button class="btn btn-secondary btn-sm" @click="clearAllPermissions">Clear All</button>
                </div>
              </div>

              <!-- Matrix Table Grouped By Module -->
              <div class="matrix-table-wrapper">
                <table class="matrix-table">
                  <thead>
                    <tr>
                      <th class="col-module">System Module</th>
                      <th class="col-action text-center">View</th>
                      <th class="col-action text-center">Create</th>
                      <th class="col-action text-center">Edit</th>
                      <th class="col-action text-center">Delete</th>
                      <th class="col-action text-center">Configure</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr 
                      v-for="grp in groupedPermissions" 
                      :key="grp.module_key"
                      :class="{ 'module-row-inactive': !grp.module_is_active }"
                    >
                      <!-- Module Label & Inactive Indicator -->
                      <td class="module-title-cell">
                        <div class="module-title-wrap">
                          <span class="module-title-name">{{ grp.module_name }}</span>
                          <span v-if="!grp.module_is_active" class="badge badge-warning module-inactive-pill">
                            Disabled on System
                          </span>
                        </div>
                      </td>

                      <!-- View Permission -->
                      <td class="text-center">
                        <input 
                          v-if="hasPermKey(grp, 'view')"
                          type="checkbox"
                          class="matrix-checkbox"
                          :checked="isAssigned(getPermId(grp, 'view'))"
                          @change="togglePerm(getPermId(grp, 'view'))"
                        />
                        <span v-else class="na-dash">-</span>
                      </td>

                      <!-- Create Permission -->
                      <td class="text-center">
                        <input 
                          v-if="hasPermKey(grp, 'create')"
                          type="checkbox"
                          class="matrix-checkbox"
                          :checked="isAssigned(getPermId(grp, 'create'))"
                          @change="togglePerm(getPermId(grp, 'create'))"
                        />
                        <span v-else class="na-dash">-</span>
                      </td>

                      <!-- Edit Permission -->
                      <td class="text-center">
                        <input 
                          v-if="hasPermKey(grp, 'edit')"
                          type="checkbox"
                          class="matrix-checkbox"
                          :checked="isAssigned(getPermId(grp, 'edit'))"
                          @change="togglePerm(getPermId(grp, 'edit'))"
                        />
                        <span v-else class="na-dash">-</span>
                      </td>

                      <!-- Delete Permission -->
                      <td class="text-center">
                        <input 
                          v-if="hasPermKey(grp, 'delete')"
                          type="checkbox"
                          class="matrix-checkbox"
                          :checked="isAssigned(getPermId(grp, 'delete'))"
                          @change="togglePerm(getPermId(grp, 'delete'))"
                        />
                        <span v-else class="na-dash">-</span>
                      </td>

                      <!-- Configure Permission -->
                      <td class="text-center">
                        <input 
                          v-if="hasPermKey(grp, 'configure')"
                          type="checkbox"
                          class="matrix-checkbox"
                          :checked="isAssigned(getPermId(grp, 'configure'))"
                          @change="togglePerm(getPermId(grp, 'configure'))"
                        />
                        <span v-else class="na-dash">-</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Matrix Tip Note -->
              <p class="matrix-tip">
                <em>Note:</em> The <strong>configure</strong> permission governs reference-table and workflow configuration independently and does not implicitly grant create or delete capabilities.
              </p>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeMatrixModal">Close</button>
            <button 
              class="btn btn-primary" 
              :disabled="isSavingMatrix"
              @click="saveRolePermissions"
            >
              <ion-spinner v-if="isSavingMatrix" name="crescent" class="spinner-sm" />
              <span>Save Permissions</span>
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { IonIcon, IonSpinner } from '@ionic/vue'
import {
  arrowBackOutline,
  shieldCheckmarkOutline,
  addOutline,
  checkmarkCircleOutline,
  alertCircleOutline,
  lockClosedOutline,
  peopleOutline,
  keyOutline,
  createOutline,
  trashOutline,
  informationCircleOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { Role, Permission, GroupedModulePermissions, RolePayload } from '../../types/permission'
import * as roleService from '../../services/roleService'
import { usePermissions } from '../../composables/usePermissions'

const { can } = usePermissions()

// State
const roles = ref<Role[]>([])
const permissionsList = ref<Permission[]>([])
const groupedPermissions = ref<GroupedModulePermissions[]>([])
const isLoading = ref(true)
const successMessage = ref('')
const errorMessage = ref('')

// KPIs
const totalRolesCount = computed(() => roles.value.length)
const activeRolesCount = computed(() => roles.value.filter(r => r.is_active).length)
const systemRolesCount = computed(() => roles.value.filter(r => r.is_system).length)

// Modal: Create / Edit Role
const showRoleModal = ref(false)
const isEditingRole = ref(false)
const isSavingRole = ref(false)
const selectedRole = ref<Role | null>(null)
const roleForm = ref<RolePayload>({
  name: '',
  description: '',
  is_active: true
})

// Modal: Permission Matrix
const showMatrixModal = ref(false)
const matrixRole = ref<Role | null>(null)
const isMatrixLoading = ref(false)
const isSavingMatrix = ref(false)
const selectedPermissionIds = ref<number[]>([])

async function loadData() {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const [fetchedRoles, permData] = await Promise.all([
      roleService.fetchRoles(),
      roleService.fetchPermissions()
    ])
    roles.value = fetchedRoles
    permissionsList.value = permData.list
    groupedPermissions.value = permData.grouped
  } catch (err: any) {
    errorMessage.value = 'Failed to load roles and permissions.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadData()
})

// Role Form Handlers
function openCreateRoleModal() {
  isEditingRole.value = false
  selectedRole.value = null
  roleForm.value = {
    name: '',
    description: '',
    is_active: true
  }
  showRoleModal.value = true
}

function openEditRoleModal(role: Role) {
  isEditingRole.value = true
  selectedRole.value = role
  roleForm.value = {
    name: role.name,
    description: role.description || '',
    is_active: role.is_active
  }
  showRoleModal.value = true
}

function closeRoleModal() {
  showRoleModal.value = false
  selectedRole.value = null
}

async function saveRoleForm() {
  if (!roleForm.value.name.trim()) return

  isSavingRole.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    if (isEditingRole.value && selectedRole.value) {
      const res = await roleService.updateRole(selectedRole.value.id, roleForm.value)
      if (res.success) {
        successMessage.value = res.message || 'Role updated successfully.'
        closeRoleModal()
        await loadData()
      } else {
        errorMessage.value = res.message || 'Failed to update role.'
      }
    } else {
      const res = await roleService.createRole(roleForm.value)
      if (res.success) {
        successMessage.value = res.message || 'Role created successfully.'
        closeRoleModal()
        await loadData()
      } else {
        errorMessage.value = res.message || 'Failed to create role.'
      }
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'An error occurred.'
  } finally {
    isSavingRole.value = false
  }
}

async function confirmDeleteRole(role: Role) {
  if (role.is_system) {
    errorMessage.value = 'System roles cannot be deleted.'
    return
  }

  if ((role.user_count ?? 0) > 0) {
    errorMessage.value = `Cannot delete role '${role.name}' because it is assigned to ${role.user_count} user(s).`
    return
  }

  if (!confirm(`Are you sure you want to permanently delete custom role '${role.name}'?`)) {
    return
  }

  try {
    const res = await roleService.deleteRole(role.id)
    if (res.success) {
      successMessage.value = res.message || `Role '${role.name}' deleted successfully.`
      await loadData()
    } else {
      errorMessage.value = res.message || 'Failed to delete role.'
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Failed to delete role.'
  }
}

// Permission Matrix Handlers
async function openPermissionsMatrix(role: Role) {
  matrixRole.value = role
  showMatrixModal.value = true
  isMatrixLoading.value = true

  try {
    const detail = await roleService.fetchRole(role.id)
    if (detail && Array.isArray(detail.permission_ids)) {
      selectedPermissionIds.value = [...detail.permission_ids]
    } else {
      selectedPermissionIds.value = []
    }
  } catch (err) {
    console.error('Error loading role permissions:', err)
    selectedPermissionIds.value = []
  } finally {
    isMatrixLoading.value = false
  }
}

function closeMatrixModal() {
  showMatrixModal.value = false
  matrixRole.value = null
  selectedPermissionIds.value = []
}

function hasPermKey(grp: GroupedModulePermissions, key: string): boolean {
  return grp.permissions.some(p => p.permission_key.toLowerCase() === key.toLowerCase())
}

function getPermId(grp: GroupedModulePermissions, key: string): number {
  const p = grp.permissions.find(p => p.permission_key.toLowerCase() === key.toLowerCase())
  return p ? p.id : 0
}

function isAssigned(permId: number): boolean {
  return permId > 0 && selectedPermissionIds.value.includes(permId)
}

function togglePerm(permId: number) {
  if (permId <= 0) return
  const idx = selectedPermissionIds.value.indexOf(permId)
  if (idx >= 0) {
    selectedPermissionIds.value.splice(idx, 1)
  } else {
    selectedPermissionIds.value.push(permId)
  }
}

function selectAllPermissions() {
  const allIds = permissionsList.value.map(p => p.id)
  selectedPermissionIds.value = Array.from(new Set([...selectedPermissionIds.value, ...allIds]))
}

function clearAllPermissions() {
  // If Administrator role, retain roles.configure
  if (matrixRole.value?.is_system && matrixRole.value.name.toLowerCase() === 'administrator') {
    const confPerm = permissionsList.value.find(p => p.module_key === 'roles' && p.permission_key === 'configure')
    selectedPermissionIds.value = confPerm ? [confPerm.id] : []
  } else {
    selectedPermissionIds.value = []
  }
}

async function saveRolePermissions() {
  if (!matrixRole.value) return

  isSavingMatrix.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const res = await roleService.updateRolePermissions(matrixRole.value.id, selectedPermissionIds.value)
    if (res.success) {
      successMessage.value = res.message || 'Role permissions updated successfully.'
      closeMatrixModal()
      await loadData()
    } else {
      errorMessage.value = res.message || 'Failed to update permissions.'
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'An error occurred while saving permissions.'
  } finally {
    isSavingMatrix.value = false
  }
}
</script>

<style scoped>
.admin-roles-container {
  padding: 24px;
  max-width: 1300px;
  margin: 0 auto;
}

/* Header Bar */
.header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 20px;
  flex-wrap: wrap;
}

.header-breadcrumb-tag {
  margin-bottom: 8px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--color-primary-light, #2563EB);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
}

.back-link:hover {
  text-decoration: underline;
}

.header-titles h2 {
  margin: 0 0 6px 0;
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
}

.subtitle {
  margin: 0;
  color: var(--color-text-secondary, #64748B);
  font-size: 0.875rem;
}

/* KPI Counter Pills */
.summary-kpis {
  display: flex;
  gap: 12px;
}

.kpi-pill {
  background: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-sm, 6px);
  padding: 10px 18px;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 95px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.kpi-num {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
}

.kpi-txt {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.active-pill .kpi-num {
  color: #16A34A;
}

.system-pill .kpi-num {
  color: #2563EB;
}

/* Toolbar Card */
.toolbar-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #FFFFFF;
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-md, 10px);
  padding: 14px 20px;
  margin-bottom: 20px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  flex-wrap: wrap;
  gap: 14px;
}

.toolbar-info {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text-secondary, #64748B);
  font-size: 0.85rem;
}

.toolbar-info .info-icon {
  font-size: 1.25rem;
  color: var(--color-primary-light, #2563EB);
  flex-shrink: 0;
}

/* Alert Boxes */
.alert-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: var(--radius-sm, 6px);
  margin-bottom: 20px;
  font-size: 0.875rem;
  position: relative;
}

.success-alert {
  background: #F0FDF4;
  border: 1px solid #16A34A;
  color: #15803D;
}

.danger-alert {
  background: #FEF2F2;
  border: 1px solid #DC2626;
  color: #B91C1C;
}

.close-alert-btn {
  background: transparent;
  border: none;
  font-size: 1.2rem;
  margin-left: auto;
  cursor: pointer;
  color: inherit;
}

/* Roles Table Card */
.roles-table-card {
  background: #FFFFFF;
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-md, 10px);
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}

.card-header-bar {
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title-group h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 600;
  color: var(--color-primary-dark, #172554);
}

.table-subtitle {
  font-size: 0.78rem;
  color: var(--color-text-secondary, #64748B);
}

.table-responsive {
  overflow-x: auto;
}

.roles-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
  text-align: left;
}

.roles-table th {
  background: #F8FAFC;
  color: var(--color-text-secondary, #64748B);
  font-weight: 700;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 18px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
}

.roles-table td {
  padding: 14px 18px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  color: var(--color-text, #0F172A);
  vertical-align: middle;
}

.roles-table tbody tr:hover {
  background: var(--color-surface-hover, #F8FAFC);
}

.system-role-row {
  background: #FAFBFF;
}

.role-name-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.role-name {
  font-weight: 600;
  color: var(--color-primary-dark, #172554);
}

.role-desc-cell {
  color: var(--color-text-secondary, #64748B);
  max-width: 320px;
}

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 4px;
}

.badge-system {
  background: #EFF6FF;
  border: 1px solid #2563EB;
  color: #1D4ED8;
}

.badge-success {
  background: #F0FDF4;
  border: 1px solid #16A34A;
  color: #15803D;
}

.badge-danger {
  background: #FEF2F2;
  border: 1px solid #DC2626;
  color: #B91C1C;
}

.badge-warning {
  background: #FFFBEB;
  border: 1px solid #D97706;
  color: #B45309;
}

.counter-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #F1F5F9;
  color: #334155;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: var(--radius-sm, 6px);
}

.perm-badge {
  background: #F8FAFC;
  border: 1px solid #CBD5E1;
}

.action-buttons-group {
  display: inline-flex;
  gap: 8px;
}

.text-right {
  text-align: right;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-weight: 600;
  font-size: 0.85rem;
  padding: 8px 14px;
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
}

.btn-sm {
  font-size: 0.78rem;
  padding: 6px 10px;
}

.btn-primary {
  background: var(--color-primary-light, #2563EB);
  color: #FFFFFF;
}

.btn-primary:hover:not(:disabled) {
  background: #1D4ED8;
}

.btn-secondary {
  background: #FFFFFF;
  border: 1px solid var(--color-border, #CBD5E1);
  color: #334155;
}

.btn-secondary:hover:not(:disabled) {
  background: #F8FAFC;
  border-color: #94A3B8;
}

.btn-danger {
  background: #FEF2F2;
  border: 1px solid #DC2626;
  color: #DC2626;
}

.btn-danger:hover:not(:disabled) {
  background: #FEE2E2;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner-sm {
  width: 16px;
  height: 16px;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-dialog {
  background: #FFFFFF;
  border-radius: var(--radius-lg, 16px);
  width: 100%;
  max-width: 520px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-dialog-lg {
  max-width: 860px;
  max-height: 90vh;
}

.modal-header {
  padding: 16px 22px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
}

.highlight-role {
  color: var(--color-primary-light, #2563EB);
}

.modal-subtitle {
  margin: 4px 0 0 0;
  font-size: 0.8rem;
  color: var(--color-text-secondary, #64748B);
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 1.4rem;
  line-height: 1;
  color: #64748B;
  cursor: pointer;
}

.close-btn:hover {
  color: #0F172A;
}

.modal-body {
  padding: 20px 22px;
  overflow-y: auto;
}

.modal-body-scrollable {
  max-height: 60vh;
}

.modal-footer {
  padding: 14px 22px;
  border-top: 1px solid var(--color-border, #CBD5E1);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: #F8FAFC;
}

/* Forms */
.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
}

.required {
  color: #DC2626;
}

.form-control {
  width: 100%;
  padding: 9px 12px;
  font-size: 0.875rem;
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-sm, 6px);
  background: #FFFFFF;
  color: #0F172A;
  font-family: inherit;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primary-light, #2563EB);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.form-group-checkbox {
  margin-top: 14px;
}

.checkbox-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 500;
  color: #0F172A;
}

.notice-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  padding: 10px 14px;
  border-radius: var(--radius-sm, 6px);
  color: #1E40AF;
  font-size: 0.82rem;
  margin-bottom: 16px;
}

/* Matrix Table Specifics */
.matrix-controls-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  font-size: 0.82rem;
  flex-wrap: wrap;
  gap: 10px;
}

.matrix-legend {
  display: flex;
  gap: 14px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #64748B;
}

.legend-box {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  border: 1px solid #CBD5E1;
}

.legend-box.checked {
  background: #2563EB;
  border-color: #2563EB;
}

.legend-box.unchecked {
  background: #FFFFFF;
}

.legend-box.na {
  background: #F1F5F9;
  text-align: center;
  line-height: 12px;
  font-size: 10px;
  color: #94A3B8;
}

.matrix-bulk-actions {
  display: flex;
  gap: 8px;
}

.matrix-table-wrapper {
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: var(--radius-sm, 6px);
  overflow: hidden;
}

.matrix-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.matrix-table th {
  background: #F8FAFC;
  padding: 10px 14px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  font-weight: 700;
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #475569;
}

.col-module {
  width: 35%;
}

.col-action {
  width: 13%;
}

.matrix-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  vertical-align: middle;
}

.module-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.module-title-name {
  font-weight: 600;
  color: var(--color-primary-dark, #172554);
}

.module-inactive-pill {
  width: fit-content;
  font-size: 0.68rem;
}

.module-row-inactive {
  background: #FFFDF7;
}

.text-center {
  text-align: center;
}

.matrix-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--color-primary-light, #2563EB);
}

.na-dash {
  color: #94A3B8;
  font-weight: 600;
}

.matrix-tip {
  margin-top: 14px;
  font-size: 0.78rem;
  color: var(--color-text-secondary, #64748B);
}

.loading-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--color-text-secondary, #64748B);
}
</style>
