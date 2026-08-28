<!-- frontend/src/views/DashboardView.vue -->
<template>
  <MainLayout title="Home">
    <div class="dashboard-wrapper">

      <!-- WELCOME HERO BANNER -->
      <div class="welcome-hero-card">
        <div class="hero-left">
          <h1 class="welcome-title">Welcome back, {{ activeUser?.username || 'User01' }}! 👋</h1>
          <p class="welcome-subtitle">
            Welcome back to 6IS. Please select an operational module to get started.
          </p>
        </div>
        <div class="hero-right">
          <div class="today-date-badge">
            <div class="today-icon-box">
              <ion-icon :icon="calendarOutline"></ion-icon>
            </div>
            <div class="today-text-group">
              <span class="today-date">{{ currentDateFormatted }}</span>
              <span class="today-day">{{ currentDayOfWeek }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- THIS WEEK OPERATIONAL SCHEDULE WIDGET (DENSE EXECUTIVE SCHEDULE) -->
      <div class="dash-schedule-widget">

        <!-- Executive Header -->
        <div class="dash-schedule-header">
          <div class="header-title-block">
            <span class="eyebrow-label">THIS WEEK</span>
            <h2 class="primary-schedule-title">Operational Schedule</h2>
            <p class="summary-subtitle" v-if="weekStart && weekEnd">
              {{ formatWeekRange(weekStart, weekEnd) }} · <strong class="count-accent">{{ totalScheduledCount }} Scheduled Activities</strong>
            </p>
          </div>

          <!-- Compact Control Group -->
          <div class="header-control-group">
            <div class="stepper-controls">
              <button class="stepper-btn" @click="handlePrevWeek" title="Previous Week">‹</button>
              <button class="btn-today-pill" @click="handleGoToToday">Today</button>
              <button class="stepper-btn" @click="handleNextWeek" title="Next Week">›</button>
            </div>

            <span class="control-divider">|</span>

            <router-link to="/calendar" class="link-view-calendar">
              View Full Calendar →
            </router-link>
          </div>
        </div>

        <!-- Spinner Loading State -->
        <div v-if="weekLoading" class="dash-schedule-loading">
          <ion-spinner name="crescent" style="color: #2563eb;"></ion-spinner>
          <p style="margin-top: 6px; font-weight: 600; color: #64748b; font-size: 0.8rem;">Loading operational schedule...</p>
        </div>

        <!-- 7-Column Dense Single-Row Grid (SUN to SAT) -->
        <div v-else class="dash-schedule-grid">
          <div
            v-for="day in weekDays"
            :key="day.date"
            :class="['dash-day-column', day.isToday ? 'is-today-column' : '']"
            @click="goToCalendarDay(day.date)"
          >
            <!-- Compact Day Header (FRI / 28) -->
            <div class="dash-day-header">
              <span :class="['dash-weekday-name', day.isSunday ? 'is-sunday-text' : '']">
                {{ day.dayName.toUpperCase() }}
              </span>
              <div :class="['dash-date-num-wrapper', day.isToday ? 'today-highlight' : '']">
                <span :class="['dash-date-number', day.isToday ? 'today-date-text' : (day.isSunday ? 'is-sunday-text' : '')]">
                  {{ day.dayNumOnly }}
                </span>
              </div>
              <span v-if="day.isToday" class="today-sub-pill">TODAY</span>
            </div>

            <!-- Dense Flat Single-Row Activities List (Max 3 visible per day) -->
            <div class="dash-day-activities-list">
              <template v-if="day.events.length > 0">
                <div
                  v-for="ev in day.events.slice(0, 3)"
                  :key="ev.id"
                  :class="['dash-activity-entry', chipTypeClass(ev), isCanceled(ev) ? 'is-canceled-entry' : '']"
                  @click.stop="handleOpenActivityDetails(ev)"
                >
                  <span class="entry-time">{{ formatTimeOnly(ev) }}</span>
                  <span class="entry-type-text">{{ getTypeCode(ev) }}</span>
                  <span class="entry-dot-sep">·</span>
                  <span class="entry-title-text" :title="ev.title">{{ ev.title }}</span>
                </div>

                <!-- + X More Overflow Button -->
                <button
                  v-if="day.events.length > 3"
                  class="dash-overflow-btn"
                  @click.stop="goToCalendarDay(day.date)"
                >
                  + {{ day.events.length - 3 }} more
                </button>
              </template>

              <!-- Empty Day Treatment -->
              <div v-else class="dash-empty-day-space">
                <span class="empty-dash">—</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECONDARY COMPACT MODULE LAUNCHER CARDS GRID -->

      <!-- Administrator Management Cards Grid -->
      <div v-if="activeUser?.role === 'Administrator'" class="module-cards-grid admin-cards-grid">
        
        <!-- Inventory Management Card -->
        <ion-card class="module-card inventory-card" @click="goToAdminInventory">
          <ion-card-header>
            <div class="module-icon-box inventory-icon">
              <ion-icon :icon="cubeOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Inventory Management</ion-card-title>
            <p class="module-desc">Manage equipment records, JRRS targets, and master inventory data.</p>
          </ion-card-header>
        </ion-card>

        <!-- Communications Management Card -->
        <ion-card class="module-card communications-card" @click="goToAdminCommunications">
          <ion-card-header>
            <div class="module-icon-box communications-icon">
              <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Communications Management</ion-card-title>
            <p class="module-desc">Maintain communications records, categories, purposes, and master data.</p>
          </ion-card-header>
        </ion-card>

        <!-- Accomplishments Management Card -->
        <ion-card class="module-card accomplishments-card" @click="goToAdminAccomplishments">
          <ion-card-header>
            <div class="module-icon-box accomplishments-icon">
              <ion-icon :icon="clipboardOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Accomplishments Management</ion-card-title>
            <p class="module-desc">Maintain accomplishment entries, category options, and reporting master data.</p>
          </ion-card-header>
        </ion-card>

        <!-- User Management Card -->
        <ion-card class="module-card admin-card" @click="goToAdminUsers">
          <ion-card-header>
            <div class="module-icon-box admin-icon">
              <ion-icon :icon="peopleOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">User Management</ion-card-title>
            <p class="module-desc">Manage user accounts, system roles, access permissions, and activation states.</p>
          </ion-card-header>
        </ion-card>

      </div>

      <!-- Standard User Operational Cards Grid -->
      <div v-else class="module-cards-grid">

        <!-- Inventory Operational Card -->
        <ion-card class="module-card inventory-card" @click="goToInventory">
          <ion-card-header>
            <div class="module-icon-box inventory-icon">
              <ion-icon :icon="cubeOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Inventory</ion-card-title>
            <p class="module-desc">View equipment readiness, maintenance condition, and inventory reports.</p>
          </ion-card-header>
        </ion-card>

        <!-- Communications Operational Card -->
        <ion-card class="module-card communications-card" @click="goToCommunications">
          <ion-card-header>
            <div class="module-icon-box communications-icon">
              <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Communications</ion-card-title>
            <p class="module-desc">View messages, announcements, and incoming/outgoing communications.</p>
          </ion-card-header>
        </ion-card>

        <!-- Accomplishments Operational Card -->
        <ion-card class="module-card accomplishments-card" @click="goToAccomplishments">
          <ion-card-header>
            <div class="module-icon-box accomplishments-icon">
              <ion-icon :icon="clipboardOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Accomplishments</ion-card-title>
            <p class="module-desc">Record daily accomplishments and generate consolidated reports.</p>
          </ion-card-header>
        </ion-card>

        <!-- Calendar Operational Card -->
        <ion-card class="module-card calendar-card" @click="goToCalendar">
          <ion-card-header>
            <div class="module-icon-box calendar-icon">
              <ion-icon :icon="calendarOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Calendar</ion-card-title>
            <p class="module-desc">View monthly activity calendar, manage events, and browse history.</p>
          </ion-card-header>
        </ion-card>

      </div>

      <!-- Activity Details Modal -->
      <CalendarActivityDetailsModal
        :is-open="isDetailsModalOpen"
        :activity="selectedActivity"
        @close="isDetailsModalOpen = false"
        @updated="loadWeekEvents(toYMD(currentDate))"
        @edit="handleOpenEditModal"
        @delete="handleConfirmDeleteEvent"
      />

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonCard,
  IonCardHeader,
  IonCardTitle,
  IonIcon,
  IonSpinner
} from '@ionic/vue'
import {
  calendarOutline,
  cubeOutline,
  chatbubbleEllipsesOutline,
  clipboardOutline,
  peopleOutline
} from 'ionicons/icons'

import MainLayout from '../layouts/MainLayout.vue'
import CalendarActivityDetailsModal from '../components/calendar/CalendarActivityDetailsModal.vue'

import { activeUser } from '../services/authService'
import { fetchWeekEvents, deleteCalendarEvent } from '../services/calendarService'
import type { CalendarActivity } from '../types/calendar'

import '../assets/styles/pages/dashboard.css'
import '../assets/styles/components/calendar.css'

import { formatDate } from '../utils/dateUtils'

const router = useRouter()
const currentDate = ref(new Date())
const weekLoading = ref(true)
const weekEvents = ref<CalendarActivity[]>([])
const weekStart = ref('')
const weekEnd = ref('')

const isDetailsModalOpen = ref(false)
const selectedActivity = ref<CalendarActivity | null>(null)

const currentDateFormatted = computed(() => {
  return formatDate(new Date().toISOString())
})

const currentDayOfWeek = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long'
  })
})

function toYMD(dt: Date): string {
  const y = dt.getFullYear()
  const m = String(dt.getMonth() + 1).padStart(2, '0')
  const d = String(dt.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

interface WeekDay {
  date: string
  dayName: string
  dayNumOnly: number
  isToday: boolean
  isSunday: boolean
  events: CalendarActivity[]
}

const weekDays = computed<WeekDay[]>(() => {
  if (!weekStart.value) return []
  const todayStr = toYMD(new Date())
  const days: WeekDay[] = []
  const start = new Date(weekStart.value + 'T00:00:00')

  for (let i = 0; i < 7; i++) {
    const dt = new Date(start)
    dt.setDate(start.getDate() + i)
    const dateStr = toYMD(dt)
    days.push({
      date: dateStr,
      dayName: dt.toLocaleDateString('en-US', { weekday: 'short' }),
      dayNumOnly: dt.getDate(),
      isToday: dateStr === todayStr,
      isSunday: i === 0 || dt.getDay() === 0,
      events: weekEvents.value.filter(e => e.date === dateStr)
    })
  }
  return days
})

const totalScheduledCount = computed(() => {
  return weekEvents.value.length
})

function formatWeekRange(start: string, end: string): string {
  if (!start || !end) return ''
  const s = new Date(start + 'T00:00:00')
  const e = new Date(end + 'T00:00:00')
  const sMonth = s.toLocaleDateString('en-US', { month: 'short' })
  const eMonth = e.toLocaleDateString('en-US', { month: 'short' })
  if (sMonth === eMonth) {
    return `${sMonth} ${s.getDate()}–${e.getDate()}, ${s.getFullYear()}`
  }
  return `${sMonth} ${s.getDate()} – ${eMonth} ${e.getDate()}, ${e.getFullYear()}`
}

async function loadWeekEvents(targetDateStr?: string) {
  weekLoading.value = true
  const queryDate = targetDateStr || toYMD(currentDate.value)
  const result = await fetchWeekEvents(queryDate)
  weekEvents.value = result.events || []
  weekStart.value = result.weekStart
  weekEnd.value = result.weekEnd
  weekLoading.value = false
}

function handlePrevWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() - 7)
  currentDate.value = d
  loadWeekEvents(toYMD(d))
}

function handleNextWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() + 7)
  currentDate.value = d
  loadWeekEvents(toYMD(d))
}

function handleGoToToday() {
  currentDate.value = new Date()
  loadWeekEvents(toYMD(new Date()))
}

function getTypeCode(act: CalendarActivity): string {
  return act.category_code || act.event_type_code || 'CONF'
}

function chipTypeClass(act: CalendarActivity): string {
  return `chip-type-${getTypeCode(act)}`
}

function formatTimeOnly(act: CalendarActivity): string {
  if (!act.all_day && act.start_datetime) {
    try {
      const formatted = act.start_datetime.replace(' ', 'T')
      const dt = new Date(formatted)
      if (!isNaN(dt.getTime())) {
        const h = String(dt.getHours()).padStart(2, '0')
        const m = String(dt.getMinutes()).padStart(2, '0')
        return `${h}:${m}`
      }
    } catch (e) {}
  }
  return 'All Day'
}

function isCanceled(act: CalendarActivity): boolean {
  if (!act.status) return false
  return act.status.toLowerCase().includes('canceled') || act.status.toLowerCase().includes('cancelled')
}

function handleOpenActivityDetails(act: CalendarActivity) {
  selectedActivity.value = act
  isDetailsModalOpen.value = true
}

function handleOpenEditModal(act: CalendarActivity) {
  isDetailsModalOpen.value = false
  router.push('/calendar')
}

async function handleConfirmDeleteEvent(act: CalendarActivity) {
  if (confirm(`Are you sure you want to delete "${act.title}"?`)) {
    const res = await deleteCalendarEvent(act.source_id)
    if (res.success) {
      isDetailsModalOpen.value = false
      await loadWeekEvents(toYMD(currentDate.value))
    } else {
      alert(res.message || 'Failed to delete activity.')
    }
  }
}

// Navigation Routes
function goToInventory() {
  router.push('/inventory')
}

function goToCommunications() {
  router.push('/communications')
}

function goToAccomplishments() {
  router.push('/accomplishments')
}

function goToCalendar() {
  router.push('/calendar')
}

function goToCalendarDay(date: string) {
  router.push(`/calendar?view=day&date=${date}`)
}

function goToAdminInventory() {
  router.push('/administrator/inventory')
}

function goToAdminCommunications() {
  router.push('/administrator/communications')
}

function goToAdminAccomplishments() {
  router.push('/administrator/accomplishments')
}

function goToAdminUsers() {
  router.push('/administrator/users')
}

onMounted(() => {
  loadWeekEvents()
})
</script>

<style scoped>
.dashboard-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.welcome-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
  padding: 16px 20px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.welcome-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.welcome-subtitle {
  font-size: 0.85rem;
  color: #64748b;
  margin: 3px 0 0 0;
}

.today-date-badge {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 8px 14px;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.today-icon-box {
  width: 34px;
  height: 34px;
  border-radius: 6px;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
}

.today-text-group {
  display: flex;
  flex-direction: column;
}

.today-date {
  font-size: 0.875rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.2;
}

.today-day {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  line-height: 1.2;
  margin-top: 1px;
}

/* Dense Executive Operational Schedule Widget */
.dash-schedule-widget {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  box-shadow: 0 2px 10px -2px rgba(15, 23, 42, 0.05);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.dash-schedule-header {
  padding: 12px 18px;
  border-bottom: 1px solid #cbd5e1;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}

.header-title-block {
  display: flex;
  flex-direction: column;
}

.eyebrow-label {
  font-size: 0.65rem;
  font-weight: 800;
  color: #64748b;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.primary-schedule-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  margin: 1px 0 0 0;
  letter-spacing: -0.01em;
}

.summary-subtitle {
  font-size: 0.775rem;
  color: #64748b;
  margin: 2px 0 0 0;
}

.count-accent {
  color: #2563eb;
  font-weight: 700;
}

.header-control-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.stepper-controls {
  display: flex;
  align-items: center;
  gap: 3px;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  padding: 2px 5px;
  border-radius: 6px;
}

.stepper-btn {
  background: transparent;
  border: none;
  color: #475569;
  font-size: 1.05rem;
  font-weight: 700;
  cursor: pointer;
  padding: 1px 6px;
  border-radius: 4px;
  transition: background 0.15s ease, color 0.15s ease;
  line-height: 1;
}

.stepper-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.btn-today-pill {
  background: #ffffff;
  color: #2563eb;
  border: 1px solid #cbd5e1;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.725rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-today-pill:hover {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #1d4ed8;
}

.control-divider {
  color: #cbd5e1;
  font-size: 0.8rem;
}

.link-view-calendar {
  font-size: 0.8rem;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
  transition: color 0.15s ease;
}

.link-view-calendar:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.dash-schedule-loading {
  text-align: center;
  padding: 30px;
}

/* 7-Column Compact Single Row Grid Layout */
.dash-schedule-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  min-height: 105px;
  width: 100%;
  box-sizing: border-box;
}

.dash-day-column {
  background: #ffffff;
  border-right: 1px solid #cbd5e1;
  padding: 6px 6px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-height: 105px;
  box-sizing: border-box;
  cursor: pointer;
  transition: background 0.15s ease;
  min-width: 0;
}

.dash-day-column:last-child {
  border-right: none;
}

.dash-day-column:hover {
  background: #f8fafc;
}

/* Compact Header (FRI / 28) */
.dash-day-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding-bottom: 4px;
  border-bottom: 1px solid #f1f5f9;
}

.dash-weekday-name {
  font-size: 0.675rem;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.04em;
}

.dash-date-num-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  margin-top: 1px;
}

.today-highlight {
  background: #2563eb;
  border-radius: 6px;
}

.dash-date-number {
  font-size: 0.95rem;
  font-weight: 800;
  color: #0f172a;
}

.today-date-text {
  color: #ffffff !important;
}

.is-sunday-text {
  color: #dc2626 !important;
}

.today-sub-pill {
  font-size: 0.55rem;
  font-weight: 800;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  padding: 0px 4px;
  border-radius: 3px;
  letter-spacing: 0.04em;
  margin-top: 1px;
}

/* Flat Dense Single-Row Activity Entries */
.dash-day-activities-list {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}

.dash-activity-entry {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 5px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  border-left-width: 3px;
  background: #ffffff;
  cursor: pointer;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  min-width: 0;
  height: 24px;
  box-sizing: border-box;
}

.dash-activity-entry:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.dash-activity-entry.is-canceled-entry {
  opacity: 0.6;
  text-decoration: line-through;
}

.entry-time {
  font-size: 0.65rem;
  font-weight: 700;
  color: #475569;
  flex-shrink: 0;
}

.entry-type-text {
  font-size: 0.625rem;
  font-weight: 800;
  color: #475569;
  flex-shrink: 0;
  text-transform: uppercase;
}

.entry-dot-sep {
  font-size: 0.65rem;
  color: #94a3b8;
  flex-shrink: 0;
}

.entry-title-text {
  font-size: 0.725rem;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
  flex: 1;
}

/* Subtle Accent Left Border Colors */
.chip-type-PAS { background: #f0fdf4; border-left-color: #16a34a; }
.chip-type-CONF { background: #eff6ff; border-left-color: #2563eb; }
.chip-type-VTC { background: #faf5ff; border-left-color: #9333ea; }

.dash-overflow-btn {
  font-size: 0.65rem;
  font-weight: 700;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  padding: 1px 6px;
  border-radius: 8px;
  cursor: pointer;
  align-self: flex-start;
  margin-top: 1px;
  transition: all 0.15s ease;
  line-height: 1.2;
}

.dash-overflow-btn:hover {
  background: #dbeafe;
  color: #1d4ed8;
}

.dash-empty-day-space {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  min-height: 30px;
}

.empty-dash {
  font-size: 0.75rem;
  color: #cbd5e1;
  font-weight: 500;
}

/* Secondary Compact Module Cards Grid */
.module-cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

@media (max-width: 900px) {
  .module-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.module-card {
  margin: 0;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  border: 1px solid #cbd5e1;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  padding: 0;
}

.module-card ion-card-header {
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  text-align: left;
}

.module-icon-box {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 8px;
}

.module-title {
  font-size: 0.925rem;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 3px;
}

.module-desc {
  font-size: 0.75rem;
  color: #64748b;
  margin: 0;
  line-height: 1.35;
}
</style>