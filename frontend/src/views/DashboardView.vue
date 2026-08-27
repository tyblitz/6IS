<template>
  <MainLayout title="Home">
    <div class="dashboard-page">
      
      <!-- HERO GREETING SECTION -->

      <!-- Administrator Dashboard Hero -->
      <div v-if="activeUser?.role === 'Administrator'" class="dashboard-hero admin-hero">
        <div class="greeting-box">
          <div class="role-badge-pill admin-pill">
            <ion-icon :icon="shieldCheckmarkOutline" />
            <span>Administrator Control Center</span>
          </div>
          <h1 class="greeting-title">Welcome back, {{ activeUser?.username }}! 👋</h1>
          <p class="greeting-subtitle">System administration, master data maintenance, and user access management.</p>
        </div>

        <div class="date-widget-card">
          <div class="date-icon-box">
            <ion-icon :icon="calendarOutline"></ion-icon>
          </div>
          <div class="date-info">
            <span class="date-string">{{ currentDateFormatted }}</span>
            <span class="day-string">{{ currentDayOfWeek }}</span>
          </div>
        </div>
      </div>

      <!-- User Operational Dashboard Hero -->
      <div v-else class="dashboard-hero">
        <div class="greeting-box">
          <h1 class="greeting-title">Welcome back, {{ activeUser?.username || 'User' }}! 👋</h1>
          <p class="greeting-subtitle">Welcome back to 6IS. Please select an operational module to get started.</p>
        </div>

        <div class="date-widget-card">
          <div class="date-icon-box">
            <ion-icon :icon="calendarOutline"></ion-icon>
          </div>
          <div class="date-info">
            <span class="date-string">{{ currentDateFormatted }}</span>
            <span class="day-string">{{ currentDayOfWeek }}</span>
          </div>
        </div>
      </div>

      <!-- THIS WEEK'S ACTIVITIES WIDGET -->
      <div class="week-widget">
        <div class="week-widget-header">
          <div class="week-widget-title-row">
            <ion-icon :icon="calendarOutline" class="week-widget-icon"></ion-icon>
            <div>
              <h3 class="week-widget-title">This Week's Activities</h3>
              <p class="week-widget-range" v-if="weekStart && weekEnd">{{ formatWeekRange(weekStart, weekEnd) }}</p>
            </div>
          </div>
          <router-link to="/calendar" class="view-calendar-link">
            View Full Calendar
            <ion-icon :icon="arrowForwardOutline"></ion-icon>
          </router-link>
        </div>

        <div v-if="weekLoading" class="week-loading">
          <ion-spinner name="dots"></ion-spinner>
        </div>

        <div v-else class="week-days-list">
          <div
            v-for="day in weekDays"
            :key="day.date"
            class="week-day-row"
            :class="{ 'is-today': day.isToday, 'has-events': day.events.length > 0 }"
            @click="goToCalendarDay(day.date)"
          >
            <div class="week-day-label">
              <span class="week-day-name">{{ day.dayName }}</span>
              <span class="week-day-number">{{ day.dayNumber }}</span>
            </div>

            <div class="week-day-events" v-if="day.events.length > 0">
              <div
                v-for="ev in day.events.slice(0, 3)"
                :key="ev.id"
                class="week-event-chip"
                :style="{ borderLeftColor: sourceColors[ev.source] }"
              >
                <span class="week-event-dot" :style="{ background: sourceColors[ev.source] }"></span>
                <span class="week-event-text">{{ ev.title }}</span>
              </div>
              <span v-if="day.events.length > 3" class="week-event-more">+{{ day.events.length - 3 }} more</span>
            </div>
            <div v-else class="week-day-empty">
              <span>No activities</span>
            </div>

            <div v-if="day.isToday" class="today-indicator">Today</div>
          </div>
        </div>
      </div>

      <!-- MODULE LAUNCHER CARDS GRID -->

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
  shieldCheckmarkOutline,
  peopleOutline,
  arrowForwardOutline
} from 'ionicons/icons'

import MainLayout from '../layouts/MainLayout.vue'
import { activeUser } from '../services/authService'
import { fetchWeekEvents } from '../services/calendarService'
import { SOURCE_COLORS } from '../types/calendar'
import type { CalendarEvent, CalendarEventSource } from '../types/calendar'
import '../assets/styles/pages/dashboard.css'

import { formatDate } from '../utils/dateUtils'

const router = useRouter()
const weekLoading = ref(true)
const weekEvents = ref<CalendarEvent[]>([])
const weekStart = ref('')
const weekEnd = ref('')

const sourceColors = SOURCE_COLORS

const currentDateFormatted = computed(() => {
  return formatDate(new Date().toISOString())
})

const currentDayOfWeek = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long'
  })
})

interface WeekDay {
  date: string
  dayName: string
  dayNumber: string
  isToday: boolean
  events: CalendarEvent[]
}

const weekDays = computed<WeekDay[]>(() => {
  if (!weekStart.value) return []
  const todayStr = new Date().toISOString().slice(0, 10)
  const days: WeekDay[] = []
  const start = new Date(weekStart.value + 'T00:00:00')

  for (let i = 0; i < 7; i++) {
    const dt = new Date(start)
    dt.setDate(start.getDate() + i)
    const dateStr = dt.toISOString().slice(0, 10)
    days.push({
      date: dateStr,
      dayName: dt.toLocaleDateString('en-US', { weekday: 'short' }),
      dayNumber: dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      isToday: dateStr === todayStr,
      events: weekEvents.value.filter(e => e.date === dateStr)
    })
  }
  return days
})

function formatWeekRange(start: string, end: string): string {
  const s = new Date(start + 'T00:00:00')
  const e = new Date(end + 'T00:00:00')
  const sMonth = s.toLocaleDateString('en-US', { month: 'short' })
  const eMonth = e.toLocaleDateString('en-US', { month: 'short' })
  if (sMonth === eMonth) {
    return `${sMonth} ${s.getDate()} – ${e.getDate()}, ${s.getFullYear()}`
  }
  return `${sMonth} ${s.getDate()} – ${eMonth} ${e.getDate()}, ${e.getFullYear()}`
}

async function loadWeekEvents() {
  weekLoading.value = true
  const todayStr = new Date().toISOString().slice(0, 10)
  const result = await fetchWeekEvents(todayStr)
  weekEvents.value = result.events
  weekStart.value = result.weekStart
  weekEnd.value = result.weekEnd
  weekLoading.value = false
}

// Operational Routes (Normal User)
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
  router.push(`/calendar/day/${date}`)
}

// Administrative Management Routes (Administrator Only)
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
.role-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 8px;
}

.admin-pill {
  background: #f3e8ff;
  color: #7e22ce;
  border: 1px solid #e9d5ff;
}

.admin-cards-grid {
  grid-template-columns: repeat(2, 1fr);
}

/* Calendar Card */
.calendar-icon {
  background: #f5f3ff;
  color: #7c3aed;
}

.calendar-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(124, 58, 237, 0.1);
  border-bottom-color: #7c3aed;
}

/* Week Widget */
.week-widget {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
  margin-bottom: 32px;
  overflow: hidden;
}

.week-widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.week-widget-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.week-widget-icon {
  font-size: 24px;
  color: #6366f1;
}

.week-widget-title {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.week-widget-range {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.view-calendar-link {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #6366f1;
  text-decoration: none;
  transition: color 0.2s;
}
.view-calendar-link:hover {
  color: #4f46e5;
}
.view-calendar-link ion-icon {
  font-size: 16px;
}

.week-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  color: #94a3b8;
}

.week-days-list {
  display: flex;
  flex-direction: column;
}

.week-day-row {
  display: flex;
  align-items: center;
  padding: 12px 24px;
  border-bottom: 1px solid #f8fafc;
  cursor: pointer;
  transition: background 0.15s;
  gap: 16px;
  position: relative;
}
.week-day-row:last-child { border-bottom: none; }
.week-day-row:hover { background: #f8fafc; }

.week-day-row.is-today {
  background: #eff6ff;
  border-left: 3px solid #6366f1;
}

.week-day-row.is-today:hover {
  background: #e0e7ff;
}

.week-day-label {
  display: flex;
  flex-direction: column;
  min-width: 75px;
  flex-shrink: 0;
}

.week-day-name {
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
}

.week-day-number {
  font-size: 12px;
  color: #64748b;
}

.week-day-events {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.week-event-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: #f8fafc;
  border-radius: 6px;
  border-left: 3px solid #94a3b8;
  max-width: 260px;
}

.week-event-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.week-event-text {
  font-size: 12px;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.week-event-more {
  font-size: 11px;
  color: #6366f1;
  font-weight: 600;
  padding: 4px 8px;
}

.week-day-empty span {
  font-size: 12px;
  color: #cbd5e1;
  font-style: italic;
}

.today-indicator {
  position: absolute;
  right: 24px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 10px;
  font-weight: 700;
  color: #6366f1;
  background: #e0e7ff;
  padding: 2px 10px;
  border-radius: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

@media (max-width: 768px) {
  .admin-cards-grid {
    grid-template-columns: 1fr;
  }

  .week-widget-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .week-day-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    padding: 14px 20px;
  }

  .week-day-label {
    flex-direction: row;
    gap: 8px;
  }

  .today-indicator {
    position: static;
    transform: none;
    margin-top: 4px;
  }

  .module-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>