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
            <th>Type</th>
            <th>Office</th>
            <th>Subject & Category</th>
            <th>Date</th>
            <th>Status</th>
            <th>Age (Days)</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in records" :key="item.id" @click="$emit('select', item)">
            <!-- Type / Direction Badge -->
            <td>
              <span
                class="type-badge"
                :class="item.communication_type === 'Incoming' ? 'badge-incoming' : 'badge-outgoing'"
              >
                {{ item.communication_type }}
              </span>
            </td>

            <!-- Office -->
            <td>
              <div class="office-cell">
                <span class="office-name">{{ item.office_name }}</span>
                <span class="office-code" v-if="item.office_abbv || item.office_code">
                  ({{ item.office_abbv || item.office_code }})
                </span>
              </div>
            </td>

            <!-- Subject & Metadata -->
            <td>
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

            <!-- Date -->
            <td>
              <span class="date-text">{{ formatDate(item.communication_date) }}</span>
            </td>

            <!-- Status Badge -->
            <td>
              <span class="status-badge" :class="getStatusClass(item.status)">
                {{ item.status }}
              </span>
            </td>

            <!-- Dynamic Age in Days -->
            <td>
              <div class="age-cell">
                <span class="age-count">{{ item.age_days ?? 0 }}</span>
                <span class="age-label">days</span>
              </div>
            </td>

            <!-- Actions -->
            <td class="text-right" @click.stop>
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

defineProps<{
  records: Communication[];
  loading: boolean;
}>()

defineEmits<{
  (e: 'select', item: Communication): void;
  (e: 'edit', item: Communication): void;
  (e: 'delete', item: Communication): void;
  (e: 'add-first'): void;
}>()

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
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
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
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

.office-cell {
  display: flex;
  flex-direction: column;
}

.office-name {
  font-weight: 600;
  color: #0f172a;
}

.office-code {
  font-size: 12px;
  color: #64748b;
}

.subject-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 360px;
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

.age-cell {
  display: inline-flex;
  align-items: baseline;
  gap: 4px;
}

.age-count {
  font-weight: 700;
  color: #0f172a;
  font-size: 15px;
}

.age-label {
  font-size: 12px;
  color: #64748b;
}

.text-right {
  text-align: right;
}

.action-buttons {
  display: inline-flex;
  gap: 6px;
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
