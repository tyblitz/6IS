<!-- frontend/src/components/calendar/CalendarRescheduleModal.vue -->
<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-container">
      <div class="modal-header">
        <h3 class="modal-title">Reschedule Activity</h3>
        <button class="btn-close" type="button" @click="$emit('close')">
          <ion-icon :icon="closeOutline" />
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="modal-body">
        <div class="activity-summary-banner">
          <span class="banner-label">Current Event:</span>
          <strong>{{ eventTitle }}</strong>
          <div class="banner-sub">Currently: {{ currentScheduleDisplay }}</div>
        </div>

        <div class="form-grid">
          <!-- Row 1: Start Date & End Date side-by-side -->
          <div class="form-group">
            <label class="form-label">New Start Date <span class="req">*</span></label>
            <div class="date-picker-wrapper">
              <input
                type="text"
                :value="formatDateMilitary(startDate)"
                readonly
                class="form-input date-display-input"
              />
              <input
                type="date"
                v-model="startDate"
                required
                class="overlay-date-input"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">New End Date <span class="req">*</span></label>
            <div class="date-picker-wrapper">
              <input
                type="text"
                :value="formatDateMilitary(endDate)"
                readonly
                class="form-input date-display-input"
              />
              <input
                type="date"
                v-model="endDate"
                required
                class="overlay-date-input"
              />
            </div>
          </div>

          <!-- Row 2: Start Time & End Time side-by-side (Military Format e.g. 1300H) -->
          <div class="form-group">
            <label class="form-label">New Start Time <span class="req">*</span></label>
            <select v-model="startTime" required class="form-input filter-select">
              <option v-for="t in militaryTimeOptions" :key="t.val" :value="t.val">
                {{ t.label }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">New End Time <span class="req">*</span></label>
            <select v-model="endTime" required class="form-input filter-select">
              <option v-for="t in militaryTimeOptions" :key="t.val" :value="t.val">
                {{ t.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Rescheduling Reason / Justification</label>
          <textarea
            v-model="reason"
            rows="3"
            placeholder="Specify reason for moving date/time (e.g. Officer unavailable, venue conflict)..."
            class="form-textarea"
          ></textarea>
        </div>

        <div v-if="errorMessage" class="error-banner">
          {{ errorMessage }}
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-cancel" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn-submit" :disabled="isSubmitting">
            <span v-if="isSubmitting">Saving...</span>
            <span v-else>Confirm Reschedule</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { IonIcon } from '@ionic/vue'
import { closeOutline } from 'ionicons/icons'
import type { CalendarActivity } from '../../types/calendar'
import { rescheduleEvent } from '../../services/calendarService'
import { formatDate } from '../../utils/dateUtils'

const props = defineProps<{
  isOpen: boolean;
  activity: CalendarActivity | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'rescheduled'): void;
}>()

const eventTitle = ref('')
const startDate = ref('')
const startTime = ref('09:00')
const endDate = ref('')
const endTime = ref('10:00')
const reason = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')

const militaryTimeOptions = Array.from({ length: 48 }, (_, i) => {
  const hour = Math.floor(i / 2)
  const min = (i % 2) * 30
  const hStr = String(hour).padStart(2, '0')
  const mStr = String(min).padStart(2, '0')
  return {
    val: `${hStr}:${mStr}`,
    label: `${hStr}${mStr}H`
  }
})

function formatDateMilitary(ymdStr: string): string {
  return formatDate(ymdStr)
}

const currentScheduleDisplay = computed(() => {
  const act = props.activity
  if (!act) return ''

  if (!act.date) return ''
  try {
    const dt = new Date(act.date + 'T00:00:00')
    const day = dt.getDate()
    const month = dt.toLocaleDateString('en-US', { month: 'short' })
    const year = dt.getFullYear()

    if (act.all_day) {
      return `${day} ${month} ${year} (All Day)`
    }

    let timeRangeStr = ''
    if (act.start_datetime) {
      const startDt = new Date(act.start_datetime)
      const startH = String(startDt.getHours()).padStart(2, '0')
      const startM = String(startDt.getMinutes()).padStart(2, '0')
      timeRangeStr = `${startH}${startM}H`

      if (act.end_datetime) {
        const endDt = new Date(act.end_datetime)
        const endH = String(endDt.getHours()).padStart(2, '0')
        const endM = String(endDt.getMinutes()).padStart(2, '0')
        timeRangeStr += ` - ${endH}${endM}H`
      }
    } else if (act.time) {
      const parts = act.time.split(':')
      if (parts.length >= 2) {
        timeRangeStr = `${parts[0]}${parts[1]}H`
      }
    }

    if (timeRangeStr) {
      return `${day} ${timeRangeStr} ${month} ${year}`
    } else {
      return `${day} ${month} ${year}`
    }
  } catch (e) {
    return act.date
  }
})

watch(
  () => props.activity,
  (act) => {
    if (act) {
      eventTitle.value = act.title

      const startDt = new Date(act.start_datetime || act.date)
      startDate.value = startDt.toISOString().slice(0, 10)
      startTime.value = startDt.toTimeString().slice(0, 5) || '09:00'

      const endDt = act.end_datetime ? new Date(act.end_datetime) : new Date(startDt.getTime() + 3600000)
      endDate.value = endDt.toISOString().slice(0, 10)
      endTime.value = endDt.toTimeString().slice(0, 5) || '10:00'

      reason.value = ''
      errorMessage.value = ''
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  if (!props.activity) return

  const newStart = `${startDate.value} ${startTime.value}:00`
  const newEnd = `${endDate.value} ${endTime.value}:00`

  if (new Date(newEnd) <= new Date(newStart)) {
    errorMessage.value = 'End date/time must be strictly after start date/time.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    const res = await rescheduleEvent({
      id: props.activity.source_id,
      new_start_datetime: newStart,
      new_end_datetime: newEnd,
      reason: reason.value
    })

    if (res.success) {
      emit('rescheduled')
      emit('close')
    } else {
      errorMessage.value = res.message || 'Failed to reschedule event.'
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'An error occurred during rescheduling.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-container {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.modal-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 20px;
  color: #64748b;
  cursor: pointer;
  display: flex;
}

.modal-body {
  padding: 20px;
}

.activity-summary-banner {
  background: #eff6ff;
  border-left: 4px solid #3b82f6;
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 16px;
}

.banner-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: #1d4ed8;
  font-weight: 700;
  display: block;
}

.banner-sub {
  font-size: 0.825rem;
  color: #334155;
  font-weight: 600;
  margin-top: 4px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}

.form-group {
  margin-bottom: 4px;
}

.form-label {
  display: block;
  font-size: 0.825rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 4px;
}

.req {
  color: #dc2626;
}

.date-picker-wrapper {
  position: relative;
  width: 100%;
}

.date-display-input {
  background: #ffffff;
  cursor: pointer;
  font-weight: 600;
}

.overlay-date-input {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.form-input, .form-textarea {
  width: 100%;
  padding: 8px 12px;
  font-size: 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  outline: none;
  background: #ffffff;
  font-weight: 600;
}
.form-input:focus, .form-textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.error-banner {
  background: #fef2f2;
  color: #b91c1c;
  padding: 10px;
  border-radius: 6px;
  font-size: 0.825rem;
  margin-bottom: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
}

.btn-cancel {
  padding: 8px 16px;
  font-size: 0.875rem;
  font-weight: 600;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  color: #475569;
  cursor: pointer;
}

.btn-submit {
  padding: 8px 16px;
  font-size: 0.875rem;
  font-weight: 600;
  background: #2563eb;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}
.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
