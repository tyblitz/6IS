<!-- frontend/src/components/calendar/CalendarDayView.vue -->
<template>
  <div class="day-view-wrapper">
    <!-- All-Day Activities Banner -->
    <div v-if="allDayList.length > 0" class="all-day-banner">
      <span class="all-day-label">All-Day Activities:</span>
      <div class="all-day-cards">
        <div
          v-for="(act, idx) in allDayList"
          :key="act.id || act.source_id || idx"
          :class="['day-activity-card', cardTypeClass(act)]"
          @click="$emit('click-activity', act)"
        >
          <div class="card-body">
            <div class="card-title-row">
              <span :class="['type-badge', badgeTypeClass(act)]">{{ getTypeCode(act) }}</span>
              <strong class="card-title-text">{{ formatDisplayTitle(act) }}</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 24-Hour Time Grid (0700H to 2000H) with Dynamic Zero-Clipping Auto-Expanding Rows -->
    <div class="day-time-grid">
      <div v-for="hour in hours" :key="hour" class="day-time-row">
        <div class="day-time-label">{{ formatHour(hour) }}</div>
        <div class="day-time-slot">
          <div
            v-for="(act, idx) in getActivitiesForHour(hour)"
            :key="act.id || act.source_id || idx"
            :class="['day-activity-card', cardTypeClass(act)]"
            @click.stop="$emit('click-activity', act)"
          >
            <div class="card-body">
              <div class="card-title-row">
                <span :class="['type-badge', badgeTypeClass(act)]">{{ getTypeCode(act) }}</span>
                <strong class="card-title-text">{{ formatDisplayTitle(act) }}</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarActivity } from '../../types/calendar'

const props = defineProps<{
  currentDate: string;
  activities: CalendarActivity[];
}>()

defineEmits<{
  (e: 'click-activity', activity: CalendarActivity): void;
}>()

// 24-Hour Operating Range: 0700H to 2000H
const hours = Array.from({ length: 14 }, (_, i) => i + 7)

const dayActivities = computed(() => {
  return props.activities.filter(a => a.date === props.currentDate)
})

const allDayList = computed(() => {
  return dayActivities.value.filter(a => a.all_day)
})

function getActivitiesForHour(hour: number): CalendarActivity[] {
  return dayActivities.value.filter(a => {
    if (a.all_day) return false
    if (a.time) {
      const parts = a.time.split(':')
      if (parts.length >= 1) {
        const h = parseInt(parts[0], 10)
        if (!isNaN(h)) return h === hour
      }
    }
    if (a.start_datetime) {
      try {
        const formatted = a.start_datetime.replace(' ', 'T')
        const dt = new Date(formatted)
        if (!isNaN(dt.getTime())) {
          return dt.getHours() === hour
        }
      } catch (e) {}
    }
    return hour === 9
  })
}

function formatHour(h: number): string {
  const hStr = String(h).padStart(2, '0')
  return `${hStr}00H`
}

function getTypeCode(act: CalendarActivity): string {
  return act.category_code || act.event_type_code || 'CONF'
}

function cardTypeClass(act: CalendarActivity): string {
  return `card-type-${getTypeCode(act)}`
}

function badgeTypeClass(act: CalendarActivity): string {
  return `badge-type-${getTypeCode(act)}`
}

// Clean military time format: 0900H - Reschedule Test Event
function formatDisplayTitle(act: CalendarActivity): string {
  let timeStr = ''
  if (!act.all_day) {
    if (act.time) {
      const parts = act.time.split(':')
      if (parts.length >= 2) {
        timeStr = `${parts[0].padStart(2, '0')}${parts[1].padStart(2, '0')}H - `
      }
    } else if (act.start_datetime) {
      try {
        const formatted = act.start_datetime.replace(' ', 'T')
        const dt = new Date(formatted)
        if (!isNaN(dt.getTime())) {
          const h = String(dt.getHours()).padStart(2, '0')
          const m = String(dt.getMinutes()).padStart(2, '0')
          timeStr = `${h}${m}H - `
        }
      } catch (e) {}
    }
  }
  const titleStr = act.title || 'Operational Activity'
  return `${timeStr}${titleStr}`
}
</script>

<style scoped>
.day-view-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  background: #ffffff;
}

.all-day-banner {
  padding: 12px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #cbd5e1;
}

.all-day-label {
  font-weight: 700;
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
}

.all-day-cards {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
}

.day-time-grid {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: 600px;
  overflow-y: auto;
  border-top: 1px solid #cbd5e1;
}

.day-time-grid::-webkit-scrollbar {
  width: 8px;
}
.day-time-grid::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.day-time-row {
  display: flex !important;
  flex-direction: row !important;
  align-items: stretch !important;
  border-bottom: 1px solid #cbd5e1 !important;
  min-height: 60px !important;
  height: auto !important;
  flex-shrink: 0 !important;
  width: 100% !important;
  box-sizing: border-box !important;
}

.day-time-row:nth-child(even) .day-time-slot {
  background-color: #f8fafc;
}

.day-time-label {
  width: 80px !important;
  min-width: 80px !important;
  max-width: 80px !important;
  flex-shrink: 0 !important;
  padding: 10px !important;
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
  text-align: center;
  border-right: 1px solid #cbd5e1;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.day-time-slot {
  flex: 1 1 auto !important;
  min-width: 0 !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 8px !important;
  padding: 8px 14px !important;
  min-height: 60px !important;
  height: auto !important;
  justify-content: center;
  box-sizing: border-box !important;
  background: #ffffff;
  border-right: 1px solid #cbd5e1;
}

.day-activity-card {
  border-radius: 6px;
  padding: 8px 12px;
  margin: 0 !important;
  cursor: pointer;
  box-sizing: border-box;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  border: 1px solid #cbd5e1;
  border-left-width: 4px;
  width: 100% !important;
  min-height: 38px !important;
  height: auto !important;
  flex-shrink: 0 !important;
}

.day-activity-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.06);
}

.card-type-PAS { background: #f0fdf4; border-left-color: #16a34a; }
.card-type-CONF { background: #eff6ff; border-left-color: #2563eb; }
.card-type-VTC { background: #faf5ff; border-left-color: #9333ea; }

.card-title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.type-badge {
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.badge-type-PAS { background: #dcfce7; color: #15803d; }
.badge-type-CONF { background: #dbeafe; color: #1d4ed8; }
.badge-type-VTC { background: #f3e8ff; color: #7e22ce; }

.card-title-text {
  font-size: 0.875rem;
  color: #0f172a;
  word-break: break-word;
}
</style>
