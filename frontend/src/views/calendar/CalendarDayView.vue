<template>
  <MainLayout title="Calendar Day">
    <div class="calendar-day-container">

      <!-- Header -->
      <div class="module-header-bar">
        <div>
          <h2>{{ formattedDate }}</h2>
          <p class="subtitle">{{ dayOfWeek }} — All activities for this day.</p>
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
          <p>No activities recorded for this day.</p>
          <button class="add-btn" @click="openCreateModal">
            <ion-icon :icon="addOutline"></ion-icon>
            <span>Add Event</span>
          </button>
        </div>

        <template v-else>
          <div class="event-group">
            <div class="group-header">
              <span class="group-dot" style="background: #9333ea;"></span>
              <h3>Calendar Activities</h3>
              <span class="group-count">{{ events.length }}</span>
            </div>
            <div class="event-cards">
              <div
                v-for="ev in events"
                :key="ev.id"
                class="event-card card-calendar_event"
                @click="openEventDetail(ev)"
              >
                <div class="event-card-body">
                  <div class="event-card-top">
                    <span class="event-title">{{ ev.title }}</span>
                  </div>
                  <div class="event-card-meta">
                    <span v-if="ev.time" class="meta-time">
                      <ion-icon :icon="timeOutline"></ion-icon>
                      {{ formatTime(ev.time) }}
                    </span>
                    <span class="event-type-badge type-meeting">{{ ev.category_code || 'CONF' }}</span>
                  </div>
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
          </div>
          <div class="modal-footer">
            <button class="btn-cancel" @click="isFormOpen = false">Cancel</button>
            <button class="btn-save" :disabled="saving" @click="saveEvent">
              {{ saving ? 'Saving...' : (editingEvent ? 'Update' : 'Create') }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonIcon, IonSpinner } from '@ionic/vue'
import {
  arrowBackOutline,
  addOutline,
  chevronBackOutline,
  chevronForwardOutline,
  timeOutline,
  createOutline,
  trashOutline,
  calendarClearOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { CalendarActivity, CalendarEventFormPayload } from '../../types/calendar'
import {
  fetchCalendarActivities,
  createCalendarEvent,
  updateCalendarEvent,
  deleteCalendarEvent
} from '../../services/calendarService'

const route = useRoute()
const router = useRouter()

const dateParam = ref<string>((route.params.date as string) || new Date().toISOString().slice(0, 10))
const events = ref<CalendarActivity[]>([])
const loading = ref(true)
const saving = ref(false)

const isFormOpen = ref(false)
const editingEvent = ref<CalendarActivity | null>(null)

const formData = ref({
  id: undefined as number | undefined,
  title: '',
  event_date: dateParam.value,
  event_time: '09:00',
  event_type_id: 1,
  office_id: 1
})

const formattedDate = computed(() => {
  try {
    const dt = new Date(dateParam.value + 'T00:00:00')
    return dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
  } catch {
    return dateParam.value
  }
})

const dayOfWeek = computed(() => {
  try {
    const dt = new Date(dateParam.value + 'T00:00:00')
    return dt.toLocaleDateString('en-US', { weekday: 'long' })
  } catch {
    return ''
  }
})

const formattedDateFull = computed(() => {
  return `${dayOfWeek.value}, ${formattedDate.value}`
})

async function loadDayEvents() {
  loading.value = true
  try {
    events.value = await fetchCalendarActivities(dateParam.value, dateParam.value)
  } catch (err) {
    console.error('Failed loading day events:', err)
  } finally {
    loading.value = false
  }
}

function prevDay() {
  const dt = new Date(dateParam.value + 'T00:00:00')
  dt.setDate(dt.getDate() - 1)
  dateParam.value = dt.toISOString().slice(0, 10)
  router.replace({ name: 'CalendarDay', params: { date: dateParam.value } })
}

function nextDay() {
  const dt = new Date(dateParam.value + 'T00:00:00')
  dt.setDate(dt.getDate() + 1)
  dateParam.value = dt.toISOString().slice(0, 10)
  router.replace({ name: 'CalendarDay', params: { date: dateParam.value } })
}

function goBackToCalendar() {
  router.push('/calendar')
}

function formatTime(t?: string | null) {
  if (!t) return ''
  return t.slice(0, 5)
}

function openCreateModal() {
  editingEvent.value = null
  formData.value = {
    id: undefined,
    title: '',
    event_date: dateParam.value,
    event_time: '09:00',
    event_type_id: 1,
    office_id: 1
  }
  isFormOpen.value = true
}

function openEditModal(ev: CalendarActivity) {
  editingEvent.value = ev
  formData.value = {
    id: ev.source_id,
    title: ev.title,
    event_date: ev.date,
    event_time: ev.time || '09:00',
    event_type_id: ev.event_type_id || 1,
    office_id: ev.office_id || 1
  }
  isFormOpen.value = true
}

function openEventDetail(ev: CalendarActivity) {
  router.push('/calendar')
}

async function saveEvent() {
  if (!formData.value.title.trim()) {
    alert('Please enter a title.')
    return
  }

  saving.value = true
  try {
    const payload: CalendarEventFormPayload = {
      id: formData.value.id,
      title: formData.value.title.trim(),
      event_date: formData.value.event_date,
      event_time: formData.value.event_time ? formData.value.event_time + ':00' : '09:00:00',
      event_type_id: formData.value.event_type_id,
      office_id: formData.value.office_id
    }

    let res
    if (formData.value.id) {
      res = await updateCalendarEvent(payload)
    } else {
      res = await createCalendarEvent(payload)
    }

    if (res && res.success) {
      isFormOpen.value = false
      await loadDayEvents()
    } else {
      alert(res?.message || 'Failed to save event.')
    }
  } catch (err) {
    console.error('Error saving event:', err)
  } finally {
    saving.value = false
  }
}

async function confirmDelete(ev: CalendarActivity) {
  if (!confirm(`Are you sure you want to delete "${ev.title}"?`)) return
  try {
    const res = await deleteCalendarEvent(ev.source_id)
    if (res && res.success) {
      await loadDayEvents()
    } else {
      alert(res?.message || 'Failed to delete.')
    }
  } catch (err) {
    console.error('Error deleting event:', err)
  }
}

watch(() => route.params.date, (newDate) => {
  if (newDate && typeof newDate === 'string') {
    dateParam.value = newDate
    loadDayEvents()
  }
})

onMounted(() => {
  loadDayEvents()
})
</script>
