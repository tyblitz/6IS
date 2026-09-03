<!-- frontend/src/views/calendar/CalendarView.vue -->
<template>
  <MainLayout title="Calendar">
    <div class="calendar-module-wrapper">
      <!-- 1. Page Header with Title, Subtitle, and Primary Add Activity Action -->
      <CalendarHeader @add-event="handleOpenCreateModal(currentDateStr)" />

      <!-- 2. Integrated Card Container (Single Unified Card: Toolbar + Dynamic Calendar Grid) -->
      <div class="calendar-integrated-card">
        <!-- Toolbar (Single Row: Date Navigation + View Switcher + Search + Event Type Filter) -->
        <CalendarToolbar
          :period-title="formattedPeriodTitle"
          :current-view="currentView"
          :event-type-options="eventTypeOptions"
          v-model:search-query="searchQuery"
          v-model:selected-type-id="selectedTypeId"
          @prev="handlePrevPeriod"
          @next="handleNextPeriod"
          @today="handleGoToToday"
          @change-view="handleChangeView"
        />

        <!-- Loading Spinner -->
        <div v-if="loading" class="calendar-loading-state" style="text-align: center; padding: 60px; color: #64748B; background: #FFFFFF; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
          <ion-spinner name="crescent" style="font-size: 2rem; color: #2563EB;"></ion-spinner>
          <p style="margin-top: 10px; font-weight: 600;">Loading operational activities...</p>
        </div>

        <!-- Dynamic Views attached directly below Toolbar -->
        <template v-else>
          <!-- Month View -->
          <CalendarGrid
            v-if="currentView === 'month'"
            :calendar-cells="calendarCells"
            @select-day="handleSelectDay"
            @click-activity="handleOpenActivityDetails"
          />

          <!-- Week View -->
          <CalendarWeekView
            v-else-if="currentView === 'week'"
            :current-date="currentDateStr"
            :activities="activities"
            @select-day="handleSelectDay"
            @click-activity="handleOpenActivityDetails"
          />

          <!-- Day View -->
          <CalendarDayView
            v-else-if="currentView === 'day'"
            :current-date="currentDateStr"
            :activities="activities"
            @click-activity="handleOpenActivityDetails"
          />
        </template>
      </div>

      <!-- 3. Slide-Over Day Drawer -->
      <CalendarDayDrawer
        :is-open="isDrawerOpen"
        :selected-date="selectedDrawerDate"
        :activities="activities"
        @close="isDrawerOpen = false"
        @add-event-for-date="handleOpenCreateModal($event)"
        @click-activity="handleOpenActivityDetails"
      />

      <!-- 4. Activity Details Modal -->
      <CalendarActivityDetailsModal
        :is-open="isDetailsModalOpen"
        :activity="selectedActivity"
        @close="isDetailsModalOpen = false"
        @updated="loadData"
        @edit="handleOpenEditModal"
        @delete="handleConfirmDeleteEvent"
        @open-reschedule="handleOpenRescheduleModal"
        @open-accomplishment-form="handleOpenAccomplishmentPrefill"
      />

      <!-- 5. Reschedule Modal -->
      <CalendarRescheduleModal
        :is-open="isRescheduleModalOpen"
        :activity="selectedRescheduleActivity"
        @close="isRescheduleModalOpen = false"
        @rescheduled="loadData"
      />

      <!-- 6. Accomplishment Form Modal with Prefill Support -->
      <AccomplishmentFormModal
        :is-open="isAccomplishmentModalOpen"
        :options="accomplishmentOptions"
        :calendar-prefill-data="accomplishmentPrefillData"
        @close="isAccomplishmentModalOpen = false"
        @saved="loadData"
      />

      <!-- 7. Create / Edit Event Modal -->
      <CalendarEventModal
        :is-open="isEventModalOpen"
        :prepopulated-date="prepopulatedModalDate"
        :editing-activity="editingActivity"
        @close="isEventModalOpen = false"
        @save-event="handleSaveEventPayload"
      />
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { IonSpinner } from '@ionic/vue'
import MainLayout from '../../layouts/MainLayout.vue'
import CalendarHeader from '../../components/calendar/CalendarHeader.vue'
import CalendarToolbar from '../../components/calendar/CalendarToolbar.vue'
import CalendarGrid from '../../components/calendar/CalendarGrid.vue'
import CalendarWeekView from '../../components/calendar/CalendarWeekView.vue'
import CalendarDayView from '../../components/calendar/CalendarDayView.vue'
import CalendarDayDrawer from '../../components/calendar/CalendarDayDrawer.vue'
import CalendarActivityDetailsModal from '../../components/calendar/CalendarActivityDetailsModal.vue'
import CalendarRescheduleModal from '../../components/calendar/CalendarRescheduleModal.vue'
import CalendarEventModal from '../../components/calendar/CalendarEventModal.vue'
import AccomplishmentFormModal from '../../components/accomplishments/AccomplishmentFormModal.vue'

import '../../assets/styles/components/calendar.css'

import type {
  CalendarViewMode,
  CalendarActivity,
  CalendarDayData,
  CalendarEventTypeOption,
  CalendarEventFormPayload
} from '../../types/calendar'

import type {
  AccomplishmentOptions
} from '../../types/accomplishment'

import {
  fetchCalendarActivities,
  fetchCalendarEventTypes,
  createCalendarEvent,
  updateCalendarEvent,
  deleteCalendarEvent
} from '../../services/calendarService'

import { fetchAccomplishmentOptions } from '../../services/accomplishmentService'

// Router
const route = useRoute()

// State
const currentDate = ref(new Date())
const currentView = ref<CalendarViewMode>('month')
const searchQuery = ref('')
const selectedTypeId = ref(0)
const activities = ref<CalendarActivity[]>([])
const eventTypeOptions = ref<CalendarEventTypeOption[]>([])
const accomplishmentOptions = ref<AccomplishmentOptions>({
  offices: [],
  categories: []
})
const loading = ref(false)

// Drawer & Modal States
const isDrawerOpen = ref(false)
const selectedDrawerDate = ref('')

const isDetailsModalOpen = ref(false)
const selectedActivity = ref<CalendarActivity | null>(null)

const isRescheduleModalOpen = ref(false)
const selectedRescheduleActivity = ref<CalendarActivity | null>(null)

const isAccomplishmentModalOpen = ref(false)
const accomplishmentPrefillData = ref<{
  calendar_event_id: number;
  title: string;
  description?: string;
  date: string;
  location?: string;
  priority?: string;
} | null>(null)

const isEventModalOpen = ref(false)
const prepopulatedModalDate = ref('')
const editingActivity = ref<CalendarActivity | null>(null)

const currentDateStr = computed(() => toYMD(currentDate.value))

// Formatted period title in toolbar
const formattedPeriodTitle = computed(() => {
  const dt = currentDate.value
  if (currentView.value === 'month') {
    return dt.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
  } else if (currentView.value === 'day') {
    // Exact requested format: 28 Aug 2026, Friday
    const day = dt.getDate()
    const month = dt.toLocaleDateString('en-US', { month: 'short' })
    const year = dt.getFullYear()
    const weekday = dt.toLocaleDateString('en-US', { weekday: 'long' })
    return `${day} ${month} ${year}, ${weekday}`
  } else {
    // Week View: Sun to Sat e.g. 10 - 16 Aug 2026
    const dayOfWeek = dt.getDay() // 0 is Sun
    const sun = new Date(dt)
    sun.setDate(dt.getDate() - dayOfWeek)

    const sat = new Date(sun)
    sat.setDate(sun.getDate() + 6)

    const sunDay = sun.getDate()
    const satDay = sat.getDate()
    const sunMonth = sun.toLocaleDateString('en-US', { month: 'short' })
    const satMonth = sat.toLocaleDateString('en-US', { month: 'short' })
    const sunYear = sun.getFullYear()
    const satYear = sat.getFullYear()

    if (sunMonth === satMonth && sunYear === satYear) {
      return `${sunDay} - ${satDay} ${sunMonth} ${sunYear}`
    } else if (sunYear === satYear) {
      return `${sunDay} ${sunMonth} - ${satDay} ${satMonth} ${sunYear}`
    } else {
      return `${sunDay} ${sunMonth} ${sunYear} - ${satDay} ${satMonth} ${satYear}`
    }
  }
})

// Generate Month Calendar Cells
const calendarCells = computed<CalendarDayData[]>(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()

  const firstDayOfMonth = new Date(year, month, 1)
  const lastDayOfMonth = new Date(year, month + 1, 0)
  const daysInMonth = lastDayOfMonth.getDate()

  const startDayOfWeek = firstDayOfMonth.getDay()
  const prevMonthLastDay = new Date(year, month, 0).getDate()

  const todayStr = toYMD(new Date())
  const cells: CalendarDayData[] = []

  // 1. Previous month trailing days
  for (let i = startDayOfWeek - 1; i >= 0; i--) {
    const dayNum = prevMonthLastDay - i
    const d = new Date(year, month - 1, dayNum)
    const dateStr = toYMD(d)
    const isWeekend = d.getDay() === 0 || d.getDay() === 6

    const cellEvents = activities.value.filter(a => a.date === dateStr)
    cells.push({
      date: dateStr,
      dayNumber: dayNum,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      isWeekend,
      events: cellEvents
    })
  }

  // 2. Current month days
  for (let dayNum = 1; dayNum <= daysInMonth; dayNum++) {
    const d = new Date(year, month, dayNum)
    const dateStr = toYMD(d)
    const isWeekend = d.getDay() === 0 || d.getDay() === 6

    const cellEvents = activities.value.filter(a => a.date === dateStr)
    cells.push({
      date: dateStr,
      dayNumber: dayNum,
      isCurrentMonth: true,
      isToday: dateStr === todayStr,
      isWeekend,
      events: cellEvents
    })
  }

  // 3. Next month leading days to complete grid 35 or 42 cells
  const totalCells = cells.length > 35 ? 42 : 35
  const remainingCells = totalCells - cells.length
  for (let dayNum = 1; dayNum <= remainingCells; dayNum++) {
    const d = new Date(year, month + 1, dayNum)
    const dateStr = toYMD(d)
    const isWeekend = d.getDay() === 0 || d.getDay() === 6

    const cellEvents = activities.value.filter(a => a.date === dateStr)
    cells.push({
      date: dateStr,
      dayNumber: dayNum,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      isWeekend,
      events: cellEvents
    })
  }

  return cells
})

// Load Data from backend API
async function loadData() {
  loading.value = true
  try {
    const start = '2020-01-01'
    const end = '2030-12-31'
    const [typesRes, activitiesRes, accOptsRes] = await Promise.all([
      fetchCalendarEventTypes(),
      fetchCalendarActivities(start, end, selectedTypeId.value, 'all', searchQuery.value),
      fetchAccomplishmentOptions()
    ])

    eventTypeOptions.value = typesRes || []
    activities.value = activitiesRes || []

    if (accOptsRes && accOptsRes.data) {
      accomplishmentOptions.value = accOptsRes.data
    }
  } catch (err) {
    console.error('Failed to load calendar data:', err)
  } finally {
    loading.value = false
  }
}

function applyRouteQuery() {
  if (route.query.view && ['month', 'week', 'day'].includes(route.query.view as string)) {
    currentView.value = route.query.view as CalendarViewMode
  }
  if (route.query.date && typeof route.query.date === 'string') {
    const parsed = new Date(route.query.date + 'T00:00:00')
    if (!isNaN(parsed.getTime())) {
      currentDate.value = parsed
    }
  }
}

watch(
  () => [searchQuery.value, selectedTypeId.value],
  () => {
    loadData()
  }
)

watch(
  () => [route.query.view, route.query.date],
  () => {
    applyRouteQuery()
  }
)

onMounted(() => {
  applyRouteQuery()
  loadData()
})

// Navigation Handlers
function handlePrevPeriod() {
  const d = new Date(currentDate.value)
  if (currentView.value === 'month') {
    d.setMonth(d.getMonth() - 1)
  } else if (currentView.value === 'week') {
    d.setDate(d.getDate() - 7)
  } else {
    d.setDate(d.getDate() - 1)
  }
  currentDate.value = d
}

function handleNextPeriod() {
  const d = new Date(currentDate.value)
  if (currentView.value === 'month') {
    d.setMonth(d.getMonth() + 1)
  } else if (currentView.value === 'week') {
    d.setDate(d.getDate() + 7)
  } else {
    d.setDate(d.getDate() + 1)
  }
  currentDate.value = d
}

function handleGoToToday() {
  currentDate.value = new Date()
  currentView.value = 'day'
}

function handleChangeView(view: CalendarViewMode) {
  currentView.value = view
}

// Drawer & Modal Handlers
function handleSelectDay(dateStr: string) {
  selectedDrawerDate.value = dateStr
  isDrawerOpen.value = true
}

function handleOpenActivityDetails(act: CalendarActivity) {
  selectedActivity.value = act
  isDetailsModalOpen.value = true
}

function handleOpenRescheduleModal(act: CalendarActivity) {
  selectedRescheduleActivity.value = act
  isRescheduleModalOpen.value = true
}

function handleOpenCreateModal(dateStr?: string) {
  editingActivity.value = null
  prepopulatedModalDate.value = dateStr || currentDateStr.value
  isEventModalOpen.value = true
}

function handleOpenEditModal(act: CalendarActivity) {
  isDetailsModalOpen.value = false
  editingActivity.value = act
  isEventModalOpen.value = true
}

async function handleSaveEventPayload(payload: CalendarEventFormPayload) {
  const res = payload.id
    ? await updateCalendarEvent(payload)
    : await createCalendarEvent(payload)
  if (res.success) {
    isEventModalOpen.value = false
    await loadData()
  } else {
    alert(res.message || 'Failed to save activity.')
  }
}

async function handleConfirmDeleteEvent(act: CalendarActivity) {
  if (confirm(`Are you sure you want to delete "${act.title}"?`)) {
    const res = await deleteCalendarEvent(act.source_id)
    if (res.success) {
      isDetailsModalOpen.value = false
      await loadData()
    } else {
      alert(res.message || 'Failed to delete activity.')
    }
  }
}

function handleOpenAccomplishmentPrefill(act: CalendarActivity) {
  isDetailsModalOpen.value = false
  accomplishmentPrefillData.value = {
    calendar_event_id: act.source_id,
    title: act.title,
    description: act.title,
    date: act.date
  }
  isAccomplishmentModalOpen.value = true
}

function toYMD(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}
</script>
