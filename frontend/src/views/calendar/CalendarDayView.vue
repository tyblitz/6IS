<template>
  <MainLayout title="Calendar Day">
    <div class="calendar-day-container">

      <!-- Header -->
      <div class="module-header-bar">
        <div>
          <h2>{{ formattedDate }}</h2>
          <p class="subtitle">{{ dayOfWeek }} — All activities and events for this day.</p>
        </div>
        <div class="header-actions">
          <button class="back-btn" @click="goBackToCalendar">
            <ion-icon :icon="arrowBackOutline"></ion-icon>
            <span>Back to Calendar</span>
          </button>
          <button class="add-btn" type="button" @click="openCreateModal">
            <ion-icon :icon="addOutline"></ion-icon>
            <span>Add Event</span>
          </button>
        </div>
      </div>

      <!-- Day Navigation -->
      <div class="day-nav">
        <button class="nav-arrow" @click="prevDay">
          <ion-icon :icon="chevronBackOutline"></ion-icon>
        </button>
        <span class="day-nav-label">{{ formattedDateFull }}</span>
        <button class="nav-arrow" @click="nextDay">
          <ion-icon :icon="chevronForwardOutline"></ion-icon>
        </button>
      </div>

      <!-- Events Timeline -->
      <div v-if="!loading" class="day-timeline">

        <!-- Empty State -->
        <div v-if="events.length === 0" class="empty-day">
          <ion-icon :icon="calendarClearOutline" class="empty-icon"></ion-icon>
          <h3>No Activities</h3>
          <p>No accomplishments, communications, or events recorded for this day.</p>
          <button class="add-btn" @click="openCreateModal">
            <ion-icon :icon="addOutline"></ion-icon>
            <span>Add Event</span>
          </button>
        </div>

        <!-- Grouped Events by Source -->
        <template v-else>
          <!-- Accomplishments -->
          <div v-if="accomplishments.length > 0" class="event-group">
            <div class="group-header">
              <span class="group-dot" style="background: #22c55e;"></span>
              <h3>Accomplishments</h3>
              <span class="group-count">{{ accomplishments.length }}</span>
            </div>
            <div class="event-cards">
              <div
                v-for="ev in accomplishments"
                :key="ev.id"
                class="event-card accomplishment-card"
                @click="navigateToSource(ev)"
              >
                <div class="event-card-indicator" style="background: #22c55e;"></div>
                <div class="event-card-body">
                  <p class="event-card-title">{{ ev.title }}</p>
                  <span class="event-card-meta">{{ ev.category_name || 'General' }}</span>
                </div>
                <ion-icon :icon="chevronForwardOutline" class="event-card-arrow"></ion-icon>
              </div>
            </div>
          </div>

          <!-- Incoming Communications -->
          <div v-if="incomingComms.length > 0" class="event-group">
            <div class="group-header">
              <span class="group-dot" style="background: #3b82f6;"></span>
              <h3>Incoming Communications</h3>
              <span class="group-count">{{ incomingComms.length }}</span>
            </div>
            <div class="event-cards">
              <div
                v-for="ev in incomingComms"
                :key="ev.id"
                class="event-card incoming-card"
                @click="navigateToSource(ev)"
              >
                <div class="event-card-indicator" style="background: #3b82f6;"></div>
                <div class="event-card-body">
                  <p class="event-card-title">{{ ev.title }}</p>
                  <span class="event-card-meta">{{ ev.status || 'Pending' }}</span>
                </div>
                <ion-icon :icon="chevronForwardOutline" class="event-card-arrow"></ion-icon>
              </div>
            </div>
          </div>

          <!-- Outgoing Communications -->
          <div v-if="outgoingComms.length > 0" class="event-group">
            <div class="group-header">
              <span class="group-dot" style="background: #f97316;"></span>
              <h3>Outgoing Communications</h3>
              <span class="group-count">{{ outgoingComms.length }}</span>
            </div>
            <div class="event-cards">
              <div
                v-for="ev in outgoingComms"
                :key="ev.id"
                class="event-card outgoing-card"
                @click="navigateToSource(ev)"
              >
                <div class="event-card-indicator" style="background: #f97316;"></div>
                <div class="event-card-body">
                  <p class="event-card-title">{{ ev.title }}</p>
                  <span class="event-card-meta">{{ ev.status || 'Pending' }}</span>
                </div>
                <ion-icon :icon="chevronForwardOutline" class="event-card-arrow"></ion-icon>
              </div>
            </div>
          </div>

          <!-- Calendar Events -->
          <div v-if="calendarEvents.length > 0" class="event-group">
            <div class="group-header">
              <span class="group-dot" style="background: #a855f7;"></span>
              <h3>Calendar Events</h3>
              <span class="group-count">{{ calendarEvents.length }}</span>
            </div>
            <div class="event-cards">
              <div
                v-for="ev in calendarEvents"
                :key="ev.id"
                class="event-card calendar-event-card"
              >
                <div class="event-card-indicator" style="background: #a855f7;"></div>
                <div class="event-card-body">
                  <p class="event-card-title">{{ ev.title }}</p>
                  <div class="event-card-meta-row">
                    <span v-if="ev.time" class="event-card-time">
                      <ion-icon :icon="timeOutline"></ion-icon>
                      {{ formatTime(ev.time) }}
                    </span>
                    <span class="event-type-badge" :class="'type-' + ev.event_type">{{ eventTypeLabels[ev.event_type] || ev.event_type }}</span>
                    <span class="priority-badge" :class="'priority-' + ev.priority">{{ ev.priority }}</span>
                  </div>
                  <p v-if="ev.description" class="event-card-desc">{{ ev.description }}</p>
                </div>
                <div class="event-card-actions">
                  <button class="icon-btn edit-btn" @click.stop="openEditModal(ev)" title="Edit">
                    <ion-icon :icon="createOutline"></ion-icon>
                  </button>
                  <button class="icon-btn delete-btn" @click.stop="confirmDelete(ev)" title="Delete">
                    <ion-icon :icon="trashOutline"></ion-icon>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Loading -->
      <div v-else class="calendar-loading">
        <ion-spinner name="crescent"></ion-spinner>
        <p>Loading events...</p>
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

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
        <div class="modal-card delete-modal">
          <div class="modal-header">
            <h3>Delete Event</h3>
            <button class="close-btn" @click="showDeleteConfirm = false">&times;</button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete <strong>"{{ deletingEvent?.title }}"</strong>?</p>
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="showDeleteConfirm = false">Cancel</button>
            <button class="btn-delete" @click="handleDelete" :disabled="saving">
              {{ saving ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { IonIcon, IonSpinner } from '@ionic/vue'
import {
  addOutline,
  arrowBackOutline,
  chevronBackOutline,
  chevronForwardOutline,
  calendarClearOutline,
  timeOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { fetchMonthEvents, createCalendarEvent, updateCalendarEvent, deleteCalendarEvent } from '../../services/calendarService'
import type { CalendarEvent, CalendarEventFormPayload } from '../../types/calendar'
import { EVENT_TYPE_LABELS } from '../../types/calendar'

const router = useRouter()
const route = useRoute()
const loading = ref(true)
const saving = ref(false)
const isFormOpen = ref(false)
const showDeleteConfirm = ref(false)
const editingEvent = ref<CalendarEvent | null>(null)
const deletingEvent = ref<CalendarEvent | null>(null)
const events = ref<CalendarEvent[]>([])
const allMonthEvents = ref<CalendarEvent[]>([])
const eventTypeLabels = EVENT_TYPE_LABELS

const currentDate = ref(route.params.date as string || new Date().toISOString().slice(0, 10))

const formattedDate = computed(() => {
  const dt = new Date(currentDate.value + 'T00:00:00')
  return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
})

const formattedDateFull = computed(() => {
  const dt = new Date(currentDate.value + 'T00:00:00')
  return dt.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
})

const dayOfWeek = computed(() => {
  const dt = new Date(currentDate.value + 'T00:00:00')
  return dt.toLocaleDateString('en-US', { weekday: 'long' })
})

// Group events by source
const accomplishments = computed(() => events.value.filter(e => e.source === 'accomplishment'))
const incomingComms = computed(() => events.value.filter(e => e.source === 'incoming_comm'))
const outgoingComms = computed(() => events.value.filter(e => e.source === 'outgoing_comm'))
const calendarEvents = computed(() => events.value.filter(e => e.source === 'calendar_event'))

const defaultForm = (): CalendarEventFormPayload => ({
  title: '',
  description: '',
  event_date: currentDate.value,
  event_time: '',
  event_type: 'other',
  priority: 'normal'
})

const formData = ref<CalendarEventFormPayload>(defaultForm())

function formatTime(timeStr: string): string {
  if (!timeStr) return ''
  const [h, m] = timeStr.split(':')
  const hour = parseInt(h)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const h12 = hour % 12 || 12
  return `${h12}:${m} ${ampm}`
}

async function loadEvents() {
  loading.value = true
  const dt = new Date(currentDate.value + 'T00:00:00')
  allMonthEvents.value = await fetchMonthEvents(dt.getFullYear(), dt.getMonth() + 1)
  events.value = allMonthEvents.value.filter(e => e.date === currentDate.value)
  loading.value = false
}

function prevDay() {
  const dt = new Date(currentDate.value + 'T00:00:00')
  dt.setDate(dt.getDate() - 1)
  const newDate = dt.toISOString().slice(0, 10)
  router.replace(`/calendar/day/${newDate}`)
}

function nextDay() {
  const dt = new Date(currentDate.value + 'T00:00:00')
  dt.setDate(dt.getDate() + 1)
  const newDate = dt.toISOString().slice(0, 10)
  router.replace(`/calendar/day/${newDate}`)
}

function goBackToCalendar() {
  router.push('/calendar')
}

function navigateToSource(ev: CalendarEvent) {
  if (ev.source === 'accomplishment') {
    router.push(`/accomplishments/detail/${ev.source_id}`)
  } else if (ev.source === 'incoming_comm' || ev.source === 'outgoing_comm') {
    router.push(`/communications/detail/${ev.source_id}`)
  }
}

function openCreateModal() {
  editingEvent.value = null
  formData.value = defaultForm()
  isFormOpen.value = true
}

function openEditModal(ev: CalendarEvent) {
  editingEvent.value = ev
  formData.value = {
    id: ev.source_id,
    title: ev.title,
    description: ev.description || '',
    event_date: ev.date,
    event_time: ev.time || '',
    event_type: ev.event_type as any,
    priority: ev.priority
  }
  isFormOpen.value = true
}

function confirmDelete(ev: CalendarEvent) {
  deletingEvent.value = ev
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!deletingEvent.value) return
  saving.value = true
  const result = await deleteCalendarEvent(deletingEvent.value.source_id)
  if (result.success) {
    showDeleteConfirm.value = false
    deletingEvent.value = null
    await loadEvents()
  }
  saving.value = false
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

// Watch for route param changes (prev/next day navigation)
watch(() => route.params.date, (newDate) => {
  if (newDate && typeof newDate === 'string') {
    currentDate.value = newDate
    // Check if still same month — if so, filter from cache
    const cached = allMonthEvents.value
    if (cached.length > 0) {
      const cachedMonth = cached[0]?.date?.slice(0, 7)
      const newMonth = newDate.slice(0, 7)
      if (cachedMonth === newMonth) {
        events.value = cached.filter(e => e.date === newDate)
        return
      }
    }
    loadEvents()
  }
})

onMounted(() => {
  loadEvents()
})
</script>

<style scoped>
.calendar-day-container {
  padding: 0;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.back-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.back-btn:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
}

/* Day Navigation */
.day-nav {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
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

.day-nav-label {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

/* Event Groups */
.day-timeline {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.event-group {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.group-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.group-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.group-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  flex: 1;
}

.group-count {
  background: #6366f1;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 12px;
}

/* Event Cards */
.event-cards {
  display: flex;
  flex-direction: column;
}

.event-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.15s;
}
.event-card:last-child { border-bottom: none; }
.event-card:hover { background: #f8fafc; }

.calendar-event-card {
  cursor: default;
  align-items: flex-start;
}

.event-card-indicator {
  width: 4px;
  min-height: 36px;
  border-radius: 2px;
  flex-shrink: 0;
}

.event-card-body {
  flex: 1;
  min-width: 0;
}

.event-card-title {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 4px 0;
}

.event-card-meta {
  font-size: 12px;
  color: #64748b;
}

.event-card-meta-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.event-card-time {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #6366f1;
  font-weight: 600;
}
.event-card-time ion-icon {
  font-size: 14px;
}

.event-type-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.type-meeting { background: #dbeafe; color: #1e40af; }
.type-deadline { background: #fee2e2; color: #991b1b; }
.type-reminder { background: #fef3c7; color: #92400e; }
.type-other { background: #f1f5f9; color: #475569; }

.priority-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: capitalize;
}
.priority-low { background: #f1f5f9; color: #64748b; }
.priority-normal { background: #dbeafe; color: #1e40af; }
.priority-high { background: #fee2e2; color: #dc2626; }

.event-card-desc {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0;
  line-height: 1.5;
}

.event-card-arrow {
  color: #94a3b8;
  font-size: 16px;
  flex-shrink: 0;
}

.event-card-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #94a3b8;
  font-size: 16px;
  transition: all 0.2s;
}
.icon-btn.edit-btn:hover { background: #eff6ff; color: #3b82f6; }
.icon-btn.delete-btn:hover { background: #fef2f2; color: #ef4444; }

/* Empty State */
.empty-day {
  text-align: center;
  padding: 60px 20px;
  color: #94a3b8;
}

.empty-icon {
  font-size: 48px;
  color: #cbd5e1;
  margin-bottom: 16px;
}

.empty-day h3 {
  font-size: 18px;
  font-weight: 700;
  color: #64748b;
  margin: 0 0 8px;
}

.empty-day p {
  font-size: 14px;
  color: #94a3b8;
  margin: 0 0 20px;
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

.delete-modal {
  max-width: 420px;
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

.modal-body p {
  font-size: 14px;
  color: #475569;
  margin: 0;
  line-height: 1.5;
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

.btn-delete {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  background: #ef4444;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-delete:hover { background: #dc2626; }
.btn-delete:disabled { opacity: 0.6; cursor: not-allowed; }

@media (max-width: 768px) {
  .header-actions {
    flex-direction: column;
  }
  .form-row-2col {
    grid-template-columns: 1fr;
  }
}
</style>
