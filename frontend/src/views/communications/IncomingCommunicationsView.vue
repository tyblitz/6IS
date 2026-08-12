<template>
  <MainLayout title="Incoming Communications" username="Admin">
    <div class="view-container">
      <!-- Header -->
      <div class="module-header-bar">
        <div>
          <h2>Incoming Communications</h2>
          <p class="subtitle">Register, track, and route incoming organizational communications.</p>
        </div>
        <button class="add-btn" type="button" @click="openCreateModal">
          <ion-icon :icon="addOutline" />
          <span>Log Incoming Communication</span>
        </button>
      </div>

      <!-- Filter Controls Bar -->
      <div class="filter-bar">
        <div class="search-box">
          <ion-icon :icon="searchOutline" class="search-icon" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search incoming subject, office, category..."
            @input="debouncedFetch"
          />
        </div>

        <div class="filter-controls">
          <select v-model.number="filters.office_id" @change="loadRecords">
            <option :value="0">All Offices</option>
            <option v-for="off in options.offices" :key="off.id" :value="off.id">
              {{ off.office_name }}
            </option>
          </select>

          <select v-model.number="filters.category_id" @change="loadRecords">
            <option :value="0">All Categories</option>
            <option v-for="cat in options.categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>

          <select v-model.number="filters.purpose_id" @change="loadRecords">
            <option :value="0">All Purposes</option>
            <option v-for="pur in options.purposes" :key="pur.id" :value="pur.id">
              {{ pur.name }}
            </option>
          </select>

          <select v-model="filters.status" @change="loadRecords">
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
            <option value="Archived">Archived</option>
          </select>
        </div>
      </div>

      <!-- Communication Table -->
      <CommunicationTable
        :show-type-column="false"
        :records="records"
        :loading="loading"
        @select="handleSelect"
        @edit="openEditModal"
        @delete="handleDelete"
        @add-first="openCreateModal"
      />

      <!-- Form Modal -->
      <CommunicationFormModal
        :is-open="isFormOpen"
        :options="options"
        :edit-data="selectedRecord"
        default-type="Incoming"
        @close="isFormOpen = false"
        @saved="loadRecords"
      />

      <!-- Detail Modal -->
      <CommunicationDetailModal
        :is-open="isDetailOpen"
        :data="detailRecord"
        @close="isDetailOpen = false"
        @edit="openEditFromDetail"
        @refresh="handleDetailRefresh"
      />
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IonIcon, onIonViewWillEnter } from '@ionic/vue'
import { addOutline, searchOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import CommunicationTable from '../../components/communications/CommunicationTable.vue'
import CommunicationFormModal from '../../components/communications/CommunicationFormModal.vue'
import CommunicationDetailModal from '../../components/communications/CommunicationDetailModal.vue'

import type {
  Communication,
  CommunicationOptions,
  CommunicationFilterParams
} from '../../types/communication'
import {
  fetchCommunications,
  fetchCommunicationOptions,
  fetchCommunicationById,
  deleteCommunication
} from '../../services/communicationService'

const route = useRoute()
const loading = ref(true)
const records = ref<Communication[]>([])

const isFormOpen = ref(false)
const isDetailOpen = ref(false)

const selectedRecord = ref<Communication | null>(null)
const detailRecord = ref<Communication | null>(null)

const options = reactive<CommunicationOptions>({
  categories: [],
  purposes: [],
  offices: []
})

const filters = reactive<CommunicationFilterParams>({
  type: 'Incoming',
  office_id: 0,
  category_id: 0,
  purpose_id: 0,
  status: '',
  search: ''
})

let timer: any = null
function debouncedFetch() {
  clearTimeout(timer)
  timer = setTimeout(() => {
    loadRecords()
  }, 300)
}

onMounted(() => {
  Promise.all([loadOptions(), loadRecords()])
})

onIonViewWillEnter(() => {
  Promise.all([loadOptions(), loadRecords()])
})

watch(() => route.fullPath, () => {
  loadRecords()
})

async function loadOptions() {
  const res = await fetchCommunicationOptions()
  if (res.success && res.data) {
    options.categories = res.data.categories || []
    options.purposes = res.data.purposes || []
    options.offices = res.data.offices || []
  }
}

async function loadRecords() {
  loading.value = true
  filters.type = 'Incoming'
  const res = await fetchCommunications(filters)
  loading.value = false
  if (res.success && res.data) {
    records.value = res.data
  }
}

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

function openEditModal(item: Communication) {
  selectedRecord.value = item
  isFormOpen.value = true
}

async function handleSelect(item: Communication) {
  const res = await fetchCommunicationById(item.id)
  if (res.success && res.data) {
    detailRecord.value = res.data
  } else {
    detailRecord.value = item
  }
  isDetailOpen.value = true
}

function openEditFromDetail(record: Communication) {
  isDetailOpen.value = false
  openEditModal(record)
}

async function handleDetailRefresh() {
  if (detailRecord.value?.id) {
    const res = await fetchCommunicationById(detailRecord.value.id)
    if (res.success && res.data) {
      detailRecord.value = res.data
    }
  }
  loadRecords()
}

async function handleDelete(item: Communication) {
  if (confirm(`Are you sure you want to soft delete incoming communication "${item.subject}"?`)) {
    const res = await deleteCommunication(item.id)
    if (res.success) {
      loadRecords()
    } else {
      alert(res.message || 'Failed to delete communication.')
    }
  }
}
</script>

<style scoped>
.view-container {
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

.add-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
}

.add-btn:hover { background: #1d4ed8; }

.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 20px;
  background: #ffffff;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.search-box {
  position: relative;
  flex: 1;
  min-width: 260px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 18px;
}

.search-box input {
  width: 100%;
  padding: 9px 12px 9px 38px;
  font-size: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

.filter-controls {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-controls select {
  padding: 9px 12px;
  font-size: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
  min-width: 130px;
  font-weight: 500;
  cursor: pointer;
}

@media (max-width: 640px) {
  .view-container {
    padding: 16px;
  }
  .module-header-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .add-btn {
    width: 100%;
    justify-content: center;
  }
  .filter-controls {
    width: 100%;
  }
  .filter-controls select {
    flex: 1;
    min-width: 140px;
  }
}
</style>
