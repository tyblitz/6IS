<!-- frontend/src/components/calendar/CalendarEventModal.vue -->
<template>
  <div v-if="isOpen" class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <h3>{{ editingActivity ? 'Edit Scheduled Activity' : 'New Scheduled Activity' }}</h3>
        <button class="close-btn" style="background: none; border: none; color: #FFF; font-size: 1.4rem; cursor: pointer;" @click="$emit('close')">&times;</button>
      </div>

      <div class="modal-body">
        <div v-if="errorMessage" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 0.8125rem;">
          {{ errorMessage }}
        </div>

        <div class="form-group">
          <label>Activity Title *</label>
          <input v-model="formData.title" type="text" placeholder="e.g. Housing board" class="form-input" />
        </div>

        <div class="form-row-2col">
          <div class="form-group">
            <label>Event Date *</label>
            <div class="date-picker-wrapper">
              <input
                type="text"
                :value="formatDateMilitary(formData.event_date)"
                readonly
                class="form-input date-display-input"
              />
              <input
                type="date"
                v-model="formData.event_date"
                required
                class="overlay-date-input"
              />
            </div>
          </div>
          <div class="form-group" style="justify-content: flex-end;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 24px;">
              <input v-model="formData.all_day" type="checkbox" />
              <span>All-Day Activity</span>
            </label>
          </div>
        </div>

        <div v-if="!formData.all_day" class="form-row-2col">
          <div class="form-group">
            <label>Start Time</label>
            <select v-model="formData.start_time" class="form-input">
              <option v-for="t in militaryTimeOptions" :key="t.val" :value="t.val">
                {{ t.label }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>End Time</label>
            <select v-model="formData.end_time" class="form-input">
              <option v-for="t in militaryTimeOptions" :key="t.val" :value="t.val">
                {{ t.label }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-row-2col">
          <div class="form-group">
            <label>Office / Unit *</label>
            <select v-model="formData.office_id" class="form-input">
              <option v-for="o in officeOptions" :key="o.id" :value="o.id">
                {{ o.office_abbv || o.office_name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Event Type *</label>
            <select v-model="formData.event_type_id" class="form-input">
              <option v-for="t in eventTypeOptions" :key="t.id" :value="t.id">
                {{ t.type_code }} - {{ t.type_name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" type="button" @click="$emit('close')">Cancel</button>
        <button class="btn-save" type="button" :disabled="saving" @click="handleSave">
          {{ saving ? 'Saving...' : (editingActivity ? 'Update Activity' : 'Create Activity') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import type { CalendarActivity, CalendarEventFormPayload, CalendarEventTypeOption } from '../../types/calendar'
import { fetchCalendarEventTypes } from '../../services/calendarService'
import { fetchAccomplishmentOptions } from '../../services/accomplishmentService'
import type { OfficeOption } from '../../types/accomplishment'

const props = defineProps<{
  isOpen: boolean;
  prepopulatedDate?: string;
  editingActivity?: CalendarActivity | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'save-event', payload: CalendarEventFormPayload): void;
}>()

const saving = ref(false)
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
  if (!ymdStr) return ''
  try {
    const dt = new Date(ymdStr + 'T00:00:00')
    const day = dt.getDate()
    const month = dt.toLocaleDateString('en-US', { month: 'short' })
    const year = dt.getFullYear()
    return `${day} ${month} ${year}`
  } catch (e) {
    return ymdStr
  }
}

const eventTypeOptions = ref<CalendarEventTypeOption[]>([
  { id: 1, type_name: 'Public Address System', type_code: 'PAS' },
  { id: 2, type_name: 'Conference', type_code: 'CONF' },
  { id: 3, type_name: 'Video Teleconference', type_code: 'VTC' }
])

const officeOptions = ref<OfficeOption[]>([
  { id: 1, office_name: 'General Headquarters', office_code: 'HQ', office_abbv: 'HQ' }
])

const formData = ref({
  id: undefined as number | undefined,
  title: '',
  event_date: '',
  start_time: '09:00',
  end_time: '10:00',
  all_day: false,
  office_id: 1,
  event_type_id: 1,
  status: 'Scheduled'
})

async function loadOptions() {
  const types = await fetchCalendarEventTypes()
  if (types && types.length > 0) {
    eventTypeOptions.value = types
  }
  const accOpts = await fetchAccomplishmentOptions()
  if (accOpts && accOpts.data && accOpts.data.offices && accOpts.data.offices.length > 0) {
    officeOptions.value = accOpts.data.offices
  }
}

onMounted(() => {
  loadOptions()
})

watch(
  () => [props.isOpen, props.editingActivity, props.prepopulatedDate],
  () => {
    errorMessage.value = ''
    if (props.editingActivity) {
      formData.value = {
        id: props.editingActivity.source_id,
        title: props.editingActivity.title,
        event_date: props.editingActivity.date,
        start_time: extractTime(props.editingActivity.start_datetime) || '09:00',
        end_time: extractTime(props.editingActivity.end_datetime) || '10:00',
        all_day: props.editingActivity.all_day,
        office_id: props.editingActivity.office_id || 1,
        event_type_id: props.editingActivity.event_type_id || 1,
        status: props.editingActivity.status || 'Scheduled'
      }
    } else {
      const todayStr = props.prepopulatedDate || new Date().toISOString().slice(0, 10)
      formData.value = {
        id: undefined,
        title: '',
        event_date: todayStr,
        start_time: '09:00',
        end_time: '10:00',
        all_day: false,
        office_id: officeOptions.value[0]?.id || 1,
        event_type_id: 1,
        status: 'Scheduled'
      }
    }
  },
  { immediate: true }
)

function extractTime(dtStr?: string | null): string {
  if (!dtStr) return ''
  try {
    const dt = new Date(dtStr)
    const hours = String(dt.getHours()).padStart(2, '0')
    const mins = String(dt.getMinutes()).padStart(2, '0')
    return `${hours}:${mins}`
  } catch (e) {
    return ''
  }
}

function handleSave() {
  if (!formData.value.title.trim()) {
    errorMessage.value = 'Please provide an activity title.'
    return
  }
  if (!formData.value.event_date) {
    errorMessage.value = 'Please select an event date.'
    return
  }

  if (!formData.value.all_day && formData.value.start_time && formData.value.end_time) {
    if (formData.value.end_time <= formData.value.start_time) {
      errorMessage.value = 'End time must be after start time.'
      return
    }
  }

  const startDt = `${formData.value.event_date} ${formData.value.start_time}:00`
  const endDt = `${formData.value.event_date} ${formData.value.end_time}:00`

  const payload: CalendarEventFormPayload = {
    id: formData.value.id,
    title: formData.value.title.trim(),
    event_date: formData.value.event_date,
    event_time: formData.value.start_time + ':00',
    start_datetime: startDt,
    end_datetime: endDt,
    all_day: formData.value.all_day,
    office_id: formData.value.office_id,
    event_type_id: formData.value.event_type_id,
    status: formData.value.status
  }

  emit('save-event', payload)
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.modal-card {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  background: #1e293b;
  color: #ffffff;
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
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

.form-input {
  width: 100%;
  padding: 8px 12px;
  font-size: 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  outline: none;
  background: #ffffff;
  font-weight: 600;
}
.form-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.modal-footer {
  padding: 14px 20px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
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

.btn-save {
  padding: 8px 16px;
  font-size: 0.875rem;
  font-weight: 600;
  background: #2563eb;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}
</style>
