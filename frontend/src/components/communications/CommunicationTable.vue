<template>
  <div class="table-card">
    <div class="table-header-bar">
      <h3>Communication Records</h3>
      <span class="record-count" v-if="!loading">{{ records.length }} records</span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-container">
      <p>Loading communications...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="records.length === 0" class="state-container empty-state">
      <div class="empty-icon">
        <ion-icon :icon="documentTextOutline" />
      </div>
      <h4>No Communications Found</h4>
      <p>No communication records match the selected criteria.</p>
      <button class="create-first-btn" type="button" @click="$emit('add-first')">
        Log Communication
      </button>
    </div>

    <!-- Data Table -->
    <div v-else class="table-responsive">
      <table class="comms-table">
        <thead>
          <tr>
            <th v-if="showTypeColumn" class="col-type">Type</th>
            <th class="col-office">Office</th>
            <th class="col-subject">Subject & Category</th>
            <th class="col-date">Date</th>
            <th class="col-status">Status</th>
            <th class="col-age text-center">Age (Days)</th>
            <th class="col-actions text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in records" :key="item.id" @click="$emit('select', item)">
            <!-- Type / Direction Badge -->
            <td v-if="showTypeColumn" class="col-type">
              <span
                class="type-badge"
                :class="item.communication_type === 'Incoming' ? 'badge-incoming' : 'badge-outgoing'"
              >
                {{ item.communication_type }}
              </span>
            </td>

            <!-- Office Abbreviation Only -->
            <td class="col-office">
              <span class="office-abbv-text">{{ item.office_abbv || item.office_code || item.office_name }}</span>
            </td>

            <!-- Subject & Metadata -->
            <td class="col-subject">
              <div class="subject-cell">
                <span class="subject-text">{{ item.subject }}</span>
                <div class="tags-row">
                  <span class="tag-badge category-tag" v-if="item.category_name">
                    {{ item.category_name }}
                  </span>
                  <span class="tag-badge purpose-tag" v-if="item.purpose_name">
                    {{ item.purpose_name }}
                  </span>
                </div>
              </div>
            </td>

            <!-- Date formatted as DD MMM YYYY -->
            <td class="col-date">
              <span class="date-text">{{ formatDate(item.communication_date) }}</span>
            </td>

            <!-- Status Badge -->
            <td class="col-status">
              <span class="status-badge" :class="getStatusClass(item.status)">
                {{ item.status }}
              </span>
            </td>

            <!-- Dynamic Age in Days (Centered count aligned under header) -->
            <td class="col-age text-center">
              <span class="age-count">{{ item.age_days ?? 0 }}</span>
            </td>

            <!-- Actions -->
            <td class="col-actions text-right" @click.stop>
              <div class="action-buttons">
                <button
                  class="action-btn view-btn"
                  title="View Details & Activity Timeline"
                  type="button"
                  @click="$emit('select', item)"
                >
                  <ion-icon :icon="eyeOutline" />
                </button>
                <button
                  class="action-btn edit-btn"
                  title="Edit Record"
                  type="button"
                  @click="$emit('edit', item)"
                >
                  <ion-icon :icon="createOutline" />
                </button>
                <button
                  class="action-btn delete-btn"
                  title="Soft Delete Record"
                  type="button"
                  @click="$emit('delete', item)"
                >
                  <ion-icon :icon="trashOutline" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import {
  documentTextOutline,
  eyeOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'
import type { Communication } from '../../types/communication'

interface Props {
  records: Communication[];
  loading: boolean;
  showTypeColumn?: boolean;
}

withDefaults(defineProps<Props>(), {
  showTypeColumn: true
})

defineEmits<{
  (e: 'select', item: Communication): void;
  (e: 'edit', item: Communication): void;
  (e: 'delete', item: Communication): void;
  (e: 'add-first'): void;
}>()

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr
  const day = date.getDate().toString().padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[date.getMonth()]
  const year = date.getFullYear()
  return `${day} ${month} ${year}`
}

function getStatusClass(status?: string): string {
  if (!status) return 'status-pending'
  const s = status.toLowerCase()
  if (s.includes('completed') || s.includes('released') || s.includes('approved')) return 'status-completed'
  if (s.includes('progress') || s.includes('processing') || s.includes('review')) return 'status-ongoing'
  return 'status-pending'
}
</script>

<style scoped>
.table-card {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.table-header-bar {
  padding: 16px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}

.table-header-bar h3 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.record-count {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

.state-container {
  padding: 48px 24px;
  text-align: center;
  color: #64748b;
}

.empty-state h4 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 12px 0 6px 0;
}

.empty-icon {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  font-size: 28px;
}

.create-first-btn {
  margin-top: 16px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.comms-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 14px;
}

.comms-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}

.comms-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.comms-table tbody tr {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.comms-table tbody tr:hover {
  background-color: #f8fafc;
}

/* Explicit Column Width & Alignments */
.col-type {
  width: 110px;
  text-align: left;
}

.col-office {
  width: 110px;
  text-align: left;
}

.col-subject {
  min-width: 280px;
  text-align: left;
}

.col-date {
  width: 140px;
  text-align: left;
  white-space: nowrap;
}

.col-status {
  width: 130px;
  text-align: left;
}

.col-age {
  width: 110px;
  text-align: center !important;
}

.col-actions {
  width: 130px;
  text-align: right !important;
}

.text-center {
  text-align: center !important;
}

.text-right {
  text-align: right !important;
}

.type-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 12px;
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

.office-abbv-text {
  font-weight: 700;
  color: #0f172a;
  font-size: 14px;
}

.subject-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 480px;
}

.subject-text {
  font-weight: 600;
  color: #1e293b;
  line-height: 1.4;
}

.tags-row {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.tag-badge {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 500;
}

.category-tag {
  background: #f1f5f9;
  color: #475569;
}

.purpose-tag {
  background: #fef3c7;
  color: #92400e;
}

.date-text {
  color: #475569;
  font-weight: 500;
  white-space: nowrap;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-pending {
  background: #fff7ed;
  color: #c2410c;
}

.status-ongoing {
  background: #eff6ff;
  color: #1d4ed8;
}

.status-completed {
  background: #f0fdf4;
  color: #15803d;
}

.age-count {
  font-weight: 700;
  color: #0f172a;
  font-size: 15px;
  display: inline-block;
}

.action-buttons {
  display: inline-flex;
  gap: 6px;
  justify-content: flex-end;
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

.edit-btn:hover {
  background: #f0fdf4;
  color: #16a34a;
  border-color: #86efac;
}

.delete-btn:hover {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fca5a5;
}
</style>
