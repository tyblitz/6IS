<template>
  <MainLayout title="Communications Overview" username="Admin">
    <div class="view-container">
      <!-- Page Header -->
      <div class="module-header-bar">
        <div>
          <h2>Communications Overview</h2>
          <p class="subtitle">Summary of communications for the current month and today's activity.</p>
        </div>
        <button class="add-btn" type="button" @click="openCreateModal()">
          <ion-icon :icon="addOutline" />
          <span>Log Communication</span>
        </button>
      </div>

      <!-- Current Month Summary Cards -->
      <div class="summary-cards-grid">
        <div class="summary-card card-incoming">
          <div class="card-icon">
            <ion-icon :icon="arrowDownCircleOutline" />
          </div>
          <div class="card-content">
            <span class="card-label">Incoming (This Month)</span>
            <span class="card-value">{{ monthlySummary.incoming }}</span>
          </div>
        </div>

        <div class="summary-card card-outgoing">
          <div class="card-icon">
            <ion-icon :icon="arrowUpCircleOutline" />
          </div>
          <div class="card-content">
            <span class="card-label">Outgoing (This Month)</span>
            <span class="card-value">{{ monthlySummary.outgoing }}</span>
          </div>
        </div>

        <div class="summary-card card-total">
          <div class="card-icon">
            <ion-icon :icon="documentTextOutline" />
          </div>
          <div class="card-content">
            <span class="card-label">Total (This Month)</span>
            <span class="card-value">{{ monthlySummary.total }}</span>
          </div>
        </div>
      </div>

      <!-- Separate Today's Tables Grid (Incoming vs Outgoing) -->
      <div class="todays-tables-grid">
        <!-- Today's Incoming Communications Table -->
        <div class="todays-card card-incoming-theme">
          <div class="card-header">
            <div class="header-title">
              <span class="type-badge badge-incoming">Incoming</span>
              <h3>Today's Incoming Communications</h3>
            </div>
            <span class="count-badge" v-if="!loading">{{ todaysIncoming.length }} records</span>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="state-container">
            <p>Loading incoming communications...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="todaysIncoming.length === 0" class="state-container empty-state">
            <p>No incoming communications logged today.</p>
          </div>

          <!-- Today's Incoming Subject Table -->
          <div v-else class="todays-table-container">
            <table class="todays-table">
              <thead>
                <tr>
                  <th class="col-subject">Subject</th>
                  <th class="col-actions text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in todaysIncoming" :key="item.id" @click="handleSelect(item)">
                  <td class="col-subject">
                    <span class="subject-title">{{ item.subject }}</span>
                  </td>
                  <td class="col-actions text-right" @click.stop>
                    <button
                      class="action-btn view-btn"
                      title="View Communication Details"
                      type="button"
                      @click="handleSelect(item)"
                    >
                      <ion-icon :icon="eyeOutline" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Today's Outgoing Communications Table -->
        <div class="todays-card card-outgoing-theme">
          <div class="card-header">
            <div class="header-title">
              <span class="type-badge badge-outgoing">Outgoing</span>
              <h3>Today's Outgoing Communications</h3>
            </div>
            <span class="count-badge" v-if="!loading">{{ todaysOutgoing.length }} records</span>
          </div>

          <!-- Loading State -->
          <div v-if="loading" class="state-container">
            <p>Loading outgoing communications...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="todaysOutgoing.length === 0" class="state-container empty-state">
            <p>No outgoing communications logged today.</p>
          </div>

          <!-- Today's Outgoing Subject Table -->
          <div v-else class="todays-table-container">
            <table class="todays-table">
              <thead>
                <tr>
                  <th class="col-subject">Subject</th>
                  <th class="col-actions text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in todaysOutgoing" :key="item.id" @click="handleSelect(item)">
                  <td class="col-subject">
                    <span class="subject-title">{{ item.subject }}</span>
                  </td>
                  <td class="col-actions text-right" @click.stop>
                    <button
                      class="action-btn view-btn"
                      title="View Communication Details"
                      type="button"
                      @click="handleSelect(item)"
                    >
                      <ion-icon :icon="eyeOutline" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Form Modal -->
      <CommunicationFormModal
        :is-open="isFormOpen"
        :options="options"
        :edit-data="selectedRecord"
        @close="isFormOpen = false"
        @saved="loadOverview"
      />

      <!-- Detail Modal -->
      <CommunicationDetailModal
        :is-open="isDetailOpen"
        :data="detailRecord"
        @close="isDetailOpen = false"
        @edit="openEditFromDetail"
        @refresh="handleDetailRefresh"
      />
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { IonIcon, onIonViewWillEnter } from '@ionic/vue'
import {
  addOutline,
  arrowDownCircleOutline,
  arrowUpCircleOutline,
  documentTextOutline,
  eyeOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import CommunicationFormModal from '../../components/communications/CommunicationFormModal.vue'
import CommunicationDetailModal from '../../components/communications/CommunicationDetailModal.vue'

import type {
  Communication,
  CommunicationOptions
} from '../../types/communication'
import {
  fetchCommunicationOverview,
  fetchCommunicationOptions,
  fetchCommunicationById
} from '../../services/communicationService'

const loading = ref(true)
const todaysIncoming = ref<Communication[]>([])
const todaysOutgoing = ref<Communication[]>([])

const monthlySummary = reactive({
  incoming: 0,
  outgoing: 0,
  total: 0
})

const isFormOpen = ref(false)
const isDetailOpen = ref(false)

const selectedRecord = ref<Communication | null>(null)
const detailRecord = ref<Communication | null>(null)

const options = reactive<CommunicationOptions>({
  categories: [],
  purposes: [],
  offices: []
})

onMounted(() => {
  Promise.all([loadOptions(), loadOverview()])
})

onIonViewWillEnter(() => {
  Promise.all([loadOptions(), loadOverview()])
})

async function loadOptions() {
  const res = await fetchCommunicationOptions()
  if (res.success && res.data) {
    options.categories = res.data.categories
    options.purposes = res.data.purposes
    options.offices = res.data.offices
  }
}

async function loadOverview() {
  loading.value = true
  const res = await fetchCommunicationOverview()
  if (res.success && res.data) {
    monthlySummary.incoming = res.data.monthly_summary.incoming
    monthlySummary.outgoing = res.data.monthly_summary.outgoing
    monthlySummary.total = res.data.monthly_summary.total
    todaysIncoming.value = res.data.todays_incoming || []
    todaysOutgoing.value = res.data.todays_outgoing || []
  }
  loading.value = false
}

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

async function handleSelect(item: Communication) {
  const res = await fetchCommunicationById(item.id)
  if (res.success && res.data) {
    detailRecord.value = res.data
  } else {
    detailRecord.value = item
  }
  isDetailOpen.value = true
}

function openEditFromDetail(item: Communication) {
  isDetailOpen.value = false
  selectedRecord.value = item
  isFormOpen.value = true
}

async function handleDetailRefresh() {
  if (detailRecord.value?.id) {
    const res = await fetchCommunicationById(detailRecord.value.id)
    if (res.success && res.data) {
      detailRecord.value = res.data
    }
  }
  loadOverview()
}
</script>

<style scoped>
.view-container {
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
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.add-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: background-color 0.15s ease;
}

.add-btn:hover {
  background: #1d4ed8;
}

/* Monthly Summary Cards Grid */
.summary-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 20px;
  margin-bottom: 28px;
}

.summary-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 20px 24px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 16px;
}

.card-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.card-incoming .card-icon {
  background: #eff6ff;
  color: #2563eb;
}

.card-outgoing .card-icon {
  background: #f0fdf4;
  color: #16a34a;
}

.card-total .card-icon {
  background: #f1f5f9;
  color: #0f2d5c;
}

.card-content {
  display: flex;
  flex-direction: column;
}

.card-label {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
  margin-bottom: 4px;
}

.card-value {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1;
}

/* Separate Today's Tables Grid */
.todays-tables-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.todays-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.count-badge {
  font-size: 12px;
  background: #e2e8f0;
  color: #334155;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 20px;
}

.state-container {
  padding: 36px 20px;
  text-align: center;
  color: #64748b;
}

.empty-state p {
  margin: 0;
  font-size: 14px;
  color: #94a3b8;
}

.todays-table-container {
  width: 100%;
  overflow-x: auto;
}

.todays-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 14px;
}

.todays-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
}

.todays-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.todays-table tbody tr {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.todays-table tbody tr:hover {
  background-color: #f8fafc;
}

.col-subject {
  width: auto;
}

.col-actions {
  width: 80px;
  text-align: right !important;
}

.subject-title {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.4;
}

.type-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
}

.badge-incoming {
  background: #eff6ff;
  color: #2563eb;
}

.badge-outgoing {
  background: #f0fdf4;
  color: #16a34a;
}

.text-right {
  text-align: right;
}

.action-btn {
  background: none;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #64748b;
  transition: all 0.15s ease;
}

.view-btn:hover {
  background: #eff6ff;
  color: #2563eb;
  border-color: #93c5fd;
}

@media (max-width: 1024px) {
  .todays-tables-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
}

@media (max-width: 640px) {
  .view-container {
    padding: 16px;
  }
  .module-header-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .add-btn {
    width: 100%;
    justify-content: center;
  }
  .summary-cards-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}
</style>