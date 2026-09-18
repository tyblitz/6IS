<!-- frontend/src/views/budget/BudgetMonitoringView.vue -->
<!-- Budget & R&M Fund Allocation and Disbursement Monitoring Module -->

<template>
  <MainLayout title="Budget & R&M Monitoring">
    <div class="budget-view-container">

      <!-- PAGE HEADER -->
      <div class="page-header">
        <div class="header-content">
          <div class="title-with-badge">
            <h2 class="page-title">Budget & R&M Monitoring</h2>
            <span class="system-badge">MOOE</span>
          </div>
          <p class="page-subtitle">
            Approved Annual Fund Release Schedule Matrix and Actual Released Funds Ledger.
          </p>
        </div>
        <div class="header-actions">
          <button
            v-if="hasPermission('budget', 'create')"
            class="btn-primary"
            type="button"
            @click="openDisbursementModal"
          >
            <ion-icon :icon="addCircleOutline" class="btn-icon"></ion-icon>
            Record Release
          </button>
          <button
            v-if="hasPermission('budget', 'create')"
            class="btn-secondary"
            type="button"
            @click="openScheduleModal"
          >
            <ion-icon :icon="calendarOutline" class="btn-icon"></ion-icon>
            Add Schedule
          </button>
          <button
            class="btn-secondary"
            type="button"
            @click="exportCsv"
          >
            <ion-icon :icon="downloadOutline" class="btn-icon"></ion-icon>
            Export CSV (Data)
          </button>
        </div>
      </div>

      <!-- EXECUTIVE SUMMARY METRIC CARDS -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Approved MOOE Total</span>
            <div class="metric-icon-box info-box">
              <ion-icon :icon="walletOutline"></ion-icon>
            </div>
          </div>
          <div class="metric-value">₱{{ formatCurrency(metrics.mooe_total) }}</div>
          <div class="metric-subtext">Approved annual baseline allocation</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Total Disbursed</span>
            <div class="metric-icon-box success-box">
              <ion-icon :icon="cashOutline"></ion-icon>
            </div>
          </div>
          <div class="metric-value text-success">₱{{ formatCurrency(metrics.overall_disbursed_total) }}</div>
          <div class="metric-subtext">
            Actual funds released
            <span v-if="metrics.unallocated_disbursed_total > 0" class="unallocated-notice">
              (₱{{ formatCurrency(metrics.unallocated_disbursed_total) }} unlinked)
            </span>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Remaining Balance</span>
            <div class="metric-icon-box warning-box">
              <ion-icon :icon="pieChartOutline"></ion-icon>
            </div>
          </div>
          <div class="metric-value text-primary">₱{{ formatCurrency(metrics.overall_remaining_balance) }}</div>
          <div class="metric-subtext">Dynamic unobligated balance</div>
        </div>

        <div class="metric-card">
          <div class="metric-card-header">
            <span class="metric-label">Scheduled Releases</span>
            <div class="metric-icon-box neutral-box">
              <ion-icon :icon="calendarNumberOutline"></ion-icon>
            </div>
          </div>
          <div class="metric-value">{{ metrics.total_scheduled_releases }}</div>
          <div class="metric-subtext">Planned release occurrences across {{ metrics.total_schedule_items }} lines</div>
        </div>
      </div>

      <!-- NAVIGATION TABS -->
      <div class="tabs-container">
        <div class="tabs-nav">
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'schedule' }"
            @click="activeTab = 'schedule'"
          >
            <ion-icon :icon="gridOutline" class="tab-icon"></ion-icon>
            Annual Fund Schedule Matrix
            <span class="tab-badge">{{ schedules.length }}</span>
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'ledger' }"
            @click="activeTab = 'ledger'"
          >
            <ion-icon :icon="receiptOutline" class="tab-icon"></ion-icon>
            Released Funds Ledger
            <span class="tab-badge">{{ disbursements.length }}</span>
          </button>
        </div>

        <!-- TAB 1: ANNUAL FUND SCHEDULE MATRIX -->
        <div v-if="activeTab === 'schedule'" class="tab-pane">
          <div class="table-card">
            <div class="table-card-header">
              <div class="table-header-left">
                <h3 class="table-card-title">Annual Fund Release Schedule</h3>
                <span class="table-item-count">{{ filteredSchedules.length }} Items</span>
              </div>
              <div class="table-header-right">
                <div v-if="canCrossOffice" class="filter-select-group">
                  <label class="filter-label">Office:</label>
                  <select v-model="selectedOfficeFilter" class="filter-select">
                    <option value="">All Offices</option>
                    <option v-for="off in officeOptions" :key="off.id" :value="off.id">
                      {{ off.office_code }} — {{ off.office_name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoadingSchedules" class="state-container">
              <div class="spinner"></div>
              <p class="state-text">Loading fund allocation schedules...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredSchedules.length === 0" class="state-container">
              <ion-icon :icon="documentTextOutline" class="state-icon"></ion-icon>
              <h4 class="state-title">No Fund Schedules Found</h4>
              <p class="state-text">No allocation schedule lines matched the current criteria.</p>
            </div>

            <!-- Table -->
            <div v-else class="table-responsive">
              <table class="data-table schedule-matrix-table">
                <thead>
                  <tr>
                    <th class="col-nr">Nr</th>
                    <th class="col-office">Office</th>
                    <th class="col-paps">PAPS Description</th>
                    <th v-for="m in monthHeaders" :key="m.key" class="col-month">{{ m.label }}</th>
                    <th class="col-total-releases">Scheduled Releases</th>
                    <th class="col-amount text-right">Allocated Budget</th>
                    <th class="col-amount text-right">Actual Disbursed</th>
                    <th class="col-amount text-right">Remaining Balance</th>
                    <th v-if="hasPermission('budget', 'edit') || hasPermission('budget', 'delete')" class="col-actions text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in filteredSchedules" :key="row.id" class="table-row">
                    <td class="col-nr">{{ idx + 1 }}</td>
                    <td class="col-office">
                      <span class="office-code-badge">{{ row.office_code }}</span>
                    </td>
                    <td class="col-paps" :title="row.paps">
                      <div class="paps-title">{{ row.paps }}</div>
                      <div v-if="row.remarks" class="paps-remarks">{{ row.remarks }}</div>
                    </td>
                    <!-- Jan - Dec Monthly Cells -->
                    <td v-for="m in monthHeaders" :key="m.key" class="col-month text-center">
                      <span
                        v-if="row[m.key] > 0"
                        class="month-pill"
                        :class="isMonthDisbursed(row, m.monthNr) ? 'month-pill-disbursed' : 'month-pill-scheduled'"
                        :title="isMonthDisbursed(row, m.monthNr) ? `Disbursement recorded for ${m.label}` : `Planned ${row[m.key]} release(s)`"
                      >
                        {{ row[m.key] }}
                      </span>
                      <span v-else class="month-dash">—</span>
                    </td>
                    <td class="col-total-releases text-center">
                      <span class="total-releases-badge">{{ row.schedule_release_count }}</span>
                    </td>
                    <td class="col-amount text-right font-medium">₱{{ formatCurrency(row.allocated_amount) }}</td>
                    <td class="col-amount text-right font-medium text-success">₱{{ formatCurrency(row.disbursed_total) }}</td>
                    <td class="col-amount text-right font-bold" :class="row.remaining_balance < 0 ? 'text-danger' : 'text-primary'">
                      ₱{{ formatCurrency(row.remaining_balance) }}
                    </td>
                    <td v-if="hasPermission('budget', 'edit') || hasPermission('budget', 'delete')" class="col-actions text-center">
                      <div class="row-actions">
                        <button
                          v-if="hasPermission('budget', 'edit')"
                          type="button"
                          class="action-btn edit-btn"
                          title="Edit Schedule Line"
                          @click="openEditScheduleModal(row)"
                        >
                          <ion-icon :icon="createOutline"></ion-icon>
                        </button>
                        <button
                          v-if="hasPermission('budget', 'delete')"
                          type="button"
                          class="action-btn delete-btn"
                          title="Delete Schedule Line"
                          @click="confirmDeleteSchedule(row)"
                        >
                          <ion-icon :icon="trashOutline"></ion-icon>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="table-summary-row">
                    <td colspan="3" class="font-bold text-right summary-label">Summary Totals:</td>
                    <td colspan="12" class="text-center font-bold">
                      <span class="summary-months-note">{{ summaryTotals.scheduledReleases }} Planned Releases</span>
                    </td>
                    <td class="text-center font-bold">{{ summaryTotals.scheduledReleases }}</td>
                    <td class="text-right font-bold">₱{{ formatCurrency(summaryTotals.allocated) }}</td>
                    <td class="text-right font-bold text-success">₱{{ formatCurrency(summaryTotals.disbursed) }}</td>
                    <td class="text-right font-bold text-primary">₱{{ formatCurrency(summaryTotals.balance) }}</td>
                    <td v-if="hasPermission('budget', 'edit') || hasPermission('budget', 'delete')"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- TAB 2: RELEASED FUNDS LEDGER -->
        <div v-if="activeTab === 'ledger'" class="tab-pane">
          <div class="table-card">
            <div class="table-card-header">
              <div class="table-header-left">
                <h3 class="table-card-title">Released Funds Ledger</h3>
                <span class="table-item-count">{{ filteredDisbursements.length }} Transactions</span>
              </div>
              <div class="table-header-right">
                <!-- Search -->
                <div class="search-input-box">
                  <ion-icon :icon="searchOutline" class="search-icon"></ion-icon>
                  <input
                    v-model="ledgerSearch"
                    type="text"
                    placeholder="Search particulars, receiver, or chargeability..."
                    class="search-input"
                  />
                  <button v-if="ledgerSearch" type="button" class="clear-search-btn" @click="ledgerSearch = ''">
                    <ion-icon :icon="closeCircleOutline"></ion-icon>
                  </button>
                </div>
                <!-- Office Filter -->
                <div v-if="canCrossOffice" class="filter-select-group">
                  <label class="filter-label">Office:</label>
                  <select v-model="selectedDisbOfficeFilter" class="filter-select">
                    <option value="">All Offices</option>
                    <option v-for="off in officeOptions" :key="off.id" :value="off.id">
                      {{ off.office_code }} — {{ off.office_name }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoadingDisbursements" class="state-container">
              <div class="spinner"></div>
              <p class="state-text">Loading disbursement ledger...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="filteredDisbursements.length === 0" class="state-container">
              <ion-icon :icon="receiptOutline" class="state-icon"></ion-icon>
              <h4 class="state-title">No Disbursement Transactions Found</h4>
              <p class="state-text">No fund releases match the active search or office filters.</p>
            </div>

            <!-- Table -->
            <div v-else class="table-responsive">
              <table class="data-table ledger-table">
                <thead>
                  <tr>
                    <th class="col-nr">ID</th>
                    <th class="col-date">Release Date</th>
                    <th class="col-office">Office</th>
                    <th class="col-paps">PAPS Project</th>
                    <th class="col-particulars">Particulars</th>
                    <th class="col-quarter text-center">Quarter</th>
                    <th class="col-amount text-right">Amount Disbursed</th>
                    <th class="col-receiver">Received By</th>
                    <th class="col-charge">Chargeability</th>
                    <th v-if="hasPermission('budget', 'edit') || hasPermission('budget', 'delete')" class="col-actions text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="dr in filteredDisbursements" :key="dr.id" class="table-row">
                    <td class="col-nr font-mono">#{{ dr.id }}</td>
                    <td class="col-date font-medium">{{ formatDateMilitary(dr.disbursement_date) }}</td>
                    <td class="col-office">
                      <span class="office-code-badge">{{ dr.office_code }}</span>
                    </td>
                    <td class="col-paps font-medium">{{ dr.paps }}</td>
                    <td class="col-particulars">
                      <div class="particulars-text">{{ dr.particulars }}</div>
                      <div v-if="dr.remarks" class="dr-remarks">{{ dr.remarks }}</div>
                    </td>
                    <td class="col-quarter text-center">
                      <span class="quarter-badge">{{ dr.quarter }}</span>
                    </td>
                    <td class="col-amount text-right font-bold text-success">₱{{ formatCurrency(dr.amount_disbursed) }}</td>
                    <td class="col-receiver">{{ dr.received_by || '—' }}</td>
                    <td class="col-charge">
                      <span class="charge-badge" :title="dr.chargeability || ''">{{ dr.chargeability || '—' }}</span>
                    </td>
                    <td v-if="hasPermission('budget', 'edit') || hasPermission('budget', 'delete')" class="col-actions text-center">
                      <div class="row-actions">
                        <button
                          v-if="hasPermission('budget', 'edit')"
                          type="button"
                          class="action-btn edit-btn"
                          title="Edit Disbursement"
                          @click="openEditDisbursementModal(dr)"
                        >
                          <ion-icon :icon="createOutline"></ion-icon>
                        </button>
                        <button
                          v-if="hasPermission('budget', 'delete')"
                          type="button"
                          class="action-btn delete-btn"
                          title="Delete Disbursement"
                          @click="confirmDeleteDisbursement(dr)"
                        >
                          <ion-icon :icon="trashOutline"></ion-icon>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="table-summary-row">
                    <td colspan="6" class="font-bold text-right summary-label">Total Disbursed:</td>
                    <td class="text-right font-bold text-success">₱{{ formatCurrency(ledgerTotalDisbursed) }}</td>
                    <td colspan="3"></td>
                    <td v-if="hasPermission('budget', 'edit') || hasPermission('budget', 'delete')"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL: RECORD / EDIT DISBURSEMENT -->
      <div v-if="showDisbursementModal" class="modal-backdrop" @click.self="closeDisbursementModal">
        <div class="modal-card">
          <div class="modal-header">
            <h3 class="modal-title">{{ editingDisbursementId ? 'Edit Fund Disbursement' : 'Record Fund Disbursement' }}</h3>
            <button type="button" class="modal-close-btn" @click="closeDisbursementModal">
              <ion-icon :icon="closeOutline"></ion-icon>
            </button>
          </div>
          <form @submit.prevent="saveDisbursement">
            <div class="modal-body">
              <div v-if="formError" class="alert-box alert-danger">
                {{ formError }}
              </div>

              <!-- Schedule Selector -->
              <div class="form-group">
                <label class="form-label required">Target Schedule Allocation:</label>
                <select
                  v-model="disbursementForm.schedule_id"
                  class="form-input"
                  required
                  :disabled="!!editingDisbursementId"
                >
                  <option :value="0" disabled>Select approved schedule allocation...</option>
                  <option v-for="sch in scheduleOptions" :key="sch.id" :value="sch.id">
                    {{ sch.office_code }} — {{ sch.paps }} (FY {{ sch.fiscal_year }}: ₱{{ formatCurrency(sch.allocated_amount) }})
                  </option>
                </select>
                <p class="form-hint">Disbursement office is derived automatically from the selected schedule.</p>
              </div>

              <div class="form-row">
                <div class="form-group flex-1">
                  <label class="form-label required">Release Date:</label>
                  <input
                    v-model="disbursementForm.disbursement_date"
                    type="date"
                    class="form-input"
                    required
                  />
                </div>
                <div class="form-group flex-1">
                  <label class="form-label required">Amount Disbursed (₱):</label>
                  <input
                    v-model.number="disbursementForm.amount_disbursed"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    class="form-input"
                    required
                  />
                </div>
              </div>

              <div class="form-group">
                <label class="form-label required">Particulars:</label>
                <input
                  v-model="disbursementForm.particulars"
                  type="text"
                  placeholder="e.g. R/M - ICT Eqmt - Q1 - OG10"
                  class="form-input"
                  required
                />
              </div>

              <div class="form-row">
                <div class="form-group flex-1">
                  <label class="form-label">Received By:</label>
                  <input
                    v-model="disbursementForm.received_by"
                    type="text"
                    placeholder="e.g. Ms De Jesus"
                    class="form-input"
                  />
                </div>
                <div class="form-group flex-1">
                  <label class="form-label">Chargeability:</label>
                  <input
                    v-model="disbursementForm.chargeability"
                    type="text"
                    placeholder="e.g. Comd - Q1 FY 2026 MOOE - R/M - ICT Eqmt"
                    class="form-input"
                  />
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Remarks:</label>
                <textarea
                  v-model="disbursementForm.remarks"
                  rows="2"
                  placeholder="Optional operational remarks..."
                  class="form-textarea"
                ></textarea>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeDisbursementModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Saving...' : (editingDisbursementId ? 'Update Release' : 'Record Release') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- MODAL: ADD / EDIT SCHEDULE -->
      <div v-if="showScheduleModal" class="modal-backdrop" @click.self="closeScheduleModal">
        <div class="modal-card modal-lg">
          <div class="modal-header">
            <h3 class="modal-title">{{ editingScheduleId ? 'Edit Schedule Allocation' : 'Add Schedule Allocation' }}</h3>
            <button type="button" class="modal-close-btn" @click="closeScheduleModal">
              <ion-icon :icon="closeOutline"></ion-icon>
            </button>
          </div>
          <form @submit.prevent="saveSchedule">
            <div class="modal-body">
              <div v-if="formError" class="alert-box alert-danger">
                {{ formError }}
              </div>

              <div class="form-row">
                <div class="form-group flex-1">
                  <label class="form-label required">Office / Unit:</label>
                  <select
                    v-model="scheduleForm.office_id"
                    class="form-input"
                    required
                    :disabled="!canCrossOffice && !editingScheduleId"
                  >
                    <option :value="0" disabled>Select office...</option>
                    <option v-for="off in officeOptions" :key="off.id" :value="off.id">
                      {{ off.office_code }} — {{ off.office_name }}
                    </option>
                  </select>
                </div>
                <div class="form-group flex-1">
                  <label class="form-label required">Fiscal Year:</label>
                  <input
                    v-model.number="scheduleForm.fiscal_year"
                    type="number"
                    min="2020"
                    max="2035"
                    class="form-input"
                    required
                  />
                </div>
              </div>

              <div class="form-row">
                <div class="form-group flex-2">
                  <label class="form-label required">PAPS Description:</label>
                  <input
                    v-model="scheduleForm.paps"
                    type="text"
                    placeholder="e.g. R & M of ICT Equipments"
                    class="form-input"
                    required
                  />
                </div>
                <div class="form-group flex-1">
                  <label class="form-label required">Allocated Budget (₱):</label>
                  <input
                    v-model.number="scheduleForm.allocated_amount"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    class="form-input"
                    required
                  />
                </div>
              </div>

              <!-- Monthly Release Occurrences -->
              <div class="form-group">
                <label class="form-label">Planned Release Occurrences (Jan – Dec):</label>
                <div class="month-inputs-grid">
                  <div v-for="m in monthHeaders" :key="m.key" class="month-input-col">
                    <span class="month-input-label">{{ m.label }}</span>
                    <input
                      v-model.number="scheduleForm[m.key]"
                      type="number"
                      min="0"
                      max="12"
                      step="1"
                      class="month-number-input"
                    />
                  </div>
                </div>
                <p class="form-hint">Enter the planned release count for each month (e.g. 1, 2, or 4).</p>
              </div>

              <div class="form-group">
                <label class="form-label">Remarks:</label>
                <textarea
                  v-model="scheduleForm.remarks"
                  rows="2"
                  placeholder="Optional notes or references..."
                  class="form-textarea"
                ></textarea>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeScheduleModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Saving...' : (editingScheduleId ? 'Update Schedule' : 'Create Schedule') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- DELETE CONFIRMATION MODAL -->
      <div v-if="showDeleteModal" class="modal-backdrop" @click.self="showDeleteModal = false">
        <div class="modal-card modal-sm">
          <div class="modal-header">
            <h3 class="modal-title text-danger">Confirm Deletion</h3>
            <button type="button" class="modal-close-btn" @click="showDeleteModal = false">
              <ion-icon :icon="closeOutline"></ion-icon>
            </button>
          </div>
          <div class="modal-body">
            <div v-if="deleteError" class="alert-box alert-danger">
              {{ deleteError }}
            </div>
            <p class="delete-msg">
              Are you sure you want to soft-delete this <strong>{{ deleteTargetType }}</strong> record?
            </p>
            <p v-if="deleteTargetType === 'schedule'" class="delete-warning">
              Note: A schedule cannot be deleted if active linked disbursements exist.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="showDeleteModal = false">Cancel</button>
            <button type="button" class="btn-danger" :disabled="isSubmitting" @click="executeDelete">
              {{ isSubmitting ? 'Deleting...' : 'Delete Record' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  addCircleOutline,
  calendarOutline,
  downloadOutline,
  walletOutline,
  cashOutline,
  pieChartOutline,
  calendarNumberOutline,
  gridOutline,
  receiptOutline,
  createOutline,
  trashOutline,
  searchOutline,
  closeCircleOutline,
  closeOutline,
  documentTextOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { activeUser } from '../../services/authService'
import { usePermissions } from '../../composables/usePermissions'
import { formatDate } from '../../utils/dateUtils'
import type {
  BudgetScheduleItem,
  BudgetDisbursementItem,
  BudgetMetrics,
  BudgetOfficeOption,
  BudgetScheduleOption
} from '../../types/budget'
import {
  fetchBudgetSchedules,
  fetchBudgetDisbursements,
  fetchBudgetOptions,
  createDisbursement,
  createSchedule,
  updateDisbursement,
  updateSchedule,
  deleteBudgetSchedule,
  deleteBudgetDisbursement
} from '../../services/budgetService'

const { hasPermission } = usePermissions()

// Tab state
const activeTab = ref<'schedule' | 'ledger'>('schedule')

// Data states
const schedules = ref<BudgetScheduleItem[]>([])
const disbursements = ref<BudgetDisbursementItem[]>([])
const metrics = reactive<BudgetMetrics>({
  mooe_total: 0,
  overall_disbursed_total: 0,
  unallocated_disbursed_total: 0,
  overall_remaining_balance: 0,
  total_scheduled_releases: 0,
  total_schedule_items: 0
})

const officeOptions = ref<BudgetOfficeOption[]>([])
const scheduleOptions = ref<BudgetScheduleOption[]>([])

const isLoadingSchedules = ref(true)
const isLoadingDisbursements = ref(true)
const isSubmitting = ref(false)

// Filters
const selectedOfficeFilter = ref<number | ''>('')
const selectedDisbOfficeFilter = ref<number | ''>('')
const ledgerSearch = ref('')

const canCrossOffice = computed(() => {
  if (!activeUser.value) return false
  if (!activeUser.value.office_id || activeUser.value.office_id <= 0) return true
  if (activeUser.value.role === 'Administrator') return true
  return hasPermission('offices', 'configure') ||
         hasPermission('organization', 'configure') ||
         hasPermission('audit', 'view') ||
         hasPermission('users', 'view')
})

type MonthKey = 'jan' | 'feb' | 'mar' | 'apr' | 'may' | 'jun' | 'jul' | 'aug' | 'sep' | 'oct' | 'nov' | 'dec'

interface MonthHeader {
  key: MonthKey
  label: string
  monthNr: number
}

// Month Column Headers Configuration
const monthHeaders: MonthHeader[] = [
  { key: 'jan', label: 'Jan', monthNr: 1 },
  { key: 'feb', label: 'Feb', monthNr: 2 },
  { key: 'mar', label: 'Mar', monthNr: 3 },
  { key: 'apr', label: 'Apr', monthNr: 4 },
  { key: 'may', label: 'May', monthNr: 5 },
  { key: 'jun', label: 'Jun', monthNr: 6 },
  { key: 'jul', label: 'Jul', monthNr: 7 },
  { key: 'aug', label: 'Aug', monthNr: 8 },
  { key: 'sep', label: 'Sep', monthNr: 9 },
  { key: 'oct', label: 'Oct', monthNr: 10 },
  { key: 'nov', label: 'Nov', monthNr: 11 },
  { key: 'dec', label: 'Dec', monthNr: 12 }
]

// Currency and Date Format Helpers
function formatCurrency(val: number | null | undefined): string {
  if (val === null || val === undefined || isNaN(val)) return '0.00'
  return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDateMilitary(dateStr: string): string {
  if (!dateStr) return '—'
  return formatDate(dateStr)
}

function isMonthDisbursed(row: BudgetScheduleItem, monthNr: number): boolean {
  return Array.isArray(row.disbursed_months) && row.disbursed_months.includes(monthNr)
}

// Filtered Lists
const filteredSchedules = computed(() => {
  let list = schedules.value
  if (selectedOfficeFilter.value) {
    list = list.filter(s => s.office_id === selectedOfficeFilter.value)
  }
  return list
})

const filteredDisbursements = computed(() => {
  let list = disbursements.value
  if (selectedDisbOfficeFilter.value) {
    list = list.filter(d => d.office_id === selectedDisbOfficeFilter.value)
  }
  if (ledgerSearch.value.trim()) {
    const q = ledgerSearch.value.toLowerCase().trim()
    list = list.filter(d =>
      d.particulars.toLowerCase().includes(q) ||
      (d.received_by && d.received_by.toLowerCase().includes(q)) ||
      (d.chargeability && d.chargeability.toLowerCase().includes(q)) ||
      d.office_code.toLowerCase().includes(q)
    )
  }
  return list
})

const summaryTotals = computed(() => {
  let allocated = 0
  let disbursed = 0
  let balance = 0
  let scheduledReleases = 0

  for (const s of filteredSchedules.value) {
    allocated += s.allocated_amount
    disbursed += s.disbursed_total
    balance += s.remaining_balance
    scheduledReleases += s.schedule_release_count
  }

  return { allocated, disbursed, balance, scheduledReleases }
})

const ledgerTotalDisbursed = computed(() => {
  return filteredDisbursements.value.reduce((acc, curr) => acc + curr.amount_disbursed, 0)
})

// Load Data
async function loadData() {
  await Promise.all([loadSchedules(), loadDisbursements(), loadOptions()])
}

async function loadOptions() {
  try {
    const res = await fetchBudgetOptions()
    officeOptions.value = res.offices
    scheduleOptions.value = res.schedules
  } catch (err) {
    console.error('Failed to load budget options:', err)
  }
}

async function loadSchedules() {
  isLoadingSchedules.value = true
  try {
    const res = await fetchBudgetSchedules()
    schedules.value = res.schedules
    Object.assign(metrics, res.metrics)
  } catch (err) {
    console.error('Failed to load schedules:', err)
  } finally {
    isLoadingSchedules.value = false
  }
}

async function loadDisbursements() {
  isLoadingDisbursements.value = true
  try {
    const res = await fetchBudgetDisbursements()
    disbursements.value = res.disbursements
  } catch (err) {
    console.error('Failed to load disbursements:', err)
  } finally {
    isLoadingDisbursements.value = false
  }
}

// Modal States
const showDisbursementModal = ref(false)
const editingDisbursementId = ref<number | null>(null)
const disbursementForm = reactive({
  schedule_id: 0,
  disbursement_date: new Date().toISOString().split('T')[0],
  particulars: '',
  amount_disbursed: 0,
  received_by: '',
  chargeability: '',
  remarks: ''
})

const showScheduleModal = ref(false)
const editingScheduleId = ref<number | null>(null)
const scheduleForm = reactive<{
  office_id: number
  fiscal_year: number
  paps: string
  allocated_amount: number
  remarks: string
  [key: string]: any
}>({
  office_id: 0,
  fiscal_year: 2026,
  paps: '',
  allocated_amount: 0,
  jan: 0, feb: 0, mar: 0, apr: 0, may: 0, jun: 0,
  jul: 0, aug: 0, sep: 0, oct: 0, nov: 0, dec: 0,
  remarks: ''
})

const showDeleteModal = ref(false)
const deleteTargetId = ref<number | null>(null)
const deleteTargetType = ref<'schedule' | 'disbursement'>('schedule')
const deleteError = ref('')
const formError = ref('')

function openDisbursementModal() {
  formError.value = ''
  editingDisbursementId.value = null
  disbursementForm.schedule_id = scheduleOptions.value[0]?.id || 0
  disbursementForm.disbursement_date = new Date().toISOString().split('T')[0]
  disbursementForm.particulars = ''
  disbursementForm.amount_disbursed = 0
  disbursementForm.received_by = ''
  disbursementForm.chargeability = ''
  disbursementForm.remarks = ''
  showDisbursementModal.value = true
}

function openEditDisbursementModal(item: BudgetDisbursementItem) {
  formError.value = ''
  editingDisbursementId.value = item.id
  disbursementForm.schedule_id = item.schedule_id || 0
  disbursementForm.disbursement_date = item.disbursement_date
  disbursementForm.particulars = item.particulars
  disbursementForm.amount_disbursed = item.amount_disbursed
  disbursementForm.received_by = item.received_by || ''
  disbursementForm.chargeability = item.chargeability || ''
  disbursementForm.remarks = item.remarks || ''
  showDisbursementModal.value = true
}

function closeDisbursementModal() {
  showDisbursementModal.value = false
  editingDisbursementId.value = null
}

async function saveDisbursement() {
  formError.value = ''
  isSubmitting.value = true
  try {
    if (editingDisbursementId.value) {
      await updateDisbursement({
        id: editingDisbursementId.value,
        type: 'disbursement',
        schedule_id: disbursementForm.schedule_id,
        disbursement_date: disbursementForm.disbursement_date,
        particulars: disbursementForm.particulars,
        amount_disbursed: disbursementForm.amount_disbursed,
        received_by: disbursementForm.received_by,
        chargeability: disbursementForm.chargeability,
        remarks: disbursementForm.remarks
      })
    } else {
      await createDisbursement({
        action: 'disbursement',
        schedule_id: disbursementForm.schedule_id,
        disbursement_date: disbursementForm.disbursement_date,
        particulars: disbursementForm.particulars,
        amount_disbursed: disbursementForm.amount_disbursed,
        received_by: disbursementForm.received_by,
        chargeability: disbursementForm.chargeability,
        remarks: disbursementForm.remarks
      })
    }
    closeDisbursementModal()
    await loadData()
  } catch (err: any) {
    formError.value = err.message || 'Failed to save disbursement.'
  } finally {
    isSubmitting.value = false
  }
}

function openScheduleModal() {
  formError.value = ''
  editingScheduleId.value = null
  scheduleForm.office_id = (activeUser.value?.office_id && !canCrossOffice.value)
    ? activeUser.value.office_id
    : (officeOptions.value[0]?.id || 0)
  scheduleForm.fiscal_year = 2026
  scheduleForm.paps = ''
  scheduleForm.allocated_amount = 0
  for (const m of monthHeaders) {
    scheduleForm[m.key] = 0
  }
  scheduleForm.remarks = ''
  showScheduleModal.value = true
}

function openEditScheduleModal(row: BudgetScheduleItem) {
  formError.value = ''
  editingScheduleId.value = row.id
  scheduleForm.office_id = row.office_id
  scheduleForm.fiscal_year = row.fiscal_year
  scheduleForm.paps = row.paps
  scheduleForm.allocated_amount = row.allocated_amount
  for (const m of monthHeaders) {
    scheduleForm[m.key] = row[m.key as keyof BudgetScheduleItem] as number || 0
  }
  scheduleForm.remarks = row.remarks || ''
  showScheduleModal.value = true
}

function closeScheduleModal() {
  showScheduleModal.value = false
  editingScheduleId.value = null
}

async function saveSchedule() {
  formError.value = ''
  for (const m of monthHeaders) {
    const val = Number(scheduleForm[m.key] || 0)
    if (val < 0 || val > 12) {
      formError.value = `Planned release count for ${m.label} must be between 0 and 12.`
      return
    }
  }

  isSubmitting.value = true
  try {
    if (editingScheduleId.value) {
      await updateSchedule({
        id: editingScheduleId.value,
        type: 'schedule',
        paps: scheduleForm.paps,
        allocated_amount: scheduleForm.allocated_amount,
        jan: scheduleForm.jan,
        feb: scheduleForm.feb,
        mar: scheduleForm.mar,
        apr: scheduleForm.apr,
        may: scheduleForm.may,
        jun: scheduleForm.jun,
        jul: scheduleForm.jul,
        aug: scheduleForm.aug,
        sep: scheduleForm.sep,
        oct: scheduleForm.oct,
        nov: scheduleForm.nov,
        dec: scheduleForm.dec,
        remarks: scheduleForm.remarks
      })
    } else {
      await createSchedule({
        action: 'schedule',
        fiscal_year: scheduleForm.fiscal_year,
        office_id: scheduleForm.office_id,
        paps: scheduleForm.paps,
        allocated_amount: scheduleForm.allocated_amount,
        jan: scheduleForm.jan,
        feb: scheduleForm.feb,
        mar: scheduleForm.mar,
        apr: scheduleForm.apr,
        may: scheduleForm.may,
        jun: scheduleForm.jun,
        jul: scheduleForm.jul,
        aug: scheduleForm.aug,
        sep: scheduleForm.sep,
        oct: scheduleForm.oct,
        nov: scheduleForm.nov,
        dec: scheduleForm.dec,
        remarks: scheduleForm.remarks
      })
    }
    closeScheduleModal()
    await loadData()
  } catch (err: any) {
    formError.value = err.message || 'Failed to save schedule.'
  } finally {
    isSubmitting.value = false
  }
}

function confirmDeleteSchedule(row: BudgetScheduleItem) {
  deleteError.value = ''
  deleteTargetId.value = row.id
  deleteTargetType.value = 'schedule'
  showDeleteModal.value = true
}

function confirmDeleteDisbursement(item: BudgetDisbursementItem) {
  deleteError.value = ''
  deleteTargetId.value = item.id
  deleteTargetType.value = 'disbursement'
  showDeleteModal.value = true
}

async function executeDelete() {
  if (!deleteTargetId.value) return
  deleteError.value = ''
  isSubmitting.value = true
  try {
    if (deleteTargetType.value === 'schedule') {
      await deleteBudgetSchedule(deleteTargetId.value)
    } else {
      await deleteBudgetDisbursement(deleteTargetId.value)
    }
    showDeleteModal.value = false
    await loadData()
  } catch (err: any) {
    deleteError.value = err.message || 'Failed to delete record.'
  } finally {
    isSubmitting.value = false
  }
}

function exportCsv() {
  if (activeTab.value === 'schedule') {
    const headers = ['Nr', 'Office', 'PAPS', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Scheduled Releases', 'Allocated Budget', 'Disbursed', 'Remaining Balance']
    const rows = filteredSchedules.value.map((s, idx) => [
      idx + 1,
      s.office_code,
      `"${s.paps.replace(/"/g, '""')}"`,
      s.jan, s.feb, s.mar, s.apr, s.may, s.jun, s.jul, s.aug, s.sep, s.oct, s.nov, s.dec,
      s.schedule_release_count,
      s.allocated_amount,
      s.disbursed_total,
      s.remaining_balance
    ])
    downloadCsvContent('Budget_Schedules_Matrix.csv', [headers, ...rows])
  } else {
    const headers = ['ID', 'Date', 'Office', 'PAPS', 'Particulars', 'Quarter', 'Amount Disbursed', 'Received By', 'Chargeability']
    const rows = filteredDisbursements.value.map(d => [
      d.id,
      d.disbursement_date,
      d.office_code,
      `"${d.paps.replace(/"/g, '""')}"`,
      `"${d.particulars.replace(/"/g, '""')}"`,
      d.quarter,
      d.amount_disbursed,
      `"${(d.received_by || '').replace(/"/g, '""')}"`,
      `"${(d.chargeability || '').replace(/"/g, '""')}"`
    ])
    downloadCsvContent('Budget_Disbursements_Ledger.csv', [headers, ...rows])
  }
}

function downloadCsvContent(filename: string, rows: any[][]) {
  const csvContent = rows.map(e => e.join(',')).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.budget-view-container {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 24px;
  background-color: var(--color-background);
  min-height: 100%;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 16px;
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
  font-family: var(--font-family-primary);
}

.system-badge {
  background-color: var(--color-primary);
  color: var(--color-text-light);
  font-size: 0.72rem;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  margin: 4px 0 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Buttons */
.btn-primary {
  background-color: var(--color-primary-light);
  color: var(--color-text-light);
  border: none;
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.btn-primary:hover {
  background-color: var(--color-primary);
}

.btn-secondary {
  background-color: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.btn-secondary:hover {
  background-color: var(--color-surface-hover);
}

.btn-danger {
  background-color: var(--color-danger);
  color: var(--color-text-light);
  border: none;
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: opacity var(--transition-fast);
}

.btn-danger:hover {
  opacity: 0.9;
}

.btn-icon {
  font-size: 1.1rem;
}

/* Metric Cards */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 16px;
}

.metric-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 16px 20px;
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
}

.metric-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.metric-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.metric-icon-box {
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
}

.info-box {
  background-color: var(--color-info-bg);
  color: var(--color-info);
}

.success-box {
  background-color: var(--color-success-bg);
  color: var(--color-success);
}

.warning-box {
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
}

.neutral-box {
  background-color: var(--color-surface-hover);
  color: var(--color-primary-dark);
}

.metric-value {
  font-size: 1.65rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  font-family: var(--font-family-primary);
  margin-bottom: 4px;
}

.metric-subtext {
  font-size: 0.78rem;
  color: var(--color-text-secondary);
}

.unallocated-notice {
  color: var(--color-warning-text);
  font-weight: 600;
}

/* Tabs */
.tabs-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tabs-nav {
  display: flex;
  gap: 8px;
  border-bottom: 2px solid var(--color-border);
  padding-bottom: 0;
}

.tab-btn {
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 10px 18px;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all var(--transition-fast);
  margin-bottom: -2px;
}

.tab-btn:hover {
  color: var(--color-primary-light);
}

.tab-btn.active {
  color: var(--color-primary-light);
  border-bottom-color: var(--color-primary-light);
}

.tab-icon {
  font-size: 1.1rem;
}

.tab-badge {
  background-color: var(--color-info-bg);
  color: var(--color-info-text);
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: var(--radius-sm);
}

/* Table Card */
.table-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.table-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border);
  flex-wrap: wrap;
  gap: 12px;
}

.table-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.table-card-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-primary-dark);
  margin: 0;
}

.table-item-count {
  background-color: var(--color-surface-hover);
  color: var(--color-text-secondary);
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.table-header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Filters & Search */
.filter-select-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filter-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.filter-select {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 6px 12px;
  font-size: 0.85rem;
  background-color: var(--color-surface);
  color: var(--color-text);
}

.search-input-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  color: var(--color-text-secondary);
  font-size: 1rem;
}

.search-input {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 6px 30px 6px 32px;
  font-size: 0.85rem;
  width: 260px;
  background-color: var(--color-surface);
  color: var(--color-text);
}

.clear-search-btn {
  position: absolute;
  right: 8px;
  background: none;
  border: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  display: flex;
  align-items: center;
}

/* Data Table */
.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.875rem;
}

.data-table th {
  background-color: var(--color-surface-hover);
  color: var(--color-text-secondary);
  font-weight: 700;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 10px 14px;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.data-table td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text);
  vertical-align: middle;
}

.table-row:hover {
  background-color: var(--color-surface-hover);
}

.table-summary-row td {
  background-color: var(--color-surface-hover);
  border-top: 2px solid var(--color-border);
  padding: 12px 14px;
}

.summary-label {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.summary-months-note {
  font-size: 0.78rem;
  color: var(--color-text-secondary);
}

/* Columns */
.col-nr {
  width: 45px;
  color: var(--color-text-secondary);
}

.col-office {
  width: 90px;
}

.office-code-badge {
  font-weight: 800;
  font-size: 0.78rem;
  color: var(--color-primary-dark);
  background-color: var(--color-info-bg);
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-info-border);
}

.col-paps {
  min-width: 220px;
}

.paps-title {
  font-weight: 600;
  color: var(--color-text);
}

.paps-remarks {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin-top: 2px;
}

.col-month {
  width: 48px;
  padding: 8px 4px !important;
}

.month-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 24px;
  border-radius: var(--radius-sm);
  font-size: 0.78rem;
  font-weight: 800;
}

.month-pill-disbursed {
  background-color: var(--color-success-bg);
  color: var(--color-success-text);
  border: 1px solid var(--color-success-border);
}

.month-pill-scheduled {
  background-color: var(--color-info-bg);
  color: var(--color-info-text);
  border: 1px solid var(--color-info-border);
}

.month-dash {
  color: var(--color-text-secondary);
  font-size: 0.8rem;
}

.col-total-releases {
  width: 85px;
}

.total-releases-badge {
  font-weight: 700;
  font-size: 0.85rem;
  background-color: var(--color-surface-hover);
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.col-amount {
  width: 130px;
  white-space: nowrap;
}

.col-particulars {
  min-width: 200px;
}

.particulars-text {
  font-weight: 600;
}

.dr-remarks {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
}

.col-quarter {
  width: 70px;
}

.quarter-badge {
  font-weight: 700;
  font-size: 0.72rem;
  background-color: var(--color-warning-bg);
  color: var(--color-warning-text);
  border: 1px solid var(--color-warning-border);
  padding: 2px 6px;
  border-radius: var(--radius-sm);
}

.col-receiver {
  min-width: 120px;
}

.col-charge {
  min-width: 180px;
}

.charge-badge {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.col-actions {
  width: 80px;
}

.row-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.action-btn {
  background: none;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.edit-btn {
  color: var(--color-primary-light);
}

.edit-btn:hover {
  background-color: var(--color-info-bg);
  border-color: var(--color-info-border);
}

.delete-btn {
  color: var(--color-danger);
}

.delete-btn:hover {
  background-color: var(--color-danger-bg);
  border-color: var(--color-danger-border);
}

/* Modals */
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

.modal-card {
  background-color: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  width: 100%;
  max-width: 580px;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.modal-lg {
  max-width: 720px;
}

.modal-sm {
  max-width: 420px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border);
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
  color: var(--color-text-secondary);
  font-size: 1.25rem;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-top: 1px solid var(--color-border);
  background-color: var(--color-surface-hover);
}

/* Form controls */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-row {
  display: flex;
  gap: 12px;
}

.flex-1 { flex: 1; }
.flex-2 { flex: 2; }

.form-label {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-text);
}

.form-label.required::after {
  content: " *";
  color: var(--color-danger);
}

.form-input, .form-textarea {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 8px 12px;
  font-size: 0.875rem;
  color: var(--color-text);
  background-color: var(--color-surface);
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: var(--color-primary-light);
  box-shadow: 0 0 0 2px var(--color-info-bg);
}

.form-hint {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  margin: 0;
}

/* Month Input Grid */
.month-inputs-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
}

.month-input-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  background-color: var(--color-surface-hover);
  padding: 6px 4px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.month-input-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-text-secondary);
  margin-bottom: 4px;
}

.month-number-input {
  width: 40px;
  text-align: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 4px 0;
  font-size: 0.85rem;
  font-weight: 700;
}

/* Alert Boxes */
.alert-box {
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
}

.alert-danger {
  background-color: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger-text);
}

.delete-msg {
  font-size: 0.9rem;
  color: var(--color-text);
  margin: 0;
}

.delete-warning {
  font-size: 0.8rem;
  color: var(--color-text-secondary);
  margin-top: 8px;
}

/* States */
.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
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

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border);
  border-top-color: var(--color-primary-light);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Utility classes */
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 600; }
.font-mono { font-family: monospace; }
.text-success { color: var(--color-success-text); }
.text-primary { color: var(--color-primary-dark); }
.text-danger { color: var(--color-danger); }
</style>
