<template>
  <div class="today-table-card">
    <div class="table-header">
      <div>
        <h3>Today's Accomplishments</h3>
        <p class="subtitle">Summary of activities for {{ currentDateFormatted }}</p>
      </div>

      <!-- Search Bar -->
      <div v-if="records.length > 0" class="table-search-input">
        <ion-icon :icon="searchOutline" />
        <input v-model="searchQuery" type="text" placeholder="Search description, office, remarks..." />
      </div>
    </div>

    <!-- Table content -->
    <div v-if="loading" class="state-container">
      <ion-spinner name="crescent"></ion-spinner>
      <span>Loading today's accomplishments...</span>
    </div>

    <div v-else-if="totalItems === 0" class="state-container empty-box">
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
            <th class="sortable-th" @click="toggleSort('office_code')">
              <div class="th-content">
                <span>Office</span>
                <ion-icon :icon="getSortIcon('office_code')" :class="['sort-icon', sortKey === 'office_code' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="sortable-th" @click="toggleSort('description')">
              <div class="th-content">
                <span>Description</span>
                <ion-icon :icon="getSortIcon('description')" :class="['sort-icon', sortKey === 'description' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="sortable-th" @click="toggleSort('remarks')">
              <div class="th-content">
                <span>Remarks</span>
                <ion-icon :icon="getSortIcon('remarks')" :class="['sort-icon', sortKey === 'remarks' ? 'active-sort' : '']" />
              </div>
            </th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in paginatedItems" :key="item.id" class="clickable-row" @click="navigateToAccDetail(item)">
            <td class="whitespace-nowrap">
              <span class="office-tag">{{ item.office_code || item.office_name }}</span>
            </td>
            <td class="desc-cell">{{ item.description }}</td>
            <td class="remarks-cell">{{ item.remarks || '-' }}</td>
            <td class="text-right actions-cell" @click.stop>
              <button class="btn-view" type="button" @click="navigateToAccDetail(item)">
                View
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- 10-Item Limit Pagination -->
      <TablePagination
        :current-page="currentPage"
        :total-pages="totalPages"
        :total-items="totalItems"
        :start-index="startIndex"
        :end-index="endIndex"
        @change-page="setPage"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, toRef } from 'vue'
import { useRouter } from 'vue-router'
import { IonSpinner, IonIcon } from '@ionic/vue'
import {
  clipboardOutline,
  searchOutline,
  swapVerticalOutline,
  chevronUpOutline,
  chevronDownOutline
} from 'ionicons/icons'
import type { AccomplishmentItem } from '../../types/accomplishment'
import { useTablePagination } from '../../composables/useTablePagination'
import TablePagination from '../common/TablePagination.vue'

const router = useRouter()

function navigateToAccDetail(item: AccomplishmentItem) {
  router.push(`/accomplishments/detail/${item.id}`)
}

const props = defineProps<{
  records: AccomplishmentItem[]
  loading: boolean
}>()

defineEmits<{
  (e: 'select', item: AccomplishmentItem): void
  (e: 'add-first'): void
}>()

const recordsRef = toRef(props, 'records')
const {
  searchQuery,
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
} = useTablePagination(recordsRef, { pageSize: 10 })

function getSortIcon(key: string) {
  if (sortKey.value !== key) return swapVerticalOutline
  return sortOrder.value === 'asc' ? chevronUpOutline : chevronDownOutline
}

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
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.table-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  margin: 0;
  font-size: 13px;
  color: #64748b;
}

.table-search-input {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 240px;
}

.table-search-input ion-icon {
  position: absolute;
  left: 10px;
  font-size: 16px;
  color: #94a3b8;
}

.table-search-input input {
  padding: 6px 12px 6px 32px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  color: #1e293b;
  outline: none;
  width: 100%;
}

.table-search-input input:focus {
  border-color: #2563eb;
}

.state-container {
  padding: 30px;
  text-align: center;
  color: #64748b;
}

.empty-box .empty-icon {
  font-size: 32px;
  color: #94a3b8;
  margin-bottom: 8px;
}

.btn-first-add {
  margin-top: 12px;
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

.overview-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.overview-table th {
  background: #f8fafc;
  padding: 12px 16px;
  text-align: left;
  font-weight: 700;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
}

.overview-table td {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
}

.office-tag {
  background: #eff6ff;
  color: #1d4ed8;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
}

.btn-view {
  background: #f1f5f9;
  color: #2563eb;
  border: 1px solid #cbd5e1;
  padding: 4px 12px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

.btn-view:hover {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}
</style>
