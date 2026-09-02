<template>
  <div class="table-card">
    <div class="table-header-bar">
      <h3>Communication Records</h3>
      <span class="record-count" v-if="!loading">{{ totalItems }} records</span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-container">
      <p>Loading communications...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="totalItems === 0" class="state-container empty-state">
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
            <th v-if="showTypeColumn" class="col-type sortable-th" @click="toggleSort('communication_type')">
              <div class="th-content">
                <span>Type</span>
                <ion-icon :icon="getSortIcon('communication_type')" :class="['sort-icon', sortKey === 'communication_type' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="col-office sortable-th" @click="toggleSort('office_abbv')">
              <div class="th-content">
                <span>Office</span>
                <ion-icon :icon="getSortIcon('office_abbv')" :class="['sort-icon', sortKey === 'office_abbv' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="col-subject sortable-th" @click="toggleSort('subject')">
              <div class="th-content">
                <span>Subject & Category</span>
                <ion-icon :icon="getSortIcon('subject')" :class="['sort-icon', sortKey === 'subject' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="col-date sortable-th" @click="toggleSort('communication_date')">
              <div class="th-content">
                <span>Date</span>
                <ion-icon :icon="getSortIcon('communication_date')" :class="['sort-icon', sortKey === 'communication_date' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="col-status sortable-th" @click="toggleSort('status')">
              <div class="th-content">
                <span>Status</span>
                <ion-icon :icon="getSortIcon('status')" :class="['sort-icon', sortKey === 'status' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="col-age text-center sortable-th" @click="toggleSort('age_days')">
              <div class="th-content justify-center">
                <span>Age (Days)</span>
                <ion-icon :icon="getSortIcon('age_days')" :class="['sort-icon', sortKey === 'age_days' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="col-actions text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in paginatedItems" :key="item.id" @click="$emit('select', item)">
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

    <!-- 10-Item Pagination Controls -->
    <TablePagination
      :current-page="currentPage"
      :total-pages="totalPages"
      :total-items="totalItems"
      :start-index="startIndex"
      :end-index="endIndex"
      @change-page="setPage"
    />
  </div>
</template>

<script setup lang="ts">
import { toRef } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  documentTextOutline,
  eyeOutline,
  createOutline,
  trashOutline,
  swapVerticalOutline,
  chevronUpOutline,
  chevronDownOutline
} from 'ionicons/icons'
import type { Communication } from '../../types/communication'
import { useTablePagination } from '../../composables/useTablePagination'
import TablePagination from '../common/TablePagination.vue'
import { formatDate } from '../../utils/dateUtils'

interface Props {
  records: Communication[]
  loading: boolean
  showTypeColumn?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showTypeColumn: true
})

defineEmits<{
  (e: 'select', item: Communication): void
  (e: 'edit', item: Communication): void
  (e: 'delete', item: Communication): void
  (e: 'add-first'): void
}>()

const recordsRef = toRef(props, 'records')
const {
  currentPage,
  totalItems,
  totalPages,
  startIndex,
  endIndex,
  sortKey,
  sortOrder,
  paginatedItems,
  toggleSort,
  setPage
} = useTablePagination(recordsRef, { pageSize: 10, defaultSortKey: 'communication_date', defaultSortOrder: 'desc' })

function getSortIcon(key: string) {
  if (sortKey.value !== key) return swapVerticalOutline
  return sortOrder.value === 'asc' ? chevronUpOutline : chevronDownOutline
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
  padding: 40px;
  text-align: center;
  color: #64748b;
}

.empty-state .empty-icon {
  font-size: 36px;
  color: #94a3b8;
  margin-bottom: 8px;
}

.empty-state h4 {
  margin: 0 0 4px 0;
  color: #1e293b;
  font-size: 16px;
}

.create-first-btn {
  margin-top: 16px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.table-responsive {
  overflow-x: auto;
}

.comms-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.comms-table th {
  background: #f8fafc;
  padding: 12px 16px;
  text-align: left;
  font-weight: 700;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
}

.comms-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.comms-table tbody tr {
  cursor: pointer;
  transition: background 0.15s ease;
}

.comms-table tbody tr:hover {
  background: #f8fafc;
}

.justify-center {
  justify-content: center;
}

.type-badge {
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.badge-incoming {
  background: #dbeafe;
  color: #1d4ed8;
}

.badge-outgoing {
  background: #fef3c7;
  color: #b45309;
}

.office-abbv-text {
  font-weight: 700;
  color: #1e293b;
}

.subject-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.subject-text {
  font-weight: 600;
  color: #0f172a;
}

.tags-row {
  display: flex;
  gap: 6px;
}

.tag-badge {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
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
