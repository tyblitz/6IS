<template>
  <MainLayout title="Custom Period Summary" username="Admin">
    <div class="report-page-container">

      <!-- Header & Action Buttons -->
      <div class="module-header-bar print-hide">
        <div>
          <h2>Custom Period Accomplishment Summary</h2>
          <p class="subtitle">Breakdown of accomplishments and outgoing communications for the selected date range.</p>
        </div>
        <div class="action-btn-group">
          <button class="btn-print" type="button" @click="handlePrint">
            <ion-icon :icon="printOutline"></ion-icon>
            <span>Print Report</span>
          </button>
        </div>
      </div>

      <!-- Printable Document Header (Visible in print) -->
      <div class="printable-header print-only">
        <div class="print-org-title">6IS INTEGRATED INFORMATION SYSTEM</div>
        <div class="print-report-title">CUSTOM PERIOD ACCOMPLISHMENT & OPERATIONAL REPORT</div>
        <div class="print-meta">Period: {{ formatDate(startDate) }} to {{ formatDate(endDate) }} | Generated: {{ formatDateTime(new Date()) }}</div>
      </div>

      <!-- Toolbar / Selectors (Start Date & End Date ONLY) -->
      <div class="toolbar-card print-hide">
        <div class="toolbar-grid">
          
          <div class="filter-item">
            <label>Start Date</label>
            <input v-model="startDate" type="date" @change="loadData" />
          </div>

          <div class="filter-item">
            <label>End Date</label>
            <input v-model="endDate" type="date" @change="loadData" />
          </div>

        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <ion-spinner name="crescent"></ion-spinner>
        <span>Loading custom period summary statistics...</span>
      </div>

      <!-- 2-COLUMN DASHBOARD LAYOUT -->
      <div v-else class="dashboard-grid-layout">

        <!-- LEFT MAIN COLUMN: Activities & Outgoing Comms -->
        <div class="main-column">

          <!-- GROUP 1: Activities for the Custom Period -->
          <div class="summary-card-group">
            <div class="group-header">
              <div class="header-icon-box accomplishment-bg">
                <ion-icon :icon="checkmarkDoneCircleOutline"></ion-icon>
              </div>
              <div>
                <h3>Activities</h3>
                <p class="group-subtitle">Total activities recorded between {{ formatDate(startDate) }} and {{ formatDate(endDate) }}</p>
              </div>
              <div class="total-badge count-accomplishment">
                <span>Total:</span>
                <strong>{{ totalAccomplishments }}</strong>
              </div>
            </div>

            <div v-if="activeAccomplishmentsByCategory.length === 0" class="empty-category-msg">
              No activities recorded between {{ formatDate(startDate) }} and {{ formatDate(endDate) }}.
            </div>

            <div v-else class="compact-category-grid">
              <div 
                v-for="item in activeAccomplishmentsByCategory" 
                :key="item.category_id" 
                class="category-stat-card accomplishment-card"
              >
                <div class="cat-card-top">
                  <h4 class="category-title-code">{{ item.category_code || item.category_name }}</h4>
                  <span class="cat-count-badge">{{ item.count }}</span>
                </div>
                <div class="cat-card-body">
                  <div class="cat-progress-bar">
                    <div 
                      class="progress-fill fill-acc" 
                      :style="{ width: calculatePercentage(item.count, totalAccomplishments) + '%' }"
                    ></div>
                  </div>
                  <div class="cat-footer">
                    <span class="percentage-text">{{ calculatePercentage(item.count, totalAccomplishments) }}% of custom period activities</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- GROUP 2: Outgoing Communications for the Custom Period -->
          <div class="summary-card-group">
            <div class="group-header">
              <div class="header-icon-box outgoing-bg">
                <ion-icon :icon="paperPlaneOutline"></ion-icon>
              </div>
              <div>
                <h3>Outgoing Communications</h3>
                <p class="group-subtitle">Total outgoing communications between {{ formatDate(startDate) }} and {{ formatDate(endDate) }}</p>
              </div>
              <div class="total-badge count-outgoing">
                <span>Total Outgoing:</span>
                <strong>{{ totalOutgoingComms }}</strong>
              </div>
            </div>

            <div v-if="activeOutgoingCommsByCategory.length === 0" class="empty-category-msg">
              No outgoing communications recorded between {{ formatDate(startDate) }} and {{ formatDate(endDate) }}.
            </div>

            <div v-else class="compact-category-grid">
              <div 
                v-for="item in activeOutgoingCommsByCategory" 
                :key="item.category_id" 
                class="category-stat-card outgoing-card"
              >
                <div class="cat-card-top">
                  <h4 class="category-title-code tag-outgoing-text">{{ item.category_code || item.category_name }}</h4>
                  <span class="cat-count-badge count-bg-outgoing">{{ item.count }}</span>
                </div>
                <div class="cat-card-body">
                  <div class="cat-progress-bar">
                    <div 
                      class="progress-fill fill-outgoing" 
                      :style="{ width: calculatePercentage(item.count, totalOutgoingComms) + '%' }"
                    ></div>
                  </div>
                  <div class="cat-footer">
                    <span class="percentage-text">{{ calculatePercentage(item.count, totalOutgoingComms) }}% of custom period outgoing comms</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT SIDEBAR COLUMN: Clearances -->
        <div class="sidebar-column">

          <!-- GROUP 3: Clearances (Access Pass Outgoing Comms) -->
          <div class="summary-card-group sidebar-group">
            <div class="group-header">
              <div class="header-icon-box clearance-bg">
                <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
              </div>
              <div>
                <h3>Clearances</h3>
                <p class="group-subtitle">Access Pass count between {{ formatDate(startDate) }} and {{ formatDate(endDate) }}</p>
              </div>
            </div>

            <div v-if="activeClearancesByPurpose.length === 0" class="empty-category-msg">
              No Access Pass clearances recorded between {{ formatDate(startDate) }} and {{ formatDate(endDate) }}.
            </div>

            <div v-else class="clearance-single-card">
              <div class="clearance-hero-box">
                <div class="clearance-number">{{ totalClearances }}</div>
                <div class="clearance-label">Released Access Pass Clearances</div>
                <div class="clearance-subtext">{{ formatDate(startDate) }} to {{ formatDate(endDate) }}</div>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IonSpinner, IonIcon, onIonViewWillEnter } from '@ionic/vue'
import { 
  checkmarkDoneCircleOutline, 
  paperPlaneOutline,
  shieldCheckmarkOutline,
  printOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { formatDate, formatDateTime } from '../../utils/dateUtils'
import type { 
  AccomplishmentCategorySummary, 
  OutgoingCommCategorySummary,
  ClearancePurposeSummary 
} from '../../types/accomplishment'
import { fetchCustomPeriodAccomplishments } from '../../services/accomplishmentService'

const route = useRoute()

const loading = ref(true)
const accomplishmentsByCategory = ref<AccomplishmentCategorySummary[]>([])
const outgoingCommsByCategory = ref<OutgoingCommCategorySummary[]>([])
const clearancesByPurpose = ref<ClearancePurposeSummary[]>([])

// Default to current month start and today
const now = new Date()
const firstDayOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
const todayStr = now.toISOString().split('T')[0]

const startDate = ref(firstDayOfMonth)
const endDate = ref(todayStr)

const totalAccomplishments = computed(() => {
  return accomplishmentsByCategory.value.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
})

const totalOutgoingComms = computed(() => {
  return outgoingCommsByCategory.value.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
})

const totalClearances = computed(() => {
  return clearancesByPurpose.value.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
})

const activeAccomplishmentsByCategory = computed(() => {
  return accomplishmentsByCategory.value.filter(item => (Number(item.count) || 0) > 0)
})

const activeOutgoingCommsByCategory = computed(() => {
  return outgoingCommsByCategory.value.filter(item => (Number(item.count) || 0) > 0)
})

const activeClearancesByPurpose = computed(() => {
  return clearancesByPurpose.value.filter(item => (Number(item.count) || 0) > 0)
})

onMounted(() => {
  loadData()
})

onIonViewWillEnter(() => {
  loadData()
})

watch(() => route.fullPath, () => {
  loadData()
})

async function loadData() {
  if (!startDate.value || !endDate.value) return

  loading.value = true
  const res = await fetchCustomPeriodAccomplishments(
    startDate.value,
    endDate.value
  )
  loading.value = false

  if (res.success && res.data) {
    accomplishmentsByCategory.value = res.data.accomplishments_by_category || []
    outgoingCommsByCategory.value = res.data.outgoing_comms_by_category || []
    clearancesByPurpose.value = res.data.clearances_by_purpose || []
  }
}

function calculatePercentage(count: number, total: number): number {
  if (!total || total === 0) return 0
  return Math.round(((Number(count) || 0) / total) * 100)
}

function handlePrint() {
  window.print()
}
</script>

<style scoped>
.report-page-container {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.module-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
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

.action-btn-group {
  display: flex;
  gap: 10px;
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
  transition: all 0.15s ease;
}

.btn-print:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.toolbar-grid {
  display: flex;
  gap: 20px;
  align-items: flex-end;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
}

label {
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

input[type="date"] {
  width: 100%;
  padding: 9px 12px;
  font-size: 14px;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px;
  color: #64748b;
  gap: 12px;
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
}

/* 2-COLUMN DASHBOARD GRID */
.dashboard-grid-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  align-items: start;
}

@media (max-width: 1024px) {
  .dashboard-grid-layout {
    grid-template-columns: 1fr;
  }
}

.main-column, .sidebar-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.summary-card-group {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
}

.group-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 14px;
}

.header-icon-box {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.accomplishment-bg {
  background: #eff6ff;
  color: #2563eb;
}

.outgoing-bg {
  background: #f0fdf4;
  color: #16a34a;
}

.clearance-bg {
  background: #faf5ff;
  color: #9333ea;
}

.group-header h3 {
  font-size: 17px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 2px 0;
}

.group-subtitle {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

.total-badge {
  margin-left: auto;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.count-accomplishment {
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

.count-outgoing {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.empty-category-msg {
  padding: 20px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
}

.compact-category-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}

.category-stat-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  transition: all 0.2s ease;
  background: #ffffff;
}

.category-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}

.accomplishment-card {
  border-left: 4px solid #2563eb;
}

.outgoing-card {
  border-left: 4px solid #16a34a;
}

.cat-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.category-title-code {
  font-size: 15px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  letter-spacing: 0.02em;
}

.tag-outgoing-text {
  color: #166534;
}

.cat-count-badge {
  background: #2563eb;
  color: #ffffff;
  font-size: 15px;
  font-weight: 800;
  padding: 1px 9px;
  border-radius: 14px;
}

.count-bg-outgoing {
  background: #16a34a;
}

.cat-progress-bar {
  width: 100%;
  height: 5px;
  background: #f1f5f9;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 6px;
}

.progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s ease;
}

.fill-acc {
  background: #2563eb;
}

.fill-outgoing {
  background: #16a34a;
}

.cat-footer {
  display: flex;
  justify-content: flex-end;
}

.percentage-text {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

/* SIDEBAR HERO BOX */
.clearance-hero-box {
  background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
  border: 1px solid #e9d5ff;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.clearance-number {
  font-size: 42px;
  font-weight: 900;
  color: #7e22ce;
  line-height: 1;
  margin-bottom: 6px;
}

.clearance-label {
  font-size: 14px;
  font-weight: 800;
  color: #581c87;
  margin-bottom: 4px;
}

.clearance-subtext {
  font-size: 12px;
  color: #7e22ce;
  font-weight: 600;
}

.print-hide {
  display: block;
}

.print-only {
  display: none;
}

@media print {
  .print-hide { display: none !important; }
  .print-only { display: block !important; }

  .report-page-container {
    padding: 0;
    max-width: 100%;
  }

  .printable-header {
    margin-bottom: 20px;
    text-align: center;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
  }

  .print-org-title { font-size: 14pt; font-weight: bold; }
  .print-report-title { font-size: 16pt; font-weight: bold; margin-top: 4px; }
  .print-meta { font-size: 10pt; color: #555; margin-top: 4px; }

  .dashboard-grid-layout {
    display: block !important;
  }

  .main-column, .sidebar-column {
    display: block !important;
    margin-bottom: 20px;
  }

  .summary-card-group {
    border: 1px solid #000 !important;
    box-shadow: none !important;
    break-inside: avoid;
    margin-bottom: 20px;
  }
}
</style>
