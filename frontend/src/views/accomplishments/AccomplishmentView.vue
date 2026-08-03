<template>
  <MainLayout title="Accomplishments" username="Admin">
    <div class="accomplishment-view-container">

      <!-- Header & Action Bar -->
      <div class="module-header-bar">
        <div>
          <h2>Overview</h2>
          <p class="subtitle">Daily operational achievement tracking and report launchers.</p>
        </div>
        <button class="add-btn" type="button" @click="openCreateModal">
          <ion-icon :icon="addOutline"></ion-icon>
          <span>Add Accomplishment</span>
        </button>
      </div>

      <!-- Vue Overview Navigation Cards -->
      <AccomplishmentNavCards />

      <!-- Current Day Table Section -->
      <AccomplishmentTodayTable
        :records="todayRecords"
        :loading="loadingToday"
        @select="handleRowSelect"
        @add-first="openCreateModal"
      />

      <!-- Form Modal (Create & Edit) -->
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
  Accomplishment,
  AccomplishmentTodayItem,
  AccomplishmentOptions
} from '../../types/accomplishment'
import {
  fetchTodayAccomplishments,
  fetchAccomplishmentOptions,
  fetchAccomplishmentById
} from '../../services/accomplishmentService'

const route = useRoute()
const loadingToday = ref(true)
const todayRecords = ref<AccomplishmentTodayItem[]>([])

const isFormOpen = ref(false)
const isDetailOpen = ref(false)
const selectedRecord = ref<Accomplishment | null>(null)

const options = reactive<AccomplishmentOptions>({
  offices: [],
  categories: [],
  users: []
})

onMounted(() => {
  loadTodayData()
  loadOptions()
})

onIonViewWillEnter(() => {
  loadTodayData()
  loadOptions()
})

watch(() => route.fullPath, () => {
  loadTodayData()
})

async function loadTodayData() {
  loadingToday.value = true
  const res = await fetchTodayAccomplishments()
  loadingToday.value = false
  if (res.success && Array.isArray(res.data)) {
    todayRecords.value = res.data
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

function openCreateModal() {
  selectedRecord.value = null
  isFormOpen.value = true
}

async function handleRowSelect(item: AccomplishmentTodayItem) {
  const res = await fetchAccomplishmentById(item.id)
  if (res.success && res.data) {
    selectedRecord.value = res.data
    isDetailOpen.value = true
  }
}

function handleEditFromDetail(record: Accomplishment) {
  isDetailOpen.value = false
  selectedRecord.value = record
  isFormOpen.value = true
}

function handleSaved() {
  loadTodayData()
}
</script>

<style scoped>
.accomplishment-view-container {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.module-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.module-header-bar h2 {
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
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
  transition: background-color 0.15s ease;
}

.add-btn:hover {
  background: #1d4ed8;
}

.add-btn ion-icon {
  font-size: 18px;
}
</style>
