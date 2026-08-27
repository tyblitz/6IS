<!-- frontend/src/components/calendar/CalendarWeekView.vue -->
<template>
  <div class="week-view-wrapper">
    <!-- All Day Activities Banner -->
    <div v-if="allDayActivities.length > 0" class="all-day-row">
      <span class="all-day-label">All-Day Activities:</span>
      <div class="all-day-list">
        <div
          v-for="act in allDayActivities"
          :key="act.id"
          :class="['event-chip', chipTypeClass(act)]"
          :title="formatChipTitle(act)"
          @click="$emit('click-activity', act)"
        >
          <span class="chip-title">{{ formatChipTitle(act) }}</span>
        </div>
      </div>
    </div>

    <!-- Single Unified Scroll Container (Header + Time Grid together for 100% pixel alignment) -->
    <div class="week-scroll-container">
      <!-- Sticky Header Row with Day Dates (SUN to SAT) -->
      <div class="time-grid-header">
        <div class="day-col-header time-col-title">Time</div>
        <div
          v-for="d in weekDays"
          :key="d.dateStr"
          class="day-col-header"
          :style="{ background: d.isToday ? '#EFF6FF' : 'transparent' }"
        >
          <div :style="{ color: d.isSunday ? '#DC2626' : '#64748B', fontWeight: d.isSunday ? '700' : '600' }">
            {{ d.dayName }}
          </div>
          <div :style="{ fontSize: '1rem', color: d.isSunday ? '#DC2626' : '#172554', fontWeight: '700' }">
            {{ d.dayNum }}
          </div>
        </div>
      </div>

      <!-- 24-Hour Time Grid (0700H to 2000H) -->
      <div class="time-grid-body">
        <div v-for="hour in hours" :key="hour" class="time-row">
          <div class="time-label">{{ formatHour(hour) }}</div>
          <div
            v-for="d in weekDays"
            :key="d.dateStr"
            class="time-slot-cell"
            @click="$emit('select-day', d.dateStr)"
          >
            <div
              v-for="act in getActivitiesForSlot(d.dateStr, hour)"
              :key="act.id"
              :class="['event-chip', chipTypeClass(act)]"
              :title="formatChipTitle(act)"
              @click.stop="$emit('click-activity', act)"
            >
              <span class="chip-title">{{ formatChipTitle(act) }}</span>
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
  (e: 'select-day', date: string): void;
  (e: 'click-activity', activity: CalendarActivity): void;
}>()

function toYMD(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// Operating Hours: 0700H to 2000H
const hours = Array.from({ length: 14 }, (_, i) => i + 7)

// Week starts on SUN (0) and ends on SAT (6)
const weekDays = computed(() => {
  const dt = new Date(props.currentDate + 'T00:00:00')
  const dayOfWeek = dt.getDay() // Sunday is 0
  const sunday = new Date(dt)
  sunday.setDate(dt.getDate() - dayOfWeek)

  const todayStr = toYMD(new Date())
  const days = []

  for (let i = 0; i < 7; i++) {
    const d = new Date(sunday)
    d.setDate(sunday.getDate() + i)
    const dateStr = toYMD(d)
    days.push({
      dateStr,
      dayName: d.toLocaleDateString('en-US', { weekday: 'short' }),
      dayNum: d.getDate(),
      isToday: dateStr === todayStr,
      isSunday: i === 0 || d.getDay() === 0
    })
  }
  return days
})

const allDayActivities = computed(() => {
  return props.activities.filter(a => a.all_day)
})

function getActivitiesForSlot(dateStr: string, hour: number): CalendarActivity[] {
  return props.activities.filter(a => {
    if (a.all_day) return false
    if (a.date !== dateStr) return false
    if (!a.start_datetime) return hour === 9
    try {
      const h = new Date(a.start_datetime).getHours()
      return h === hour
    } catch (e) {
      return false
    }
  })
}

function formatHour(h: number): string {
  const hStr = String(h).padStart(2, '0')
  return `${hStr}00H`
}

function getTypeCode(act: CalendarActivity): string {
  return act.category_code || act.event_type_code || 'CONF'
}

function chipTypeClass(act: CalendarActivity): string {
  return `chip-type-${getTypeCode(act)}`
}

function formatChipTitle(act: CalendarActivity): string {
  let timeStr = ''
  if (!act.all_day && act.start_datetime) {
    try {
      const dt = new Date(act.start_datetime)
      if (!isNaN(dt.getTime())) {
        const h = String(dt.getHours()).padStart(2, '0')
        const m = String(dt.getMinutes()).padStart(2, '0')
        timeStr = `${h}${m}H - `
      }
    } catch (e) {}
  }
  const typeCode = getTypeCode(act)
  const titleStr = act.title ? ` - ${act.title}` : ''
  return `${timeStr}${typeCode}${titleStr}`
}
</script>

<style scoped>
.week-view-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  background: #ffffff;
}

.all-day-row {
  padding: 10px 14px;
  background: #f8fafc;
  border-bottom: 1px solid #cbd5e1;
}

.all-day-label {
  font-weight: 700;
  font-size: 0.75rem;
  color: #64748b;
  text-transform: uppercase;
}

.all-day-list {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 6px;
}

.time-slot-cell {
  display: flex !important;
  flex-direction: column !important;
  gap: 3px !important;
  padding: 4px !important;
  min-height: 56px !important;
  height: auto !important;
  overflow: visible !important;
  border-right: 1px solid #cbd5e1;
  box-sizing: border-box;
}

.event-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.725rem;
  font-weight: 600;
  line-height: 1.2;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  min-width: 0;
  flex-shrink: 0 !important;
  background: #f1f5f9;
  color: #1e293b;
  border-left: 3px solid #3b82f6;
  margin-bottom: 2px;
  box-sizing: border-box;
}

.event-chip:hover {
  filter: brightness(0.95);
}

.chip-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  display: block;
}

.chip-type-PAS { background: #f0fdf4; border-left-color: #16a34a; color: #14532d; }
.chip-type-CONF { background: #eff6ff; border-left-color: #2563eb; color: #1e3a8a; }
.chip-type-VTC { background: #faf5ff; border-left-color: #9333ea; color: #581c87; }
</style>
