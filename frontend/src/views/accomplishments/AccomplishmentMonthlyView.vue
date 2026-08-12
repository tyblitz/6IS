<template>
  <MainLayout title="Monthly Summary" username="Admin">
    <div class="report-page-container">

      <!-- Header & Action Buttons (Disabled as requested) -->
      <div class="module-header-bar print-hide">
        <div>
          <h2>Monthly Accomplishment Summary</h2>
          <p class="subtitle">Categorized breakdown of accomplishments and outgoing communications for the month.</p>
        </div>
        <div class="action-btn-group">
          <button class="btn-disabled" type="button" disabled title="Export functionality disabled for now">
            <ion-icon :icon="documentTextOutline"></ion-icon>
            <span>Export DOCX (Disabled)</span>
          </button>
          <button class="btn-disabled" type="button" disabled title="Print functionality disabled for now">
            <ion-icon :icon="printOutline"></ion-icon>
            <span>Print Report (Disabled)</span>
          </button>
        </div>
      </div>

      <!-- Toolbar / Selectors (Year & Month ONLY) -->
      <div class="toolbar-card print-hide">
        <div class="toolbar-grid">
          
          <div class="filter-item">
            <label>Year</label>
            <select v-model.number="selectedYear" @change="loadData">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>

          <div class="filter-item">
            <label>Month</label>
            <select v-model.number="selectedMonth" @change="loadData">
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <ion-spinner name="crescent"></ion-spinner>
        <span>Loading monthly summary statistics...</span>
      </div>

      <div v-else class="summary-groups-wrapper">

        <!-- GROUP 1: Activities for the Month -->
        <div class="summary-card-group">
          <div class="group-header">
            <div class="header-icon-box accomplishment-bg">
              <ion-icon :icon="checkmarkDoneCircleOutline"></ion-icon>
            </div>
            <div>
              <h3>Activities</h3>
              <p class="group-subtitle">Total activities recorded in {{ selectedMonthName }} {{ selectedYear }}</p>
            </div>
            <div class="total-badge count-accomplishment">
              <span>Total:</span>
              <strong>{{ totalAccomplishments }}</strong>
            </div>
          </div>

          <div v-if="activeAccomplishmentsByCategory.length === 0" class="empty-category-msg">
            No activities recorded for {{ selectedMonthName }} {{ selectedYear }}.
          </div>

          <div v-else class="category-grid">
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
                  <span class="percentage-text">{{ calculatePercentage(item.count, totalAccomplishments) }}% of monthly activities</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- GROUP 2: Outgoing Communications for the Month -->
        <div class="summary-card-group">
          <div class="group-header">
            <div class="header-icon-box outgoing-bg">
              <ion-icon :icon="paperPlaneOutline"></ion-icon>
            </div>
            <div>
              <h3>Outgoing Communications</h3>
              <p class="group-subtitle">Total outgoing communications in {{ selectedMonthName }} {{ selectedYear }}</p>
            </div>
            <div class="total-badge count-outgoing">
              <span>Total Outgoing:</span>
              <strong>{{ totalOutgoingComms }}</strong>
            </div>
          </div>

          <div v-if="activeOutgoingCommsByCategory.length === 0" class="empty-category-msg">
            No outgoing communications recorded for {{ selectedMonthName }} {{ selectedYear }}.
          </div>

          <div v-else class="category-grid">
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
                  <span class="percentage-text">{{ calculatePercentage(item.count, totalOutgoingComms) }}% of monthly outgoing comms</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- GROUP 3: Clearances (Special Group - Access Pass Outgoing Comms) -->
        <div class="summary-card-group">
          <div class="group-header">
            <div class="header-icon-box clearance-bg">
              <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
            </div>
            <div>
              <h3>Clearances</h3>
              <p class="group-subtitle">Released Access Pass Clearance in {{ selectedMonthName }} {{ selectedYear }}</p>
            </div>
            <div class="total-badge count-clearance">
              <span>Total Clearances:</span>
              <strong>{{ totalClearances }}</strong>
            </div>
          </div>

          <div v-if="activeClearancesByPurpose.length === 0" class="empty-category-msg">
            No Access Pass clearances recorded for {{ selectedMonthName }} {{ selectedYear }}.
          </div>

          <div v-else class="category-grid">
            <div 
              v-for="item in activeClearancesByPurpose" 
              :key="item.purpose_id" 
              class="category-stat-card clearance-card"
            >
              <div class="cat-card-top">
                <h4 class="category-title-code tag-clearance-text">{{ item.purpose_name }}</h4>
                <span class="cat-count-badge count-bg-clearance">{{ item.count }}</span>
              </div>
              <div class="cat-card-body">
                <div class="cat-progress-bar">
                  <div 
                    class="progress-fill fill-clearance" 
                    :style="{ width: '100%' }"
                  ></div>
                </div>
                <div class="cat-footer">
                  <span class="percentage-text">Released Access Pass Count</span>
                </div>
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
  printOutline, 
  documentTextOutline, 
  checkmarkDoneCircleOutline, 
  paperPlaneOutline,
  shieldCheckmarkOutline 
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { 
  AccomplishmentCategorySummary, 
  OutgoingCommCategorySummary,
  ClearancePurposeSummary 
} from '../../types/accomplishment'
import { fetchMonthlyAccomplishments } from '../../services/accomplishmentService'

const route = useRoute()

const loading = ref(true)
const accomplishmentsByCategory = ref<AccomplishmentCategorySummary[]>([])
const outgoingCommsByCategory = ref<OutgoingCommCategorySummary[]>([])
const clearancesByPurpose = ref<ClearancePurposeSummary[]>([])

const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  return [current, current - 1, current - 2]
})

const monthOptions = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' }
]

const selectedMonthName = computed(() => {
  const item = monthOptions.find(m => m.value === selectedMonth.value)
  return item ? item.label : 'August'
})

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
  loading.value = true
  const res = await fetchMonthlyAccomplishments(
    selectedYear.value,
    selectedMonth.value
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

.action-btn-group {
  display: flex;
  gap: 10px;
}

.btn-disabled {
  background: #f1f5f9;
  color: #94a3b8;
  border: 1px solid #cbd5e1;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: not-allowed;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  opacity: 0.7;
}

.toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 20px;
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

select {
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

.summary-groups-wrapper {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.summary-card-group {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
}

.group-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.header-icon-box {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.accomplishment-bg {
  background: #eff6ff;
  color: #2563eb;
}

.outgoing-bg {
  background: #f0fdf4;
  color: #16a34a;
}

.group-header h3 {
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 2px 0;
}

.group-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.total-badge {
  margin-left: auto;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-accomplishment {
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

.empty-category-msg {
  padding: 24px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
}

.category-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.category-stat-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
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
  margin-bottom: 10px;
}

.category-title-code {
  font-size: 16px;
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
  font-size: 16px;
  font-weight: 800;
  padding: 2px 10px;
  border-radius: 20px;
}

.count-bg-outgoing {
  background: #16a34a;
}

.category-title {
  font-size: 14px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 12px 0;
  min-height: 38px;
  display: flex;
  align-items: center;
}

.cat-progress-bar {
  width: 100%;
  height: 6px;
  background: #f1f5f9;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 8px;
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

.clearance-bg {
  background: #faf5ff;
  color: #9333ea;
}

.count-clearance {
  background: #faf5ff;
  color: #6b21a8;
  border: 1px solid #e9d5ff;
}

.clearance-card {
  border-left: 4px solid #9333ea;
}

.tag-clearance-text {
  color: #6b21a8;
}

.count-bg-clearance {
  background: #9333ea;
}

.fill-clearance {
  background: #9333ea;
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

.print-hide {
  display: block;
}
</style>
