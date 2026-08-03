<template>
  <div class="today-table-container">
    <div class="table-header-bar">
      <h3>Today's Accomplishments</h3>
      <span class="date-badge">{{ currentDateFormatted }}</span>
    </div>

    <div v-if="loading" class="loading-state">
      <ion-spinner name="crescent"></ion-spinner>
      <span>Loading today's accomplishments...</span>
    </div>

    <div v-else-if="records.length === 0" class="empty-state">
      <ion-icon :icon="clipboardOutline" class="empty-icon"></ion-icon>
      <p>No accomplishments recorded for today yet.</p>
      <button class="empty-action-btn" type="button" @click="$emit('addFirst')">
        <ion-icon :icon="addOutline"></ion-icon>
        <span>Log Today's First Accomplishment</span>
      </button>
    </div>

    <div v-else class="table-wrapper">
      <table class="compact-table">
        <thead>
          <tr>
            <th class="col-title">Title</th>
            <th class="col-employee">Employee</th>
            <th class="col-office">Office</th>
            <th class="col-category">Category</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in records" :key="item.id" @click="$emit('select', item)" class="table-row">
            <td class="col-title font-medium">{{ item.title }}</td>
            <td class="col-employee">{{ item.assigned_employee_name }}</td>
            <td class="col-office">
              <span class="office-tag">{{ item.office_code || item.office_name }}</span>
            </td>
            <td class="col-category">{{ item.category_name }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { IonSpinner, IonIcon } from '@ionic/vue'
import { clipboardOutline, addOutline } from 'ionicons/icons'
import type { AccomplishmentTodayItem } from '../../types/accomplishment'

defineProps<{
  records: AccomplishmentTodayItem[];
  loading: boolean;
}>()

defineEmits<{
  (e: 'select', item: AccomplishmentTodayItem): void;
  (e: 'addFirst'): void;
}>()

const currentDateFormatted = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
})
</script>

<style scoped>
.today-table-container {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.table-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.table-header-bar h3 {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.date-badge {
  font-size: 12px;
  font-weight: 500;
  color: #4b5563;
  background: #f3f4f6;
  padding: 4px 10px;
  border-radius: 20px;
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  color: #6b7280;
  gap: 12px;
}

.empty-icon {
  font-size: 36px;
  color: #9ca3af;
}

.empty-action-btn {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.empty-action-btn:hover {
  background: #dbeafe;
  border-color: #93c5fd;
}

.table-wrapper {
  width: 100%;
  overflow-x: hidden; /* Strictly no horizontal scrolling */
}

.compact-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed; /* Ensures tight column allocation fitting screen width */
}

.compact-table th {
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
}

.compact-table td {
  padding: 12px;
  font-size: 14px;
  color: #374151;
  border-bottom: 1px solid #f3f4f6;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.table-row {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.table-row:hover {
  background-color: #f9fafb;
}

.font-medium {
  font-weight: 500;
  color: #111827;
}

.office-tag {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  color: #1d4ed8;
  background: #dbeafe;
  padding: 2px 8px;
  border-radius: 4px;
}

/* Width allotments for standard fit */
.col-title { width: 35%; }
.col-employee { width: 25%; }
.col-office { width: 15%; }
.col-category { width: 25%; }
</style>
