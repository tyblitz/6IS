<template>
  <MainLayout title="Daily Report" username="Admin">
    <div class="report-page-container">

      <!-- Header & Action Bar -->
      <div class="module-header-bar print-hide">
        <div>
          <h2>Daily Report</h2>
          <p class="subtitle">Daily accomplishment records log and management.</p>
        </div>
        <div class="header-action-group">
          <button class="btn-print" type="button" @click="handlePrint">
            <ion-icon :icon="printOutline"></ion-icon>
            <span>Print Report</span>
          </button>
          <button class="add-btn" type="button" @click="openCreateModal">
            <ion-icon :icon="addOutline"></ion-icon>
            <span>Log Accomplishment</span>
          </button>
        </div>
      </div>

      <!-- Printable Document Header (Visible in print) -->
      <div class="printable-header print-only">
        <div class="print-org-title">6IS INTEGRATED INFORMATION SYSTEM</div>
        <div class="print-report-title">DAILY ACCOMPLISHMENT REPORT</div>
        <div class="print-meta">Date: {{ filterDate }} | Generated: {{ new Date().toLocaleString() }}</div>
      </div>

      <!-- Filter Toolbar -->
      <div class="toolbar-card print-hide">
        <div class="toolbar-grid">
          
          <!-- Date Filter -->
          <div class="filter-item">
            <label>Date</label>
            <input v-model="filterDate" type="date" @change="loadData" />
          </div>

          <!-- Office Filter -->
          <div class="filter-item">
            <label>Office</label>
            <select v-model.number="filterOfficeId" @change="loadData">
              <option :value="0">All Offices</option>
              <option v-for="off in options.offices" :key="off.id" :value="off.id">
                {{ off.office_name }} ({{ off.office_code }})
              </option>
            </select>
          </div>

          <!-- Search Filter -->
          <div class="filter-item search-box">
            <label>Search</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search description or remarks..."
              @keyup.enter="loadData"
            />
          </div>

          <!-- Filter Action Buttons -->
          <div class="filter-actions">
            <button class="btn-filter" type="button" @click="loadData">Apply Filter</button>
            <button class="btn-reset" type="button" @click="resetFilters">Reset</button>
          </div>

        </div>
      </div>

      <!-- Communication Stats Strip -->
      <div class="stats-strip print-hide">
        <div class="stat-pill">
          <span>Incoming Communications:</span>
          <strong>{{ commsStats.incoming }}</strong>
        </div>
        <div class="stat-pill">
          <span>Outgoing Communications:</span>
          <strong>{{ commsStats.outgoing }}</strong>
        </div>
      </div>

      <!-- Records Table -->
      <div class="table-card">
        <div v-if="loading" class="state-container print-hide">
          <ion-spinner name="crescent"></ion-spinner>
          <span>Loading daily report records...</span>
        </div>

        <div v-else-if="records.length === 0" class="state-container empty-box print-hide">
          <ion-icon :icon="clipboardOutline" class="empty-icon"></ion-icon>
          <p>No accomplishment records found for {{ filterDate }}.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="report-table">
            <thead>
              <tr>
                <th style="width: 120px;">Date</th>
                <th style="width: 180px;">Office</th>
                <th>Accomplishment Description</th>
                <th>Remarks</th>
                <th class="text-right print-hide" style="width: 140px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in records" :key="item.id">
                <td class="whitespace-nowrap date-cell">{{ item.date }}</td>
                <td class="whitespace-nowrap">
                  <span class="office-tag">{{ item.office_code || item.office_name }}</span>
                </td>
                <td class="desc-cell">{{ item.description }}</td>
                <td class="remarks-cell">{{ item.remarks || '-' }}</td>
                <td class="text-right actions-cell print-hide">
                  <button class="icon-action-btn view-btn" title="View Details" @click="handleView(item)">
                    <ion-icon :icon="eyeOutline"></ion-icon>
                  </button>
                  <button class="icon-action-btn edit-btn" title="Edit Record" @click="handleEdit(item)">
                    <ion-icon :icon="createOutline"></ion-icon>
                  </button>
                  <button class="icon-action-btn delete-btn" title="Soft Delete" @click="handleDeletePrompt(item)">
                    <ion-icon :icon="trashOutline"></ion-icon>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Form Modal -->
      <AccomplishmentFormModal
        :is-open="isFormOpen"
        :options="options"
        :edit-data="selectedRecord"
        @close="isFormOpen = false"
        @saved="loadData"
      />

      <!-- Details Modal -->
      <AccomplishmentDetailModal
        :is-open="isDetailOpen"
        :data="selectedRecord"
        @close="isDetailOpen = false"
        @edit="handleEditFromDetail"
      />

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IonSpinner, IonIcon, onIonViewWillEnter } from '@ionic/vue'
import {
  addOutline,
  printOutline,
  clipboardOutline,
  eyeOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import AccomplishmentFormModal from '../../components/accomplishments/AccomplishmentFormModal.vue'
import AccomplishmentDetailModal from '../../components/accomplishments/AccomplishmentDetailModal.vue'

import type {
  AccomplishmentItem,
  AccomplishmentOptions
} from '../../types/accomplishment'
import {
  fetchDailyAccomplishments,
  fetchAccomplishmentOptions,
  deleteAccomplishment
} from '../../services/accomplishmentService'

const route = useRoute()

const loading = ref(true)
const records = ref<AccomplishmentItem[]>([])
const commsStats = reactive({ incoming: 0, outgoing: 0 })

const filterDate = ref(new Date().toISOString().split('T')[0])
const filterOfficeId = ref(0)
const searchQuery = ref('')

const isFormOpen = ref(false)
const isDetailOpen = ref(false)
const selectedRecord = ref<AccomplishmentItem | null>(null)

const options = reactive<AccomplishmentOptions>({
  offices: []
})

onMounted(() => {
  loadOptions()
  loadData()
})

onIonViewWillEnter(() => {
  loadOptions()
  loadData()
})

watch(() => route.fullPath, () => {
  loadData()
})

async function loadData() {
  loading.value = true
  const res = await fetchDailyAccomplishments(
    filterDate.value,
    filterOfficeId.value > 0 ? filterOfficeId.value : undefined,
    searchQuery.value
  )
  loading.value = false

  if (res.success && res.data) {
    records.value = res.data.records || []
    commsStats.incoming = res.data.communications_stats?.incoming || 0
    commsStats.outgoing = res.data.communications_stats?.outgoing || 0
  }
}

async function loadOptions() {
  const res = await fetchAccomplishmentOptions()
  if (res.success && res.data) {
    options.offices = res.data.offices || []
  }
}

function resetFilters() {
  filterDate.value = new Date().toISOString().split('T')[0]
  filterOfficeId.value = 0
  searchQuery.value = ''
  loadData()
}

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

function handleView(item: AccomplishmentItem) {
  selectedRecord.value = item
  isDetailOpen.value = true
}

function handleEdit(item: AccomplishmentItem) {
  selectedRecord.value = item
  isFormOpen.value = true
}

function handleEditFromDetail(record: AccomplishmentItem) {
  isDetailOpen.value = false
  selectedRecord.value = record
  isFormOpen.value = true
}

async function handleDeletePrompt(item: AccomplishmentItem) {
  if (confirm(`Are you sure you want to delete this accomplishment for ${item.office_code || item.office_name}?`)) {
    const res = await deleteAccomplishment(item.id)
    if (res.success) {
      loadData()
    } else {
      alert(res.message || 'Failed to delete record.')
    }
  }
}

function handlePrint() {
  window.print()
}
</script>

<style scoped>
.report-page-container {
  padding: 24px;
  max-width: 1280px;
  margin: 0 auto;
}

.module-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.module-header-bar h2 {
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

.header-action-group {
  display: flex;
  gap: 12px;
}

.btn-print {
  background: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: background-color 0.15s ease;
}

.btn-print:hover { background: #f8fafc; }

.add-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.add-btn:hover { background: #1d4ed8; }

.toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.toolbar-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-end;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  min-width: 160px;
}

.filter-item.search-box {
  flex: 2;
  min-width: 220px;
}

label {
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

input, select {
  width: 100%;
  padding: 9px 12px;
  font-size: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
}

.filter-actions {
  display: flex;
  gap: 8px;
}

.btn-filter {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-reset {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 9px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.stats-strip {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}

.stat-pill {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 13px;
  color: #475569;
  display: flex;
  gap: 8px;
}

.stat-pill strong { color: #0f172a; }

.table-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 24px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #64748b;
  gap: 12px;
}

.empty-icon { font-size: 40px; color: #cbd5e1; }

.table-responsive { width: 100%; overflow-x: auto; }

.report-table {
  width: 100%;
  border-collapse: collapse;
}

.report-table th {
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.report-table td {
  padding: 14px 12px;
  font-size: 14px;
  color: #334155;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.date-cell { font-weight: 600; color: #0f172a; }

.office-tag {
  background: #eff6ff;
  color: #2563eb;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.desc-cell { line-height: 1.5; }
.remarks-cell { color: #64748b; font-size: 13px; }

.text-right { text-align: right; }
.actions-cell { white-space: nowrap; }

.icon-action-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  padding: 6px;
  cursor: pointer;
  border-radius: 6px;
  margin-left: 4px;
}

.view-btn { color: #2563eb; }
.edit-btn { color: #10b981; }
.delete-btn { color: #ef4444; }

/* Print Styles */
.print-only { display: none; }

@media print {
  .print-hide { display: none !important; }
  .print-only { display: block !important; }
  .table-card { border: none; padding: 0; box-shadow: none; }
  .report-table th, .report-table td { border-bottom: 1px solid #000; }
  .printable-header {
    text-align: center;
    margin-bottom: 20px;
  }
  .print-org-title { font-size: 16px; font-weight: bold; }
  .print-report-title { font-size: 18px; font-weight: bold; margin-top: 4px; }
  .print-meta { font-size: 12px; color: #555; margin-top: 4px; }
}
</style>
