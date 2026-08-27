<!-- frontend/src/components/calendar/CalendarDayCell.vue -->
<template>
  <div
    :class="[
      'day-cell',
      cellData.isCurrentMonth ? '' : 'other-month',
      cellData.isToday ? 'is-today' : '',
      cellData.isWeekend ? 'is-weekend' : ''
    ]"
    @click="$emit('select-day', cellData.date)"
  >
    <!-- Cell Date Header -->
    <div class="cell-header-row">
      <span :class="['cell-date-num', isSunday ? 'is-sunday-num' : '']">
        {{ cellData.dayNumber }}
      </span>
      <span v-if="cellData.isToday" class="today-label-badge">Today</span>
    </div>

    <!-- Event Chips List -->
    <div class="cell-events-list">
      <CalendarEventChip
        v-for="act in visibleEvents"
        :key="act.id"
        :activity="act"
        @click-activity="$emit('click-activity', $event)"
      />

      <!-- Overflow Badge Pill -->
      <div
        v-if="overflowCount > 0"
        class="overflow-badge"
        @click.stop="$emit('select-day', cellData.date)"
      >
        +{{ overflowCount }} more
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarActivity, CalendarDayData } from '../../types/calendar'
import CalendarEventChip from './CalendarEventChip.vue'

const props = defineProps<{
  cellData: CalendarDayData
}>()

defineEmits<{
  (e: 'select-day', date: string): void;
  (e: 'click-activity', activity: CalendarActivity): void;
}>()

// Display top 2 events per cell for clean density
const maxVisible = 2

const isSunday = computed(() => {
  try {
    const dt = new Date(props.cellData.date + 'T00:00:00')
    return dt.getDay() === 0
  } catch (e) {
    return false
  }
})

const visibleEvents = computed(() => {
  return props.cellData.events.slice(0, maxVisible)
})

const overflowCount = computed(() => {
  return Math.max(0, props.cellData.events.length - maxVisible)
})
</script>

<style scoped>
.cell-date-num {
  font-size: 0.875rem;
  font-weight: 700;
  color: #1e293b;
}

.is-sunday-num {
  color: #dc2626 !important;
  font-weight: 800;
}

.today-label-badge {
  font-size: 0.65rem;
  font-weight: 700;
  background: #2563eb;
  color: #ffffff;
  padding: 1px 6px;
  border-radius: 10px;
  text-transform: uppercase;
}

.overflow-badge {
  font-size: 0.725rem;
  font-weight: 700;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  padding: 2px 8px;
  border-radius: 12px;
  display: inline-block;
  margin-top: 3px;
  cursor: pointer;
  align-self: flex-start;
  line-height: 1.2;
  transition: all 0.15s ease;
}

.overflow-badge:hover {
  background: #dbeafe;
  border-color: #93c5fd;
  color: #1d4ed8;
}
</style>
