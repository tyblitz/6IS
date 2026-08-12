<template>
  <MainLayout title="Annual Summary" username="Admin">
    <div class="report-page-container">

      <!-- Header -->
      <div class="module-header-bar print-hide">
        <div>
          <h2>Annual Accomplishment Summary</h2>
          <p class="subtitle">Categorized breakdown of accomplishments and outgoing communications for the year.</p>
        </div>
      </div>

      <!-- Toolbar / Selectors (Year ONLY) -->
      <div class="toolbar-card print-hide">
        <div class="toolbar-grid">
          
          <div class="filter-item">
            <label>Year</label>
            <select v-model.number="selectedYear" @change="loadData">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <ion-spinner name="crescent"></ion-spinner>
        <span>Loading annual summary statistics...</span>
      </div>

      <!-- 2-COLUMN DASHBOARD LAYOUT -->
      <div v-else class="dashboard-grid-layout">

        <!-- LEFT MAIN COLUMN: Activities & Outgoing Comms -->
        <div class="main-column">

          <!-- GROUP 1: Activities for the Year -->
          <div class="summary-card-group">
            <div class="group-header">
              <div class="header-icon-box accomplishment-bg">
                <ion-icon :icon="checkmarkDoneCircleOutline"></ion-icon>
              </div>
              <div>
                <h3>Activities</h3>
                <p class="group-subtitle">Total activities recorded in {{ selectedYear }}</p>
              </div>
              <div class="total-badge count-accomplishment">
                <span>Total:</span>
                <strong>{{ totalAccomplishments }}</strong>
              </div>
            </div>

            <div v-if="activeAccomplishmentsByCategory.length === 0" class="empty-category-msg">
              No activities recorded for year {{ selectedYear }}.
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
                    <span class="percentage-text">{{ calculatePercentage(item.count, totalAccomplishments) }}% of annual activities</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- GROUP 2: Outgoing Communications for the Year -->
          <div class="summary-card-group">
            <div class="group-header">
              <div class="header-icon-box outgoing-bg">
                <ion-icon :icon="paperPlaneOutline"></ion-icon>
              </div>
              <div>
                <h3>Outgoing Communications</h3>
                <p class="group-subtitle">Total outgoing communications in {{ selectedYear }}</p>
              </div>
              <div class="total-badge count-outgoing">
                <span>Total Outgoing:</span>
                <strong>{{ totalOutgoingComms }}</strong>
              </div>
            </div>

            <div v-if="activeOutgoingCommsByCategory.length === 0" class="empty-category-msg">
              No outgoing communications recorded for year {{ selectedYear }}.
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
                    <span class="percentage-text">{{ calculatePercentage(item.count, totalOutgoingComms) }}% of annual outgoing comms</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT SIDEBAR COLUMN: Clearances & Executive Quick Stats -->
        <div class="sidebar-column">

          <!-- GROUP 3: Clearances (Access Pass Outgoing Comms) -->
          <div class="summary-card-group sidebar-group">
            <div class="group-header">
              <div class="header-icon-box clearance-bg">
                <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
              </div>
              <div>
                <h3>Clearances</h3>
                <p class="group-subtitle">Access Pass count in {{ selectedYear }}</p>
              </div>
            </div>

            <div v-if="activeClearancesByPurpose.length === 0" class="empty-category-msg">
              No Access Pass clearances recorded for year {{ selectedYear }}.
            </div>

            <div v-else class="clearance-single-card">
              <div class="clearance-hero-box">
                <div class="clearance-number">{{ totalClearances }}</div>
                <div class="clearance-label">Released Access Pass Clearances</div>
                <div class="clearance-subtext">{{ selectedYear }}</div>
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
  shieldCheckmarkOutline 
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { 
  AccomplishmentCategorySummary, 
  OutgoingCommCategorySummary,
  ClearancePurposeSummary 
} from '../../types/accomplishment'
import { fetchAnnualAccomplishments } from '../../services/accomplishmentService'

const route = useRoute()

const loading = ref(true)
const accomplishmentsByCategory = ref<AccomplishmentCategorySummary[]>([])
const outgoingCommsByCategory = ref<OutgoingCommCategorySummary[]>([])
const clearancesByPurpose = ref<ClearancePurposeSummary[]>([])

const selectedYear = ref(new Date().getFullYear())

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  return [current, current - 1, current - 2, current - 3]
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
  const res = await fetchAnnualAccomplishments(
    selectedYear.value
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
  min-width: 200px;
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

/* SIDEBAR HERO BOX & QUICK STATS */
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

.stats-widget-group {
  background: #ffffff;
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
}

.widget-header h4 {
  font-size: 15px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.widget-period-tag {
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.widget-metrics-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.widget-metric-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #f1f5f9;
}

.metric-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.metric-name {
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
}

.metric-sub {
  font-size: 11px;
  color: #64748b;
}

.metric-val {
  font-size: 18px;
  font-weight: 800;
}

.text-blue { color: #2563eb; }
.text-indigo { color: #4f46e5; }
.text-emerald { color: #059669; }

.print-hide {
  display: block;
}
</style>
