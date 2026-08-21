<template>
  <MainLayout title="Calendar">
    <div class="calendar-view-container">

      <!-- Header & Action Bar -->
      <div class="module-header-bar">
        <div>
          <h2>Calendar</h2>
          <p class="subtitle">Monthly activity calendar with event tracking and history.</p>
        </div>
        <button class="add-btn" type="button" @click="openCreateModal">
          <ion-icon :icon="addOutline"></ion-icon>
          <span>Add Event</span>
        </button>
      </div>

      <!-- Month Navigation -->
      <div class="calendar-nav">
        <button class="nav-arrow" @click="prevMonth">
          <ion-icon :icon="chevronBackOutline"></ion-icon>
        </button>

        <div class="nav-month-year">
          <select v-model="currentMonth" class="month-select" @change="loadEvents">
            <option v-for="(name, idx) in monthNames" :key="idx" :value="idx">{{ name }}</option>
          </select>
          <select v-model="currentYear" class="year-select" @change="loadEvents">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <button class="nav-arrow" @click="nextMonth">
          <ion-icon :icon="chevronForwardOutline"></ion-icon>
        </button>

        <button class="today-btn" @click="goToToday">Today</button>
      </div>

      <!-- Legend -->
      <div class="calendar-legend">
        <div class="legend-item">
          <span class="legend-dot" style="background: #22c55e;"></span>
          <span>Accomplishment</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot" style="background: #3b82f6;"></span>
          <span>Incoming Comm</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot" style="background: #f97316;"></span>
          <span>Outgoing Comm</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot" style="background: #a855f7;"></span>
          <span>Calendar Event</span>
        </div>
      </div>

      <!-- Calendar Grid -->
      <div class="calendar-grid-wrapper" v-if="!loading">
        <!-- Day Headers -->
        <div class="calendar-day-headers">
          <div v-for="day in dayHeaders" :key="day" class="day-header">{{ day }}</div>
        </div>

        <!-- Day Cells Grid -->
        <div class="calendar-grid">
          <div
            v-for="(cell, idx) in calendarCells"
            :key="idx"
            class="calendar-cell"
            :class="{
              'other-month': !cell.isCurrentMonth,
              'is-today': cell.isToday,
              'has-events': cell.events.length > 0
            }"
            @click="goToDay(cell.date)"
          >
            <span class="cell-day-number">{{ cell.dayNumber }}</span>

            <!-- Event Dots -->
            <div class="cell-dots" v-if="cell.events.length > 0">
              <span
                v-for="source in getUniqueSources(cell.events)"
                :key="source"
                class="event-dot"
                :style="{ background: sourceColors[source] }"
                :title="sourceLabels[source]"
              ></span>
            </div>

            <!-- Event Count Badge -->
            <span v-if="cell.events.length > 0" class="event-count-badge">
              {{ cell.events.length }}
            </span>

            <!-- Event Preview (first 2) -->
            <div class="cell-event-previews" v-if="cell.events.length > 0 && cell.isCurrentMonth">
              <div
                v-for="(ev, i) in cell.events.slice(0, 2)"
                :key="ev.id"
                class="event-preview-item"
                :style="{ borderLeftColor: sourceColors[ev.source] }"
              >
                <span class="event-preview-text">{{ ev.title }}</span>
              </div>
              <div v-if="cell.events.length > 2" class="event-preview-more">
                +{{ cell.events.length - 2 }} more
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else class="calendar-loading">
        <ion-spinner name="crescent"></ion-spinner>
        <p>Loading calendar...</p>
      </div>

      <!-- Create/Edit Event Modal -->
      <div v-if="isFormOpen" class="modal-overlay" @click.self="isFormOpen = false">
        <div class="modal-card event-modal">
          <div class="modal-header">
            <h3>{{ editingEvent ? 'Edit Event' : 'New Event' }}</h3>
            <button class="close-btn" @click="isFormOpen = false">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Title *</label>
              <input v-model="formData.title" type="text" placeholder="Event title" class="form-input" />
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea v-model="formData.description" placeholder="Optional description" class="form-input form-textarea" rows="3"></textarea>
            </div>
            <div class="form-row-2col">
              <div class="form-group">
                <label>Date *</label>
                <input v-model="formData.event_date" type="date" class="form-input" />
              </div>
              <div class="form-group">
                <label>Time</label>
                <input v-model="formData.event_time" type="time" class="form-input" />
              </div>
            </div>
            <div class="form-row-2col">
              <div class="form-group">
                <label>Event Type</label>
                <select v-model="formData.event_type" class="form-input">
                  <option value="meeting">Meeting</option>
                  <option value="deadline">Deadline</option>
                  <option value="reminder">Reminder</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="form-group">
                <label>Priority</label>
                <select v-model="formData.priority" class="form-input">
                  <option value="low">Low</option>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="isFormOpen = false">Cancel</button>
            <button class="btn-save" @click="saveEvent" :disabled="saving">
              {{ saving ? 'Saving...' : (editingEvent ? 'Update Event' : 'Create Event') }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { IonIcon, IonSpinner } from '@ionic/vue'
import {
  addOutline,
  chevronBackOutline,
  chevronForwardOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { fetchMonthEvents, createCalendarEvent, updateCalendarEvent } from '../../services/calendarService'
import type { CalendarEvent, CalendarDayData, CalendarEventFormPayload, CalendarEventSource } from '../../types/calendar'
import { SOURCE_COLORS, SOURCE_LABELS } from '../../types/calendar'

const router = useRouter()
const loading = ref(true)
const saving = ref(false)
const isFormOpen = ref(false)
const editingEvent = ref<CalendarEvent | null>(null)
const events = ref<CalendarEvent[]>([])

const sourceColors = SOURCE_COLORS
const sourceLabels = SOURCE_LABELS

const now = new Date()
const currentMonth = ref(now.getMonth())
const currentYear = ref(now.getFullYear())

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]
const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  const years = []
  for (let i = y - 5; i <= y + 5; i++) years.push(i)
  return years
})

const defaultForm = (): CalendarEventFormPayload => ({
  title: '',
  description: '',
  event_date: new Date().toISOString().slice(0, 10),
  event_time: '',
  event_type: 'other',
  priority: 'normal'
})

const formData = ref<CalendarEventFormPayload>(defaultForm())

// Build calendar grid cells for the current month
const calendarCells = computed<CalendarDayData[]>(() => {
  const year = currentYear.value
  const month = currentMonth.value
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startDayOfWeek = firstDay.getDay() // 0=Sun
  const daysInMonth = lastDay.getDate()

  const today = new Date()
  const todayStr = today.toISOString().slice(0, 10)

  const cells: CalendarDayData[] = []

  // Previous month fill
  const prevMonthLastDay = new Date(year, month, 0).getDate()
  for (let i = startDayOfWeek - 1; i >= 0; i--) {
    const d = prevMonthLastDay - i
    const dateStr = formatDateStr(year, month - 1, d)
    cells.push({
      date: dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      events: getEventsForDate(dateStr)
    })
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = formatDateStr(year, month, d)
    cells.push({
      date: dateStr,
      dayNumber: d,
      isCurrentMonth: true,
      isToday: dateStr === todayStr,
      events: getEventsForDate(dateStr)
    })
  }

  // Next month fill (to make 6 rows = 42 cells)
  const remaining = 42 - cells.length
  for (let d = 1; d <= remaining; d++) {
    const dateStr = formatDateStr(year, month + 1, d)
    cells.push({
      date: dateStr,
      dayNumber: d,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      events: getEventsForDate(dateStr)
    })
  }

  return cells
})

function formatDateStr(year: number, month: number, day: number): string {
  const dt = new Date(year, month, day)
  return dt.toISOString().slice(0, 10)
}

function getEventsForDate(dateStr: string): CalendarEvent[] {
  return events.value.filter(e => e.date === dateStr)
}

function getUniqueSources(evts: CalendarEvent[]): CalendarEventSource[] {
  const seen = new Set<CalendarEventSource>()
  for (const e of evts) seen.add(e.source)
  return Array.from(seen)
}

async function loadEvents() {
  loading.value = true
  events.value = await fetchMonthEvents(currentYear.value, currentMonth.value + 1)
  loading.value = false
}

function prevMonth() {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
  loadEvents()
}

function nextMonth() {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
  loadEvents()
}

function goToToday() {
  const n = new Date()
  currentMonth.value = n.getMonth()
  currentYear.value = n.getFullYear()
  loadEvents()
}

function goToDay(dateStr: string) {
  router.push(`/calendar/day/${dateStr}`)
}

function openCreateModal() {
  editingEvent.value = null
  formData.value = defaultForm()
  isFormOpen.value = true
}

async function saveEvent() {
  if (!formData.value.title.trim() || !formData.value.event_date) return
  saving.value = true

  let result
  if (editingEvent.value) {
    result = await updateCalendarEvent({
      ...formData.value,
      id: editingEvent.value.source_id
    })
  } else {
    result = await createCalendarEvent(formData.value)
  }

  if (result.success) {
    isFormOpen.value = false
    await loadEvents()
  }
  saving.value = false
}

onMounted(() => {
  loadEvents()
})
</script>

<style scoped>
.calendar-view-container {
  padding: 0;
}

/* Navigation */
.calendar-nav {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.nav-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  color: #475569;
  transition: all 0.2s;
}
.nav-arrow:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.nav-month-year {
  display: flex;
  gap: 8px;
}

.month-select, .year-select {
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  background: #fff;
  cursor: pointer;
}

.today-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid #6366f1;
  background: #6366f1;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.today-btn:hover {
  background: #4f46e5;
}

/* Legend */
.calendar-legend {
  display: flex;
  gap: 20px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

/* Calendar Grid */
.calendar-grid-wrapper {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.calendar-day-headers {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.day-header {
  text-align: center;
  padding: 12px 4px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

.calendar-cell {
  min-height: 110px;
  padding: 8px;
  border-right: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: all 0.15s;
  position: relative;
  display: flex;
  flex-direction: column;
}

.calendar-cell:nth-child(7n) {
  border-right: none;
}

.calendar-cell:hover {
  background: #f0f4ff;
}

.calendar-cell.other-month {
  background: #fafafa;
  opacity: 0.5;
}

.calendar-cell.is-today {
  background: #eff6ff;
}

.calendar-cell.is-today .cell-day-number {
  background: #6366f1;
  color: #fff;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.cell-day-number {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  margin-bottom: 4px;
}

.cell-dots {
  display: flex;
  gap: 3px;
  margin-bottom: 4px;
}

.event-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  display: inline-block;
}

.event-count-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: #6366f1;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cell-event-previews {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  overflow: hidden;
}

.event-preview-item {
  padding: 2px 6px;
  border-left: 3px solid #94a3b8;
  border-radius: 2px;
  background: #f8fafc;
  overflow: hidden;
}

.event-preview-text {
  font-size: 10px;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}

.event-preview-more {
  font-size: 10px;
  color: #6366f1;
  font-weight: 600;
  padding: 0 6px;
}

/* Loading */
.calendar-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 0;
  color: #94a3b8;
  gap: 12px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-card {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #94a3b8;
  cursor: pointer;
  line-height: 1;
}
.close-btn:hover { color: #475569; }

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}

.form-input {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  color: #1e293b;
  background: #fff;
  transition: border-color 0.2s;
}
.form-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.form-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

.btn-cancel {
  padding: 10px 20px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-cancel:hover { background: #f1f5f9; }

.btn-save {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  background: #6366f1;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-save:hover { background: #4f46e5; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 768px) {
  .calendar-cell {
    min-height: 70px;
    padding: 4px;
  }
  .cell-event-previews {
    display: none;
  }
  .form-row-2col {
    grid-template-columns: 1fr;
  }
}
</style>
