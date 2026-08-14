<template>
  <div class="today-table-card">
    <div class="table-header">
      <div>
        <h3>Today's Accomplishments</h3>
        <p class="subtitle">Summary of activities for {{ currentDateFormatted }}</p>
      </div>
    </div>

    <!-- Table content -->
    <div v-if="loading" class="state-container">
      <ion-spinner name="crescent"></ion-spinner>
      <span>Loading today's accomplishments...</span>
    </div>

    <div v-else-if="records.length === 0" class="state-container empty-box">
      <ion-icon :icon="clipboardOutline" class="empty-icon"></ion-icon>
      <p>No accomplishments recorded for today yet.</p>
      <button class="btn-first-add" type="button" @click="$emit('add-first')">
        + Add First Activity
      </button>
    </div>

    <div v-else class="table-responsive">
      <table class="overview-table">
        <thead>
          <tr>
            <th>Office</th>
            <th>Description</th>
            <th>Remarks</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in records" :key="item.id">
            <td class="whitespace-nowrap">
              <span class="office-tag">{{ item.office_code || item.office_name }}</span>
            </td>
            <td class="desc-cell">{{ item.description }}</td>
            <td class="remarks-cell">{{ item.remarks || '-' }}</td>
            <td class="text-right actions-cell">
              <button class="btn-view" type="button" @click="$emit('select', item)">
                View
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { IonSpinner, IonIcon } from '@ionic/vue'
import { clipboardOutline } from 'ionicons/icons'
import type { AccomplishmentItem } from '../../types/accomplishment'

defineProps<{
  records: AccomplishmentItem[];
  loading: boolean;
}>()

defineEmits<{
  (e: 'select', item: AccomplishmentItem): void;
  (e: 'add-first'): void;
}>()

import { formatDate } from '../../utils/dateUtils'

const currentDateFormatted = computed(() => {
  const now = new Date()
  const day = String(now.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[now.getMonth()]
  const year = now.getFullYear()
  return `${day} ${month} ${year}`
})
</script>

<style scoped>
.today-table-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
  padding: 24px;
}

.table-header {
  margin-bottom: 20px;
}

.table-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #64748b;
  gap: 12px;
  text-align: center;
}

.empty-icon {
  font-size: 40px;
  color: #cbd5e1;
}

.btn-first-add {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 4px;
}

.btn-first-add:hover { background: #dbeafe; }

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.overview-table {
  width: 100%;
  border-collapse: collapse;
}

.overview-table th {
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.overview-table td {
  padding: 14px 12px;
  font-size: 14px;
  color: #334155;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}

.whitespace-nowrap { white-space: nowrap; }

.date-cell { font-weight: 600; color: #0f172a; }

.office-tag {
  background: #eff6ff;
  color: #2563eb;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.desc-cell {
  line-height: 1.5;
  max-width: 400px;
}

.remarks-cell {
  color: #64748b;
  font-size: 13px;
  max-width: 250px;
}

.text-right { text-align: right; }

.actions-cell {
  white-space: nowrap;
}

.btn-view {
  background: #f1f5f9;
  color: #2563eb;
  border: none;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-view:hover { background: #e2e8f0; }
</style>
