<!-- frontend/src/components/calendar/CalendarActivityDetailsModal.vue -->
<template>
  <div v-if="isOpen && activity" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-container">
      <!-- Modal Header -->
      <div class="modal-header">
        <div></div>
        <button class="btn-close" type="button" @click="$emit('close')">
          <ion-icon :icon="closeOutline" />
        </button>
      </div>

      <!-- Modal Content Body -->
      <div class="modal-body">
        <h2 class="activity-title">{{ formatDisplayTitle(activity) }}</h2>

        <div class="details-grid">
          <div class="detail-item">
            <ion-icon :icon="calendarOutline" class="item-icon" />
            <div>
              <span class="item-label">Date & Time</span>
              <div class="item-value">{{ formatDateTimeDisplay(activity) }}</div>
            </div>
          </div>

          <div class="detail-item">
            <ion-icon :icon="businessOutline" class="item-icon" />
            <div>
              <span class="item-label">Office / Unit</span>
              <div class="item-value">{{ activity.office_abbv || activity.office_name || 'General Headquarters' }}</div>
            </div>
          </div>

          <div class="detail-item">
            <ion-icon :icon="pricetagOutline" class="item-icon" />
            <div>
              <span class="item-label">Event Type</span>
              <div class="item-value">{{ activity.category || 'Conference' }}</div>
            </div>
          </div>
        </div>

        <!-- Reschedule Audit Log Accordion -->
        <div v-if="detailRecord && detailRecord.reschedules && detailRecord.reschedules.length > 0" class="section-box history-box">
          <span class="section-title">Rescheduling Audit History ({{ detailRecord.reschedules.length }})</span>
          <div class="history-timeline">
            <div v-for="r in detailRecord.reschedules" :key="r.id" class="timeline-item">
              <div class="timeline-dot"></div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <strong>Moved to {{ r.new_start_datetime }}</strong>
                  <span class="timeline-date">{{ formatDate(r.created_at) }}</span>
                </div>
                <div class="timeline-sub">From: {{ r.previous_start_datetime || 'Original Schedule' }}</div>
                <div v-if="r.reason" class="timeline-reason">"{{ r.reason }}"</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="errorMessage" class="error-banner">
          {{ errorMessage }}
        </div>
      </div>

      <!-- Modal Footer Actions (Sleek Professional Button System) -->
      <div class="modal-footer">
        <button type="button" class="btn-prof-primary" @click="$emit('open-reschedule', activity)">
          Reschedule
        </button>

        <button
          type="button"
          class="btn-prof-secondary"
          @click="$emit('edit', activity)"
        >
          Edit
        </button>

        <button
          type="button"
          class="btn-prof-danger"
          @click="$emit('delete', activity)"
        >
          Delete
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  closeOutline,
  calendarOutline,
  businessOutline,
  pricetagOutline
} from 'ionicons/icons'
import type { CalendarActivity } from '../../types/calendar'
import { fetchCalendarActivityDetail } from '../../services/calendarService'
import { formatDateTime } from '../../utils/dateUtils'

const props = defineProps<{
  isOpen: boolean;
  activity: CalendarActivity | null;
}>()

defineEmits<{
  (e: 'close'): void;
  (e: 'updated'): void;
  (e: 'edit', activity: CalendarActivity): void;
  (e: 'delete', activity: CalendarActivity): void;
  (e: 'open-reschedule', activity: CalendarActivity): void;
  (e: 'open-accomplishment-form', activity: CalendarActivity): void;
}>()

const detailRecord = ref<CalendarActivity | null>(null)
const errorMessage = ref('')

watch(
  () => [props.isOpen, props.activity],
  async () => {
    const act = props.activity
    if (props.isOpen && act && typeof act === 'object') {
      errorMessage.value = ''
      detailRecord.value = await fetchCalendarActivityDetail(act.source_id)
    } else {
      detailRecord.value = null
    }
  },
  { immediate: true }
)

function formatDisplayTitle(act: CalendarActivity): string {
  let timeStr = ''
  if (!act.all_day && act.start_datetime) {
    try {
      const dt = new Date(act.start_datetime)
      if (!isNaN(dt.getTime())) {
        const h = String(dt.getHours()).padStart(2, '0')
        const m = String(dt.getMinutes()).padStart(2, '0')
        timeStr = `${h}${m}H - `
      }
    } catch {
      // ignore parse error
    }
  }
  const typeCode = act.category_code || act.event_type_code || 'CONF'
  const titleStr = act.title ? ` - ${act.title}` : ''
  return `${timeStr}${typeCode}${titleStr}`
}

function formatDateTimeDisplay(act: CalendarActivity): string {
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
}

function formatDate(dtStr?: string) {
  if (!dtStr) return ''
  return formatDateTime(dtStr)
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
  z-index: 9998;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-container {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 20px;
  color: #64748b;
  cursor: pointer;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}

.activity-title {
  margin: 0 0 16px 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.detail-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #f8fafc;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.item-icon {
  font-size: 20px;
  color: #2563eb;
  margin-top: 2px;
}

.item-label {
  display: block;
  font-size: 0.725rem;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.item-value {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  margin-top: 2px;
}

.section-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
  margin-bottom: 16px;
}

.section-title {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  margin-bottom: 6px;
}

.history-timeline {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.timeline-item {
  display: flex;
  gap: 10px;
  position: relative;
}

.timeline-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #3b82f6;
  margin-top: 5px;
}

.timeline-content {
  flex: 1;
  background: #ffffff;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  font-size: 0.825rem;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  color: #0f172a;
}

.timeline-date {
  color: #94a3b8;
  font-size: 0.75rem;
}

.timeline-sub {
  color: #64748b;
  margin-top: 2px;
}

.timeline-reason {
  font-style: italic;
  color: #475569;
  margin-top: 4px;
}

.error-banner {
  background: #fef2f2;
  color: #b91c1c;
  padding: 10px;
  border-radius: 6px;
  font-size: 0.825rem;
  margin-bottom: 12px;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

/* Professional Cohesive Button System */
.btn-prof-primary, .btn-prof-secondary, .btn-prof-danger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 18px;
  font-size: 0.825rem;
  font-weight: 600;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
  outline: none;
}

.btn-prof-primary {
  background: #1e293b;
  color: #ffffff;
  border: 1px solid #1e293b;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.btn-prof-primary:hover {
  background: #0f172a;
  border-color: #0f172a;
}

.btn-prof-secondary {
  background: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
}
.btn-prof-secondary:hover {
  background: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}

.btn-prof-danger {
  background: #ffffff;
  color: #dc2626;
  border: 1px solid #fca5a5;
}
.btn-prof-danger:hover {
  background: #fef2f2;
  border-color: #f87171;
  color: #b91c1c;
}
</style>
