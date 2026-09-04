<template>
  <MainLayout title="Overview" username="Admin">
    <div class="accomplishment-view-container">

      <!-- Header & Action Bar -->
      <div class="module-header-bar">
        <div>
          <h2>Overview</h2>
          <p class="subtitle">Summary of accomplishments and activities</p>
        </div>
        <button class="add-btn" type="button" @click="openCreateModal">
          <ion-icon :icon="addOutline"></ion-icon>
          <span>Add Activity</span>
        </button>
      </div>

      <!-- Overview Summary Cards (6 Cards) -->
      <AccomplishmentNavCards :counts="summary?.counts" />

      <!-- Current Day Table Preview -->
      <AccomplishmentTodayTable
        :records="summary?.today_records || []"
        :loading="loading"
        @select="handleRowSelect"
        @add-first="openCreateModal"
      />

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
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IonIcon, onIonViewWillEnter } from '@ionic/vue'
import { addOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import AccomplishmentNavCards from '../../components/accomplishments/AccomplishmentNavCards.vue'
import AccomplishmentTodayTable from '../../components/accomplishments/AccomplishmentTodayTable.vue'
import AccomplishmentFormModal from '../../components/accomplishments/AccomplishmentFormModal.vue'
import AccomplishmentDetailModal from '../../components/accomplishments/AccomplishmentDetailModal.vue'

import type {
  AccomplishmentItem,
  AccomplishmentOptions,
  OverviewSummary
} from '../../types/accomplishment'
import {
  fetchOverviewSummary,
  fetchAccomplishmentOptions,
  fetchAccomplishmentById
} from '../../services/accomplishmentService'

const route = useRoute()
const loading = ref(true)
const summary = ref<OverviewSummary | null>(null)

const isFormOpen = ref(false)
const isDetailOpen = ref(false)
const selectedRecord = ref<AccomplishmentItem | null>(null)

const options = reactive<AccomplishmentOptions>({
  offices: [],
  categories: []
})

onMounted(() => {
  loadData()
  loadOptions()
})

onIonViewWillEnter(() => {
  loadData()
  loadOptions()
})

watch(() => route.fullPath, () => {
  loadData()
})

async function loadData() {
  loading.value = true
  const res = await fetchOverviewSummary()
  loading.value = false
  if (res.success && res.data) {
    summary.value = res.data
  }
}

async function loadOptions() {
  const res = await fetchAccomplishmentOptions()
  if (res.success && res.data) {
    options.offices = res.data.offices || []
    options.categories = res.data.categories || []
  }
}

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

async function handleRowSelect(item: AccomplishmentItem) {
  const res = await fetchAccomplishmentById(item.id)
  if (res.success && res.data) {
    selectedRecord.value = res.data
    isDetailOpen.value = true
  } else {
    selectedRecord.value = item
    isDetailOpen.value = true
  }
}

function handleEditFromDetail(record: AccomplishmentItem) {
  isDetailOpen.value = false
  selectedRecord.value = record
  isFormOpen.value = true
}

function handleSaved() {
  loadData()
}
</script>

<style scoped>
.accomplishment-view-container {
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
  transition: background-color 0.15s ease;
}

.add-btn:hover { background: #1d4ed8; }
.add-btn ion-icon { font-size: 18px; }
</style>
