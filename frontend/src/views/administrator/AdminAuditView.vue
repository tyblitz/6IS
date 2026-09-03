<!-- frontend/src/views/administrator/AdminAuditView.vue -->
<!-- 6IS Core Audit Logs & Governance Inspection View (Phase 4) -->
<template>
  <MainLayout title="Audit Logs">
    <div class="admin-audit-container">
      
      <!-- Page Header Bar -->
      <div class="header-bar">
        <div class="header-titles">
          <div class="header-breadcrumb-tag">
            <router-link to="/administrator" class="back-link">
              <ion-icon :icon="arrowBackOutline" />
              <span>System Administration</span>
            </router-link>
          </div>
          <h2>Audit Logs & System Activity</h2>
          <p class="subtitle">
            Tamper-evident, immutable record of administrative actions, authentication events, and governance mutations across the 6IS platform.
          </p>
        </div>

        <!-- Summary KPI Counter Pills -->
        <div class="summary-kpis">
          <div class="kpi-pill">
            <span class="kpi-num">{{ pagination.total }}</span>
            <span class="kpi-txt">Total Events</span>
          </div>
          <div class="kpi-pill security-pill">
            <span class="kpi-num">Read-Only</span>
            <span class="kpi-txt">Immutable</span>
          </div>
        </div>
      </div>

      <!-- Filter Toolbar -->
      <div class="filter-card">
        <div class="filter-grid">
          <!-- Search input -->
          <div class="filter-group search-group">
            <label for="audit-search">Search Details / Actor / Entity</label>
            <div class="input-with-icon">
              <ion-icon :icon="searchOutline" class="search-icon" />
              <input
                id="audit-search"
                v-model="filters.search"
                type="text"
                class="form-control"
                placeholder="Search username, entity ID, description..."
                @keyup.enter="applyFilters"
              />
            </div>
          </div>

          <!-- Module Dropdown -->
          <div class="filter-group">
            <label for="audit-module">Module</label>
            <select
              id="audit-module"
              v-model="filters.module_key"
              class="form-control select-control"
              @change="applyFilters"
            >
              <option value="">All Modules</option>
              <option value="auth">Auth & Session</option>
              <option value="users">Users</option>
              <option value="roles">Roles & Permissions</option>
              <option value="modules">Module Registry</option>
              <option value="organization">Organization</option>
              <option value="offices">Offices</option>
            </select>
          </div>

          <!-- Action Dropdown -->
          <div class="filter-group">
            <label for="audit-action">Action</label>
            <select
              id="audit-action"
              v-model="filters.action"
              class="form-control select-control"
              @change="applyFilters"
            >
              <option value="">All Actions</option>
              <option value="LOGIN">LOGIN</option>
              <option value="LOGIN_FAILED">LOGIN_FAILED</option>
              <option value="LOGOUT">LOGOUT</option>
              <option value="CREATE">CREATE</option>
              <option value="UPDATE">UPDATE</option>
              <option value="DELETE">DELETE</option>
              <option value="ACTIVATE">ACTIVATE</option>
              <option value="DEACTIVATE">DEACTIVATE</option>
              <option value="ASSIGN">ASSIGN</option>
            </select>
          </div>

          <!-- Date From -->
          <div class="filter-group">
            <label for="audit-date-from">Date From</label>
            <input
              id="audit-date-from"
              v-model="filters.date_from"
              type="date"
              class="form-control"
              @change="applyFilters"
            />
          </div>

          <!-- Date To -->
          <div class="filter-group">
            <label for="audit-date-to">Date To</label>
            <input
              id="audit-date-to"
              v-model="filters.date_to"
              type="date"
              class="form-control"
              @change="applyFilters"
            />
          </div>
        </div>

        <!-- Filter Action Buttons -->
        <div class="filter-actions">
          <button class="btn btn-secondary" @click="resetFilters">
            <ion-icon :icon="refreshOutline" />
            <span>Reset Filters</span>
          </button>
          <button class="btn btn-primary" @click="applyFilters">
            <ion-icon :icon="funnelOutline" />
            <span>Apply Filters</span>
          </button>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="errorMessage" class="alert-box danger-alert">
        <ion-icon :icon="alertCircleOutline" />
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Audit Logs Table Card -->
      <div class="table-card">
        <!-- Table Header & Per-Page Controls -->
        <div class="table-top-bar">
          <div class="table-title">
            <h3>Audit Trail Records</h3>
            <span class="record-count">Showing page {{ pagination.page }} of {{ pagination.total_pages || 1 }} ({{ pagination.total }} total records)</span>
          </div>
          <div class="per-page-control">
            <label for="per-page-select">Per page:</label>
            <select
              id="per-page-select"
              v-model.number="pagination.limit"
              class="form-control per-page-select"
              @change="changeLimit"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <div class="spinner" />
          <p>Loading audit trail records...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="logs.length === 0" class="empty-state">
          <ion-icon :icon="documentTextOutline" class="empty-icon" />
          <h4>No Audit Records Found</h4>
          <p>No audit trail records matched the selected filters or date range.</p>
          <button class="btn btn-secondary" @click="resetFilters">
            Clear Filters
          </button>
        </div>

        <!-- Data Table -->
        <div v-else class="table-responsive">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Module</th>
                <th>Target Entity</th>
                <th>Description</th>
                <th class="text-right">Inspection</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in logs" :key="entry.id" class="audit-row">
                <!-- Timestamp strictly formatted as DD HHmmH MMM YYYY -->
                <td class="timestamp-cell">
                  {{ formatDateTimeCombined(entry.created_at) }}
                </td>

                <!-- Actor / User -->
                <td class="actor-cell">
                  <div class="actor-info">
                    <span class="actor-name">{{ entry.full_name || entry.username || 'System' }}</span>
                    <span v-if="entry.username && entry.full_name" class="actor-username">@{{ entry.username }}</span>
                  </div>
                </td>

                <!-- Action Badge -->
                <td>
                  <span :class="['badge-action', getActionBadgeClass(entry.action)]">
                    {{ entry.action }}
                  </span>
                </td>

                <!-- Module Key -->
                <td>
                  <span class="module-tag">{{ entry.module_key }}</span>
                </td>

                <!-- Target Entity -->
                <td class="target-cell">
                  <span class="entity-type">{{ entry.entity_type }}</span>
                  <span v-if="entry.entity_id" class="entity-id">#{{ entry.entity_id }}</span>
                </td>

                <!-- Description -->
                <td class="desc-cell" :title="entry.description || ''">
                  {{ entry.description || '—' }}
                </td>

                <!-- Details Action Button -->
                <td class="actions-cell text-right">
                  <button
                    class="btn btn-xs btn-inspect"
                    title="Inspect full audit record"
                    @click="openDetailModal(entry)"
                  >
                    <ion-icon :icon="eyeOutline" />
                    <span>Details</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="pagination.total_pages > 1" class="pagination-footer">
          <button
            class="btn-page"
            :disabled="pagination.page <= 1"
            @click="goToPage(1)"
          >
            First
          </button>
          <button
            class="btn-page"
            :disabled="pagination.page <= 1"
            @click="goToPage(pagination.page - 1)"
          >
            Prev
          </button>
          
          <span class="page-indicator">
            Page {{ pagination.page }} of {{ pagination.total_pages }}
          </span>

          <button
            class="btn-page"
            :disabled="pagination.page >= pagination.total_pages"
            @click="goToPage(pagination.page + 1)"
          >
            Next
          </button>
          <button
            class="btn-page"
            :disabled="pagination.page >= pagination.total_pages"
            @click="goToPage(pagination.total_pages)"
          >
            Last
          </button>
        </div>
      </div>

      <!-- Detail Inspection Modal -->
      <div v-if="selectedEntry" class="modal-backdrop" @click.self="closeDetailModal">
        <div class="modal-dialog audit-detail-modal">
          <!-- Modal Header -->
          <div class="modal-header">
            <div class="modal-header-title">
              <ion-icon :icon="shieldCheckmarkOutline" class="modal-icon" />
              <div>
                <h3>Audit Log Record #{{ selectedEntry.id }}</h3>
                <span class="modal-subtitle">Recorded on {{ formatDateTimeCombined(selectedEntry.created_at) }}</span>
              </div>
            </div>
            <button class="modal-close-btn" @click="closeDetailModal">
              <ion-icon :icon="closeOutline" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="modal-body">
            <!-- Metadata Grid -->
            <div class="detail-meta-grid">
              <div class="meta-item">
                <span class="meta-label">Action</span>
                <span :class="['badge-action', getActionBadgeClass(selectedEntry.action)]">
                  {{ selectedEntry.action }}
                </span>
              </div>
              <div class="meta-item">
                <span class="meta-label">Module</span>
                <span class="meta-val font-semibold">{{ selectedEntry.module_key }}</span>
              </div>
              <div class="meta-item">
                <span class="meta-label">Target Entity</span>
                <span class="meta-val font-semibold">{{ selectedEntry.entity_type }} (ID: {{ selectedEntry.entity_id || 'N/A' }})</span>
              </div>
              <div class="meta-item">
                <span class="meta-label">Actor</span>
                <span class="meta-val">{{ selectedEntry.full_name || selectedEntry.username || 'System' }} (ID: {{ selectedEntry.user_id ?? 'N/A' }})</span>
              </div>
              <div class="meta-item">
                <span class="meta-label">IP Address</span>
                <span class="meta-val font-mono">{{ selectedEntry.ip_address || '—' }}</span>
              </div>
              <div class="meta-item">
                <span class="meta-label">User Agent</span>
                <span class="meta-val font-mono user-agent-text" :title="selectedEntry.user_agent || ''">
                  {{ selectedEntry.user_agent || '—' }}
                </span>
              </div>
            </div>

            <!-- Description -->
            <div class="detail-section">
              <span class="meta-label">Description</span>
              <p class="description-box">{{ selectedEntry.description || 'No descriptive text provided.' }}</p>
            </div>

            <!-- Old vs New Values Comparison -->
            <div class="values-comparison">
              <!-- Old Values -->
              <div class="value-block">
                <div class="value-block-header">
                  <span class="value-badge old-badge">Previous State (Old Values)</span>
                </div>
                <pre class="json-box">{{ formatJson(selectedEntry.old_values) }}</pre>
              </div>

              <!-- New Values -->
              <div class="value-block">
                <div class="value-block-header">
                  <span class="value-badge new-badge">Resulting State (New Values)</span>
                </div>
                <pre class="json-box">{{ formatJson(selectedEntry.new_values) }}</pre>
              </div>
            </div>
          </div>

          <!-- Modal Footer (Close only - Strictly Immutable) -->
          <div class="modal-footer">
            <span class="immutable-notice">
              <ion-icon :icon="lockClosedOutline" />
              <span>Immutable audit record. Modification and deletion are disabled by design.</span>
            </span>
            <button class="btn btn-secondary" @click="closeDetailModal">
              Close
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import MainLayout from '../../layouts/MainLayout.vue'
import type { AuditLogEntry, AuditFilterParams, AuditPagination } from '../../types/audit'
import { fetchAuditLogs } from '../../services/auditService'
import { formatDateTimeCombined } from '../../utils/dateUtils'
import {
  arrowBackOutline,
  shieldCheckmarkOutline,
  searchOutline,
  funnelOutline,
  refreshOutline,
  documentTextOutline,
  eyeOutline,
  closeOutline,
  alertCircleOutline,
  lockClosedOutline
} from 'ionicons/icons'

// State
const logs = ref<AuditLogEntry[]>([])
const loading = ref(false)
const errorMessage = ref('')
const selectedEntry = ref<AuditLogEntry | null>(null)

const filters = reactive<AuditFilterParams>({
  search: '',
  module_key: '',
  action: '',
  date_from: '',
  date_to: ''
})

const pagination = reactive<AuditPagination>({
  page: 1,
  limit: 25,
  total: 0,
  total_pages: 0
})

async function loadAuditLogs() {
  loading.value = true
  errorMessage.value = ''

  try {
    const res = await fetchAuditLogs({
      page: pagination.page,
      limit: pagination.limit,
      search: filters.search ? filters.search.trim() : undefined,
      module_key: filters.module_key || undefined,
      action: filters.action || undefined,
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined
    })

    if (res.success) {
      logs.value = res.data || []
      pagination.page = res.pagination.page
      pagination.limit = res.pagination.limit
      pagination.total = res.pagination.total
      pagination.total_pages = res.pagination.total_pages
    } else {
      errorMessage.value = res.message || 'Failed to load audit logs.'
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Network error fetching audit logs.'
  } finally {
    loading.value = false
  }
}

function applyFilters() {
  pagination.page = 1
  loadAuditLogs()
}

function resetFilters() {
  filters.search = ''
  filters.module_key = ''
  filters.action = ''
  filters.date_from = ''
  filters.date_to = ''
  pagination.page = 1
  loadAuditLogs()
}

function changeLimit() {
  pagination.page = 1
  loadAuditLogs()
}

function goToPage(page: number) {
  if (page < 1 || page > pagination.total_pages) return
  pagination.page = page
  loadAuditLogs()
}

function openDetailModal(entry: AuditLogEntry) {
  selectedEntry.value = entry
}

function closeDetailModal() {
  selectedEntry.value = null
}

function formatJson(val: any): string {
  if (val === null || val === undefined) return 'null'
  if (typeof val === 'string') {
    try {
      const parsed = JSON.parse(val)
      return JSON.stringify(parsed, null, 2)
    } catch {
      return val
    }
  }
  return JSON.stringify(val, null, 2)
}

function getActionBadgeClass(action: string): string {
  const upper = (action || '').toUpperCase()
  if (['CREATE', 'LOGIN', 'ACTIVATE'].includes(upper)) {
    return 'badge-success'
  }
  if (['UPDATE', 'ASSIGN'].includes(upper)) {
    return 'badge-info'
  }
  if (['DEACTIVATE'].includes(upper)) {
    return 'badge-warning'
  }
  if (['DELETE', 'LOGIN_FAILED'].includes(upper)) {
    return 'badge-danger'
  }
  return 'badge-default'
}

onMounted(() => {
  loadAuditLogs()
})
</script>

<style scoped>
.admin-audit-container {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Header Bar */
.header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 1rem;
}

.header-titles {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.header-breadcrumb-tag .back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--color-primary-light, #2563EB);
  text-decoration: none;
  margin-bottom: 0.25rem;
}

.header-breadcrumb-tag .back-link:hover {
  text-decoration: underline;
}

.header-titles h2 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0;
}

.subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
  max-width: 780px;
}

/* KPI Pills */
.summary-kpis {
  display: flex;
  gap: 0.75rem;
}

.kpi-pill {
  background: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: 6px;
  padding: 0.4rem 0.85rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 90px;
}

.kpi-num {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
}

.kpi-txt {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.security-pill {
  background: #EFF6FF;
  border-color: #BFDBFE;
}

.security-pill .kpi-num {
  color: #1E3A8A;
}

/* Filter Card */
.filter-card {
  background: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: 10px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.filter-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 1024px) {
  .filter-grid {
    grid-template-columns: 1fr 1fr;
  }
  .search-group {
    grid-column: 1 / -1;
  }
}

@media (max-width: 640px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.filter-group label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text, #0F172A);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.form-control {
  font-family: inherit;
  font-size: 0.875rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: 6px;
  background-color: var(--color-surface, #FFFFFF);
  color: var(--color-text, #0F172A);
  transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primary-light, #2563EB);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}

.input-with-icon .search-icon {
  position: absolute;
  left: 0.75rem;
  color: var(--color-text-secondary, #64748B);
  font-size: 1rem;
}

.input-with-icon input {
  padding-left: 2.25rem;
  width: 100%;
}

.select-control {
  appearance: auto;
}

.filter-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 0.5rem;
  border-top: 1px solid #F1F5F9;
}

/* Buttons */
.btn {
  font-family: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  transition: all 0.15s ease;
  border: 1px solid transparent;
}

.btn-primary {
  background: var(--color-primary-light, #2563EB);
  color: #FFFFFF;
}

.btn-primary:hover {
  background: #1D4ED8;
}

.btn-secondary {
  background: #FFFFFF;
  border-color: var(--color-border, #CBD5E1);
  color: #334155;
}

.btn-secondary:hover {
  background: #F8FAFC;
}

.btn-xs {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
}

.btn-inspect {
  background: #EFF6FF;
  border: 1px solid #BFDBFE;
  color: #1D4ED8;
}

.btn-inspect:hover {
  background: #DBEAFE;
}

/* Alert Box */
.alert-box {
  padding: 0.85rem 1rem;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.875rem;
}

.danger-alert {
  background-color: #FEF2F2;
  border: 1px solid #FCA5A5;
  color: #B91C1C;
}

/* Table Card */
.table-card {
  background: var(--color-surface, #FFFFFF);
  border: 1px solid var(--color-border, #CBD5E1);
  border-radius: 10px;
  overflow: hidden;
}

.table-top-bar {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.table-title h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--color-primary-dark, #172554);
  margin: 0;
}

.record-count {
  font-size: 0.75rem;
  color: var(--color-text-secondary, #64748B);
}

.per-page-control {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  color: var(--color-text-secondary, #64748B);
}

.per-page-select {
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
}

/* Table */
.table-responsive {
  overflow-x: auto;
}

.audit-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.audit-table th {
  background: #F8FAFC;
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--color-text-secondary, #64748B);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
}

.audit-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #F1F5F9;
  color: var(--color-text, #0F172A);
  vertical-align: middle;
}

.audit-row:hover {
  background: var(--color-surface-hover, #F8FAFC);
}

.timestamp-cell {
  font-weight: 600;
  color: #1E293B;
  white-space: nowrap;
}

.actor-cell {
  white-space: nowrap;
}

.actor-info {
  display: flex;
  flex-direction: column;
}

.actor-name {
  font-weight: 600;
  color: var(--color-text, #0F172A);
}

.actor-username {
  font-size: 0.75rem;
  color: var(--color-text-secondary, #64748B);
}

.module-tag {
  font-size: 0.75rem;
  font-weight: 600;
  color: #475569;
  background: #F1F5F9;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
}

.target-cell {
  white-space: nowrap;
}

.entity-type {
  font-weight: 600;
  color: #334155;
  text-transform: capitalize;
}

.entity-id {
  font-size: 0.75rem;
  color: var(--color-text-secondary, #64748B);
  margin-left: 0.3rem;
}

.desc-cell {
  max-width: 320px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #475569;
}

.text-right {
  text-align: right;
}

/* Action Badges */
.badge-action {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  display: inline-block;
}

.badge-success {
  background: #DCFCE7;
  color: #15803D;
  border: 1px solid #16A34A;
}

.badge-info {
  background: #DBEAFE;
  color: #1D4ED8;
  border: 1px solid #2563EB;
}

.badge-warning {
  background: #FEF3C7;
  color: #B45309;
  border: 1px solid #D97706;
}

.badge-danger {
  background: #FEE2E2;
  color: #B91C1C;
  border: 1px solid #DC2626;
}

.badge-default {
  background: #F1F5F9;
  color: #475569;
  border: 1px solid #CBD5E1;
}

/* Loading & Empty States */
.loading-state, .empty-state {
  padding: 3rem 1.5rem;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  color: var(--color-text-secondary, #64748B);
}

.empty-icon {
  font-size: 2.5rem;
  color: #94A3B8;
}

.empty-state h4 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--color-text, #0F172A);
  margin: 0;
}

.empty-state p {
  margin: 0;
  font-size: 0.875rem;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #E2E8F0;
  border-top-color: var(--color-primary-light, #2563EB);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Pagination Footer */
.pagination-footer {
  padding: 0.85rem 1.25rem;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.5rem;
  border-top: 1px solid var(--color-border, #CBD5E1);
  background: #FAFAFA;
}

.btn-page {
  font-family: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--color-border, #CBD5E1);
  background: #FFFFFF;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-page:hover:not(:disabled) {
  background: #F1F5F9;
  border-color: #94A3B8;
}

.btn-page:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-indicator {
  font-size: 0.8rem;
  color: var(--color-text-secondary, #64748B);
  margin: 0 0.5rem;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 1.5rem;
}

.audit-detail-modal {
  background: #FFFFFF;
  border-radius: 16px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: 100%;
  max-width: 860px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--color-border, #CBD5E1);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header-title {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.modal-icon {
  font-size: 1.75rem;
  color: var(--color-primary-light, #2563EB);
}

.modal-header-title h3 {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0;
}

.modal-subtitle {
  font-size: 0.8rem;
  color: var(--color-text-secondary, #64748B);
}

.modal-close-btn {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  color: #64748B;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
}

.modal-close-btn:hover {
  background: #F1F5F9;
  color: #0F172A;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.detail-meta-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  background: #F8FAFC;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid #E2E8F0;
}

@media (max-width: 640px) {
  .detail-meta-grid {
    grid-template-columns: 1fr;
  }
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.meta-label {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--color-text-secondary, #64748B);
  letter-spacing: 0.05em;
}

.meta-val {
  font-size: 0.875rem;
  color: var(--color-text, #0F172A);
}

.font-mono {
  font-family: monospace;
  font-size: 0.8rem;
}

.font-semibold {
  font-weight: 600;
}

.user-agent-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 240px;
}

.detail-section {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.description-box {
  margin: 0;
  font-size: 0.875rem;
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 6px;
  padding: 0.75rem;
  color: var(--color-text, #0F172A);
}

.values-comparison {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 640px) {
  .values-comparison {
    grid-template-columns: 1fr;
  }
}

.value-block {
  display: flex;
  flex-direction: column;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  overflow: hidden;
}

.value-block-header {
  background: #F8FAFC;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #E2E8F0;
}

.value-badge {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.old-badge {
  color: #92400E;
}

.new-badge {
  color: #065F46;
}

.json-box {
  margin: 0;
  padding: 0.75rem;
  background: #0F172A;
  color: #E2E8F0;
  font-family: monospace;
  font-size: 0.75rem;
  max-height: 220px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-all;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--color-border, #CBD5E1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #FAFAFA;
}

.immutable-notice {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  color: var(--color-text-secondary, #64748B);
  font-style: italic;
}
</style>
