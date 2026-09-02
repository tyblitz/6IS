<!-- frontend/src/views/administrator/AdminModulesView.vue -->
<template>
  <MainLayout title="System Administration">
    <div class="admin-modules-container">
      
      <!-- Page Header Bar -->
      <div class="header-bar">
        <div class="header-titles">
          <div class="header-breadcrumb-tag">
            <router-link to="/administrator" class="back-link">
              <ion-icon :icon="arrowBackOutline" />
              <span>System Administration</span>
            </router-link>
          </div>
          <h2>Module Management</h2>
          <p class="subtitle">
            Configure system modules, view activation statuses, and manage optional business features.
          </p>
        </div>

        <!-- Summary KPI Counter Pills -->
        <div class="summary-kpis">
          <div class="kpi-pill">
            <span class="kpi-num">{{ totalModulesCount }}</span>
            <span class="kpi-txt">Total Modules</span>
          </div>
          <div class="kpi-pill active-pill">
            <span class="kpi-num">{{ activeModulesCount }}</span>
            <span class="kpi-txt">Active</span>
          </div>
          <div class="kpi-pill core-pill">
            <span class="kpi-num">{{ coreModulesCount }}</span>
            <span class="kpi-txt">Core Platform</span>
          </div>
        </div>
      </div>

      <!-- Information Banner -->
      <div class="info-alert-box">
        <div class="info-alert-icon">
          <ion-icon :icon="shieldCheckmarkOutline" />
        </div>
        <div class="info-alert-content">
          <h4>Data Safety Guarantee</h4>
          <p>
            Disabling an operational module makes it unavailable in menus, routing, and APIs. 
            <strong>All existing records, tables, and configurations remain completely intact.</strong> Re-enabling a module instantly restores access.
          </p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="loading-state">
        <ion-spinner name="crescent" color="primary"></ion-spinner>
        <p>Loading module registry...</p>
      </div>

      <!-- Modules Table Card -->
      <div v-else class="modules-table-card">
        <div class="card-header-bar">
          <h3>Registered System Modules</h3>
          <span class="table-subtitle">{{ modules.length }} modules registered in database</span>
        </div>

        <div class="modules-list">
          <div 
            v-for="mod in modules" 
            :key="mod.id" 
            :class="['module-row', mod.is_core ? 'is-core-row' : '', mod.is_active ? 'is-active-row' : 'is-inactive-row']"
          >
            <!-- Module Icon Box -->
            <div :class="['module-icon-wrapper', getIconColorClass(mod)]">
              <ion-icon :icon="getModuleIcon(mod.icon)" />
            </div>

            <!-- Module Main Details -->
            <div class="module-details">
              <div class="title-row">
                <h4 class="module-name">{{ mod.name }}</h4>
                <code class="module-key-tag">{{ mod.module_key }}</code>
                
                <!-- Core Protected Badge -->
                <span v-if="mod.is_core" class="badge badge-core">
                  <ion-icon :icon="lockClosedOutline" /> Core Protected
                </span>

                <!-- Version Badge -->
                <span v-if="mod.version" class="badge badge-version">
                  v{{ mod.version }}
                </span>
                <span v-else class="badge badge-unreleased">
                  Planned
                </span>
              </div>

              <p class="module-desc">{{ mod.description || 'No description provided.' }}</p>

              <div class="module-meta">
                <span v-if="mod.route" class="meta-item">
                  <strong>Route:</strong> <code>{{ mod.route }}</code>
                </span>
                <span v-else class="meta-item text-muted">
                  <em>No route assigned</em>
                </span>
                <span class="meta-divider">·</span>
                <span class="meta-item">
                  <strong>Order:</strong> #{{ mod.sort_order }}
                </span>
              </div>
            </div>

            <!-- Activation Status Pill -->
            <div class="module-status-col">
              <span :class="['status-pill', mod.is_active ? 'status-active' : 'status-inactive']">
                <span class="status-dot"></span>
                {{ mod.is_active ? 'Active' : 'Disabled' }}
              </span>
            </div>

            <!-- Action Toggle Control -->
            <div class="module-action-col">
              <!-- Core Module: Locked & Disabled -->
              <div v-if="mod.is_core" class="core-locked-indicator" title="Core modules cannot be disabled">
                <ion-toggle :checked="true" :disabled="true" color="primary"></ion-toggle>
                <span class="locked-hint">Locked</span>
              </div>

              <!-- Business Module: Interactive Toggle -->
              <div v-else class="toggle-control-group">
                <ion-toggle
                  :checked="mod.is_active"
                  :disabled="isUpdating"
                  color="primary"
                  @ionChange="handleToggleAttempt(mod, $event)"
                ></ion-toggle>
              </div>
            </div>

          </div>
        </div>
      </div>

      <!-- Confirmation Modal for Deactivating a Business Module -->
      <div v-if="showConfirmModal && pendingModule" class="modal-backdrop" @click.self="cancelDeactivation">
        <div class="confirm-modal-card">
          <div class="modal-header">
            <div class="modal-warning-icon">
              <ion-icon :icon="alertCircleOutline" />
            </div>
            <div>
              <h3>Disable {{ pendingModule.name }} Module?</h3>
              <p class="modal-desc">Please review the impact before deactivating.</p>
            </div>
          </div>

          <div class="modal-body">
            <p>
              Are you sure you want to disable the <strong>{{ pendingModule.name }}</strong> module?
            </p>
            <ul class="modal-impact-list">
              <li>The module will be hidden from all user navigation and menus.</li>
              <li>Direct URL access will be blocked and redirected to the Dashboard.</li>
              <li>Backend API endpoints for this module will reject requests with HTTP 403.</li>
              <li><strong>Zero Data Loss:</strong> All database tables, records, and history remain untouched.</li>
            </ul>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn-secondary" :disabled="isUpdating" @click="cancelDeactivation">
              Cancel
            </button>
            <button type="button" class="btn-danger" :disabled="isUpdating" @click="confirmDeactivation">
              {{ isUpdating ? 'Disabling...' : 'Disable Module' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Feedback Toast Notification -->
      <div v-if="toastMessage" :class="['toast-notification', toastType]">
        <ion-icon :icon="toastType === 'success' ? checkmarkCircleOutline : alertCircleOutline" />
        <span>{{ toastMessage }}</span>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonIcon,
  IonSpinner,
  IonToggle
} from '@ionic/vue'
import {
  arrowBackOutline,
  shieldCheckmarkOutline,
  lockClosedOutline,
  alertCircleOutline,
  checkmarkCircleOutline,
  cubeOutline,
  chatbubbleEllipsesOutline,
  calendarOutline,
  clipboardOutline,
  speedometerOutline,
  cashOutline,
  homeOutline,
  settingsOutline,
  gridOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { useModules } from '../../composables/useModules'
import type { SystemModule } from '../../types/module'

const router = useRouter()
const { modules, isLoading, loadModules, toggleModule } = useModules()

const isUpdating = ref(false)
const showConfirmModal = ref(false)
const pendingModule = ref<SystemModule | null>(null)

// Toast feedback state
const toastMessage = ref<string | null>(null)
const toastType = ref<'success' | 'error'>('success')
let toastTimer: any = null

function showToast(message: string, type: 'success' | 'error' = 'success') {
  toastMessage.value = message
  toastType.value = type
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastMessage.value = null
  }, 4000)
}

// Summary Metrics
const totalModulesCount = computed(() => modules.value.length)
const activeModulesCount = computed(() => modules.value.filter(m => m.is_active).length)
const coreModulesCount = computed(() => modules.value.filter(m => m.is_core).length)

function getModuleIcon(iconName: string | null) {
  switch (iconName) {
    case 'cubeOutline': return cubeOutline
    case 'chatbubbleEllipsesOutline': return chatbubbleEllipsesOutline
    case 'calendarOutline': return calendarOutline
    case 'clipboardOutline': return clipboardOutline
    case 'speedometerOutline': return speedometerOutline
    case 'cashOutline': return cashOutline
    case 'homeOutline': return homeOutline
    case 'settingsOutline': return settingsOutline
    default: return gridOutline
  }
}

function getIconColorClass(mod: SystemModule): string {
  if (!mod.is_active) return 'icon-muted'
  switch (mod.module_key) {
    case 'dashboard': return 'icon-blue'
    case 'inventory': return 'icon-blue'
    case 'communications': return 'icon-green'
    case 'calendar': return 'icon-indigo'
    case 'accomplishments': return 'icon-amber'
    case 'administrator': return 'icon-purple'
    default: return 'icon-slate'
  }
}

/**
 * Triggered when user changes the toggle.
 * If enabling: toggles immediately.
 * If disabling: opens confirmation modal.
 */
function handleToggleAttempt(mod: SystemModule, event: CustomEvent) {
  const targetChecked = event.detail.checked as boolean

  if (mod.is_core) {
    showToast('Core modules cannot be disabled.', 'error')
    return
  }

  if (!targetChecked) {
    // Attempting to disable -> request confirmation
    pendingModule.value = mod
    showConfirmModal.value = true
  } else {
    // Attempting to activate -> enable immediately
    executeToggle(mod, true)
  }
}

function cancelDeactivation() {
  showConfirmModal.value = false
  pendingModule.value = null
}

async function confirmDeactivation() {
  if (!pendingModule.value) return
  const mod = pendingModule.value
  showConfirmModal.value = false
  pendingModule.value = null
  await executeToggle(mod, false)
}

async function executeToggle(mod: SystemModule, newStatus: boolean) {
  isUpdating.value = true
  try {
    const res = await toggleModule(mod.id, newStatus)
    if (res.success) {
      showToast(res.message || `Module '${mod.name}' updated successfully.`, 'success')
      
      // If user is currently within the disabled module, redirect to /home
      const currentPath = router.currentRoute.value.path
      if (!newStatus && mod.route && currentPath.startsWith(mod.route)) {
        router.push('/home')
      }
    } else {
      showToast(res.message || 'Failed to update module activation.', 'error')
    }
  } catch (err: any) {
    showToast('Unexpected error updating module: ' + err.message, 'error')
  } finally {
    isUpdating.value = false
  }
}

onMounted(async () => {
  await loadModules(true)
})
</script>

<style scoped>
.admin-modules-container {
  padding: 28px 36px;
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Header Bar */
.header-bar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.header-breadcrumb-tag {
  margin-bottom: 6px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--color-primary, #1e3a8a);
  font-size: 0.825rem;
  font-weight: 600;
  text-decoration: none;
  transition: opacity 0.15s ease;
}

.back-link:hover {
  opacity: 0.8;
  text-decoration: underline;
}

.header-titles h2 {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--color-primary-dark, #0f172a);
  margin: 0 0 4px 0;
  letter-spacing: -0.01em;
}

.subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #64748b);
  margin: 0;
}

/* Summary Counter Pills */
.summary-kpis {
  display: flex;
  align-items: center;
  gap: 10px;
}

.kpi-pill {
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #cbd5e1);
  padding: 6px 14px;
  border-radius: var(--radius-sm, 6px);
  display: flex;
  align-items: baseline;
  gap: 6px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.kpi-num {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--color-primary-dark, #0f172a);
}

.kpi-txt {
  font-size: 0.725rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748b);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.active-pill .kpi-num {
  color: #16a34a;
}

.core-pill .kpi-num {
  color: #2563eb;
}

/* Info Alert Box */
.info-alert-box {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: var(--radius-md, 10px);
  padding: 14px 18px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.info-alert-icon {
  font-size: 1.5rem;
  color: #2563eb;
  flex-shrink: 0;
  margin-top: 2px;
}

.info-alert-content h4 {
  font-size: 0.925rem;
  font-weight: 700;
  color: #1e3a8a;
  margin: 0 0 3px 0;
}

.info-alert-content p {
  font-size: 0.825rem;
  color: #3b82f6;
  margin: 0;
  line-height: 1.45;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 40px;
  color: var(--color-text-secondary, #64748b);
}

/* Modules Table Card */
.modules-table-card {
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #cbd5e1);
  border-radius: var(--radius-md, 10px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.card-header-bar {
  padding: 16px 22px;
  border-bottom: 1px solid var(--color-border, #cbd5e1);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-header-bar h3 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-primary-dark, #0f172a);
  margin: 0;
}

.table-subtitle {
  font-size: 0.775rem;
  color: var(--color-text-secondary, #64748b);
}

/* Modules List Rows */
.modules-list {
  display: flex;
  flex-direction: column;
}

.module-row {
  display: flex;
  align-items: center;
  padding: 16px 22px;
  border-bottom: 1px solid #f1f5f9;
  gap: 18px;
  transition: background 0.15s ease;
}

.module-row:last-child {
  border-bottom: none;
}

.module-row:hover {
  background: var(--color-surface-hover, #f8fafc);
}

.module-row.is-inactive-row {
  background: #fdfdfd;
  opacity: 0.85;
}

/* Module Icon */
.module-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
}

.icon-blue { background: #eff6ff; color: #2563eb; }
.icon-green { background: #f0fdf4; color: #16a34a; }
.icon-amber { background: #fffbe8; color: #d97706; }
.icon-indigo { background: #eef2ff; color: #4f46e5; }
.icon-purple { background: #faf5ff; color: #9333ea; }
.icon-slate { background: #f1f5f9; color: #475569; }
.icon-muted { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

/* Module Details */
.module-details {
  flex: 1;
  min-width: 0;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 3px;
}

.module-name {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-text, #0f172a);
  margin: 0;
}

.module-key-tag {
  font-family: monospace;
  font-size: 0.725rem;
  color: #475569;
  background: #f1f5f9;
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.badge-core {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.badge-version {
  background: #f8fafc;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.badge-unreleased {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.module-desc {
  font-size: 0.825rem;
  color: var(--color-text-secondary, #64748b);
  margin: 0 0 6px 0;
  line-height: 1.4;
}

.module-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
  color: #64748b;
}

.meta-divider {
  color: #cbd5e1;
}

.meta-item code {
  font-size: 0.725rem;
  color: #2563eb;
}

/* Status Pill */
.module-status-col {
  flex-shrink: 0;
  min-width: 90px;
  text-align: center;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.status-active {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.status-active .status-dot {
  background: #16a34a;
}

.status-inactive {
  background: #f8fafc;
  color: #64748b;
  border: 1px solid #cbd5e1;
}

.status-inactive .status-dot {
  background: #94a3b8;
}

/* Actions Column */
.module-action-col {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  min-width: 80px;
}

.core-locked-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.locked-hint {
  font-size: 0.65rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Modal Styling */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(2px);
}

.confirm-modal-card {
  background: #ffffff;
  border-radius: var(--radius-lg, 16px);
  width: 90%;
  max-width: 480px;
  padding: 24px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  border: 1px solid #cbd5e1;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 14px;
}

.modal-warning-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  background: #fef2f2;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.modal-header h3 {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 2px 0;
}

.modal-desc {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
}

.modal-body p {
  font-size: 0.875rem;
  color: #334155;
  margin: 0 0 12px 0;
  line-height: 1.5;
}

.modal-impact-list {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 14px 10px 28px;
  margin: 0 0 20px 0;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.5;
}

.modal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.btn-secondary {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 8px 16px;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-secondary:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #94a3b8;
}

.btn-danger {
  background: #dc2626;
  border: 1px solid #b91c1c;
  color: #ffffff;
  padding: 8px 16px;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-secondary:disabled, .btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Toast Notification */
.toast-notification {
  position: fixed;
  bottom: 24px;
  right: 24px;
  padding: 12px 18px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  z-index: 10000;
  animation: slideInUp 0.25s ease;
}

.toast-notification.success {
  background: #0f172a;
  color: #ffffff;
  border-left: 4px solid #16a34a;
}

.toast-notification.success ion-icon {
  color: #22c55e;
  font-size: 1.25rem;
}

.toast-notification.error {
  background: #0f172a;
  color: #ffffff;
  border-left: 4px solid #ef4444;
}

.toast-notification.error ion-icon {
  color: #ef4444;
  font-size: 1.25rem;
}

@keyframes slideInUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
</style>
