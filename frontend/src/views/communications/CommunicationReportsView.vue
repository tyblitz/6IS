<template>
  <MainLayout title="Communication Reports" username="Admin">
    <div class="view-container">
      <div class="module-header-bar">
        <div>
          <h2>Communication Reports</h2>
          <p class="subtitle">Summary statistics foundation for organizational communications.</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <p>Loading communication statistics...</p>
      </div>

      <!-- Reports Grid -->
      <div v-else class="reports-grid">
        <!-- Breakdown by Type -->
        <div class="report-card">
          <div class="card-header">
            <h3>Breakdown by Direction / Type</h3>
          </div>
          <table class="report-table">
            <thead>
              <tr>
                <th>Type</th>
                <th class="text-right">Total Communications</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in reportsData?.by_type" :key="item.communication_type">
                <td>
                  <span
                    class="type-badge"
                    :class="item.communication_type === 'Incoming' ? 'badge-incoming' : 'badge-outgoing'"
                  >
                    {{ item.communication_type }}
                  </span>
                </td>
                <td class="text-right font-bold">{{ item.total }}</td>
              </tr>
              <tr v-if="!reportsData?.by_type || reportsData.by_type.length === 0">
                <td colspan="2" class="empty-cell">No records logged</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Breakdown by Status -->
        <div class="report-card">
          <div class="card-header">
            <h3>Breakdown by Processing Status</h3>
          </div>
          <table class="report-table">
            <thead>
              <tr>
                <th>Status</th>
                <th class="text-right">Total Communications</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in reportsData?.by_status" :key="item.status">
                <td>
                  <span class="status-badge" :class="getStatusClass(item.status)">
                    {{ item.status }}
                  </span>
                </td>
                <td class="text-right font-bold">{{ item.total }}</td>
              </tr>
              <tr v-if="!reportsData?.by_status || reportsData.by_status.length === 0">
                <td colspan="2" class="empty-cell">No records logged</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Breakdown by Category -->
        <div class="report-card">
          <div class="card-header">
            <h3>Breakdown by Category</h3>
          </div>
          <table class="report-table">
            <thead>
              <tr>
                <th>Category</th>
                <th class="text-right">Total Communications</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in reportsData?.by_category" :key="item.category_name">
                <td class="font-medium">{{ item.category_name || 'Unassigned' }}</td>
                <td class="text-right font-bold">{{ item.total }}</td>
              </tr>
              <tr v-if="!reportsData?.by_category || reportsData.by_category.length === 0">
                <td colspan="2" class="empty-cell">No records logged</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Breakdown by Purpose -->
        <div class="report-card">
          <div class="card-header">
            <h3>Breakdown by Purpose</h3>
          </div>
          <table class="report-table">
            <thead>
              <tr>
                <th>Purpose</th>
                <th class="text-right">Total Communications</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in reportsData?.by_purpose" :key="item.purpose_name">
                <td class="font-medium">{{ item.purpose_name || 'Unassigned' }}</td>
                <td class="text-right font-bold">{{ item.total }}</td>
              </tr>
              <tr v-if="!reportsData?.by_purpose || reportsData.by_purpose.length === 0">
                <td colspan="2" class="empty-cell">No records logged</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onIonViewWillEnter } from '@ionic/vue'
import MainLayout from '../../layouts/MainLayout.vue'
import type { CommunicationReportsData } from '../../types/communication'
import { fetchCommunicationReports } from '../../services/communicationService'

const loading = ref(true)
const reportsData = ref<CommunicationReportsData | null>(null)

onMounted(() => {
  loadReports()
})

onIonViewWillEnter(() => {
  loadReports()
})

async function loadReports() {
  loading.value = true
  const res = await fetchCommunicationReports()
  loading.value = false
  if (res.success && res.data) {
    reportsData.value = res.data
  }
}

function getStatusClass(status?: string): string {
  if (!status) return 'status-pending'
  const s = status.toLowerCase()
  if (s.includes('completed') || s.includes('released') || s.includes('approved')) return 'status-completed'
  if (s.includes('progress') || s.includes('processing')) return 'status-ongoing'
  return 'status-pending'
}
</script>

<style scoped>
.view-container {
  padding: 24px;
  max-width: 1280px;
  margin: 0 auto;
}

.module-header-bar {
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

.loading-state {
  padding: 48px;
  text-align: center;
  color: #64748b;
}

.reports-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
  gap: 24px;
}

.report-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.card-header {
  padding: 16px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.card-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.report-table th {
  background: #ffffff;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 12px 20px;
  border-bottom: 1px solid #e2e8f0;
}

.report-table td {
  padding: 12px 20px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}

.text-right { text-align: right; }
.font-bold { font-weight: 700; color: #0f172a; }
.font-medium { font-weight: 600; }
.empty-cell { text-align: center; color: #94a3b8; padding: 20px; }

.type-badge {
  padding: 3px 8px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
}

.badge-incoming { background: #eff6ff; color: #2563eb; }
.badge-outgoing { background: #f0fdf4; color: #16a34a; }

.status-badge {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-pending { background: #fff7ed; color: #c2410c; }
.status-ongoing { background: #eff6ff; color: #1d4ed8; }
.status-completed { background: #f0fdf4; color: #15803d; }
</style>
