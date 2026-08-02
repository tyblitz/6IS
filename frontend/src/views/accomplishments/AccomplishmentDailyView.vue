<template>
  <MainLayout title="Daily Accomplishments" username="Admin">
    <div class="daily-view-container">

      <!-- Header & Navigation Back -->
      <div class="header-bar">
        <div>
          <button class="back-link" type="button" @click="router.push('/accomplishments')">
            &larr; Back to Overview
          </button>
          <h2>{{ pageTitle }}</h2>
          <p class="subtitle">Comprehensive accomplishment logs, search, and record management.</p>
        </div>
        <button class="add-btn" type="button" @click="openCreateModal">
          <ion-icon :icon="addOutline"></ion-icon>
          <span>+ Add Accomplishment</span>
        </button>
      </div>

      <!-- Search & Filters Toolbar -->
      <div class="toolbar-card">
        <div class="toolbar-grid">
          
          <!-- Search input -->
          <div class="filter-item search-box">
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search title, employee, description..."
              @keyup.enter="loadData"
            />
          </div>

          <!-- Status Filter -->
          <div class="filter-item">
            <select v-model="filters.status" @change="loadData">
              <option value="">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Ongoing">Ongoing</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>

          <!-- Priority Filter -->
          <div class="filter-item">
            <select v-model="filters.priority" @change="loadData">
              <option value="">All Priorities</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
              <option value="Critical">Critical</option>
            </select>
          </div>

          <!-- Office Filter -->
          <div class="filter-item">
            <select v-model.number="filters.office_id" @change="loadData">
              <option :value="0">All Offices</option>
              <option v-for="opt in options.offices" :key="opt.id" :value="opt.id">
                {{ opt.office_code }}
              </option>
            </select>
          </div>

          <!-- Action Buttons -->
          <div class="filter-actions">
            <button class="btn-filter" type="button" @click="loadData">Filter</button>
            <button class="btn-reset" type="button" @click="resetFilters">Reset</button>
          </div>

        </div>
      </div>

      <!-- Detailed Table -->
      <div class="table-card">
        <div v-if="loading" class="loading-state">
          <ion-spinner name="crescent"></ion-spinner>
          <span>Loading accomplishments...</span>
        </div>

        <div v-else-if="records.length === 0" class="empty-state">
          <ion-icon :icon="clipboardOutline" class="empty-icon"></ion-icon>
          <p>No accomplishment records found matching your filters.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="full-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Employee</th>
                <th>Office</th>
                <th>Category</th>
                <th>Date Started</th>
                <th>Status</th>
                <th>Priority</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in records" :key="item.id">
                <td class="font-medium">{{ item.title }}</td>
                <td>{{ item.assigned_employee_name }}</td>
                <td>
                  <span class="office-tag">{{ item.office_code || item.office_name }}</span>
                </td>
                <td>{{ item.category_name }}</td>
                <td>{{ item.date_started }}</td>
                <td>
                  <span :class="['badge', 'status-badge', item.status.toLowerCase()]">
                    {{ item.status }}
                  </span>
                </td>
                <td>
                  <span :class="['badge', 'priority-badge', item.priority.toLowerCase()]">
                    {{ item.priority }}
                  </span>
                </td>
                <td class="text-right actions-cell">
                  <button class="icon-action-btn view-btn" title="View Details" @click="handleView(item)">
                    <ion-icon :icon="eyeOutline"></ion-icon>
                  </button>
                  <button class="icon-action-btn edit-btn" title="Edit Record" @click="handleEdit(item)">
                    <ion-icon :icon="createOutline"></ion-icon>
                  </button>
                  <button class="icon-action-btn delete-btn" title="Soft Delete" @click="handleDeletePrompt(item)">
                    <ion-icon :icon="trashOutline"></ion-icon>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Form Modal -->
      <AccomplishmentFormModal
        :is-open="isFormOpen"
        :options="options"
        :edit-data="selectedRecord"
        @close="isFormOpen = false"
        @saved="handleSaved"
      />

      <!-- Details Modal -->
      <AccomplishmentDetailModal
        :is-open="isDetailOpen"
        :data="selectedRecord"
        @close="isDetailOpen = false"
        @edit="handleEditFromDetail"
      />

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { IonSpinner, IonIcon } from '@ionic/vue'
import {
  addOutline,
  clipboardOutline,
  eyeOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import AccomplishmentFormModal from '../../components/accomplishments/AccomplishmentFormModal.vue'
import AccomplishmentDetailModal from '../../components/accomplishments/AccomplishmentDetailModal.vue'

import type {
  Accomplishment,
  AccomplishmentOptions
} from '../../types/accomplishment'
import {
  fetchAccomplishmentsList,
  fetchAccomplishmentOptions,
  deleteAccomplishment
} from '../../services/accomplishmentService'

const router = useRouter()
const route = useRoute()

const loading = ref(true)
const records = ref<Accomplishment[]>([])
const selectedRecord = ref<Accomplishment | null>(null)
const isFormOpen = ref(false)
const isDetailOpen = ref(false)

const viewMode = computed(() => (route.query.view as string) || 'daily')

const pageTitle = computed(() => {
  if (viewMode.value === 'monthly') return 'Monthly Accomplishments'
  if (viewMode.value === 'annual') return 'Annual Accomplishments'
  return 'Daily Accomplishments'
})

const options = reactive<AccomplishmentOptions>({
  offices: [],
  categories: [],
  users: []
})

const filters = reactive({
  search: '',
  status: '',
  priority: '',
  office_id: 0
})

onMounted(() => {
  loadOptions()
  loadData()
})

watch(() => route.query.view, () => {
  loadData()
})

async function loadData() {
  loading.value = true
  const res = await fetchAccomplishmentsList({
    view: viewMode.value,
    search: filters.search,
    status: filters.status,
    priority: filters.priority,
    office_id: filters.office_id > 0 ? filters.office_id : undefined
  })
  loading.value = false

  if (res.success && Array.isArray(res.data)) {
    records.value = res.data
  }
}

async function loadOptions() {
  const res = await fetchAccomplishmentOptions()
  if (res.success && res.data) {
    options.offices = res.data.offices || []
    options.categories = res.data.categories || []
    options.users = res.data.users || []
  }
}

function resetFilters() {
  filters.search = ''
  filters.status = ''
  filters.priority = ''
  filters.office_id = 0
  loadData()
}

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

function handleView(item: Accomplishment) {
  selectedRecord.value = item
  isDetailOpen.value = true
}

function handleEdit(item: Accomplishment) {
  selectedRecord.value = item
  isFormOpen.value = true
}

function handleEditFromDetail(record: Accomplishment) {
  isDetailOpen.value = false
  selectedRecord.value = record
  isFormOpen.value = true
}

async function handleDeletePrompt(item: Accomplishment) {
  if (confirm(`Are you sure you want to delete the accomplishment "${item.title}"?`)) {
    const res = await deleteAccomplishment(item.id)
    if (res.success) {
      loadData()
    } else {
      alert(res.message || 'Failed to delete record.')
    }
  }
}

function handleSaved() {
  loadData()
}
</script>

<style scoped>
.daily-view-container {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.back-link {
  background: none;
  border: none;
  color: #2563eb;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  margin-bottom: 8px;
}

.header-bar h2 {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.add-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;

}

.add-btn:hover { background: #1d4ed8; }

.toolbar-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 20px;
}

.toolbar-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}

.filter-item {
  flex: 1;
  min-width: 140px;
}

.filter-item.search-box {
  flex: 2;
  min-width: 220px;
}

input, select {
  width: 100%;
  padding: 8px 12px;
  font-size: 14px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  outline: none;
  background: #ffffff;
}

.filter-actions {
  display: flex;
  gap: 8px;
}

.btn-filter {
  background: #3b82f6;
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.btn-reset {
  background: #f3f4f6;
  color: #4b5563;
  border: 1px solid #d1d5db;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.table-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #6b7280;
  gap: 8px;
}

.empty-icon { font-size: 36px; color: #9ca3af; }

.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.full-table {
  width: 100%;
  border-collapse: collapse;
}

.full-table th {
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.full-table td {
  padding: 12px;
  font-size: 14px;
  color: #374151;
  border-bottom: 1px solid #f3f4f6;
}

.font-medium { font-weight: 500; color: #111827; }
.text-right { text-align: right; }

.office-tag {
  font-size: 11px;
  font-weight: 600;
  color: #1d4ed8;
  background: #dbeafe;
  padding: 2px 8px;
  border-radius: 4px;
}

.badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 10px;
}

.status-badge.completed { background: #dcfce7; color: #15803d; }
.status-badge.ongoing { background: #dbeafe; color: #1d4ed8; }
.status-badge.pending { background: #fef3c7; color: #b45309; }
.status-badge.cancelled { background: #fee2e2; color: #b91c1c; }

.priority-badge.low { background: #f3f4f6; color: #4b5563; }
.priority-badge.medium { background: #e0e7ff; color: #4338ca; }
.priority-badge.high { background: #ffedd5; color: #c2410c; }
.priority-badge.critical { background: #ffe4e6; color: #be123c; }

.actions-cell {
  white-space: nowrap;
}

.icon-action-btn {
  background: transparent;
  border: none;
  font-size: 16px;
  padding: 6px;
  cursor: pointer;
  border-radius: 4px;
  margin-left: 4px;
  transition: background-color 0.15s ease;
}

.view-btn { color: #3b82f6; }
.view-btn:hover { background: #eff6ff; }

.edit-btn { color: #10b981; }
.edit-btn:hover { background: #ecfdf5; }

.delete-btn { color: #ef4444; }
.delete-btn:hover { background: #fef2f2; }
</style>
