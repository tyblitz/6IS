<!-- frontend/src/components/calendar/CalendarGrid.vue -->
<template>
  <div class="calendar-grid-container">
    <!-- Day Name Headers -->
    <div class="grid-day-headers">
      <div
        v-for="(dayName, idx) in dayHeaders"
        :key="dayName"
        :class="[
          'header-cell',
          idx === 0 ? 'is-sunday' : '',
          idx === 6 ? 'is-weekend' : ''
        ]"
      >
        {{ dayName }}
      </div>
    </div>

    <!-- Cells Grid -->
    <div class="month-cells-grid">
      <CalendarDayCell
        v-for="(cell, idx) in calendarCells"
        :key="idx"
        :cell-data="cell"
        @select-day="$emit('select-day', $event)"
        @click-activity="$emit('click-activity', $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { CalendarActivity, CalendarDayData } from '../../types/calendar'
import CalendarDayCell from './CalendarDayCell.vue'

defineProps<{
  calendarCells: CalendarDayData[];
}>()

defineEmits<{
  (e: 'select-day', date: string): void;
  (e: 'click-activity', activity: CalendarActivity): void;
}>()

const dayHeaders = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']
</script>
