<!-- frontend/src/components/calendar/CalendarFilters.vue -->
<template>
  <div class="calendar-filters-bar">
    <button
      type="button"
      :class="['filter-pill', currentFilter === 'all' ? 'active' : '']"
      @click="$emit('change-filter', 'all')"
    >
      All
    </button>

    <button
      type="button"
      :class="['filter-pill', 'pill-acc', currentFilter === 'accomplishment' ? 'active' : '']"
      @click="$emit('change-filter', 'accomplishment')"
    >
      <span class="dot" style="background: #16A34A;"></span>
      Accomplishment
    </button>

    <button
      type="button"
      :class="['filter-pill', 'pill-inc', currentFilter === 'incoming_comm' ? 'active' : '']"
      @click="$emit('change-filter', 'incoming_comm')"
    >
      <span class="dot" style="background: #2563EB;"></span>
      Incoming Comm
    </button>

    <button
      type="button"
      :class="['filter-pill', 'pill-out', currentFilter === 'outgoing_comm' ? 'active' : '']"
      @click="$emit('change-filter', 'outgoing_comm')"
    >
      <span class="dot" style="background: #EA580C;"></span>
      Outgoing Comm
    </button>

    <button
      type="button"
      :class="['filter-pill', 'pill-evt', currentFilter === 'calendar_event' ? 'active' : '']"
      @click="$emit('change-filter', 'calendar_event')"
    >
      <span class="dot" style="background: #9333EA;"></span>
      Calendar Event
    </button>
  </div>
</template>

<script setup lang="ts">
import type { CalendarFilterSource } from '../../types/calendar'

defineProps<{
  currentFilter: CalendarFilterSource;
}>()

defineEmits<{
  (e: 'change-filter', filter: CalendarFilterSource): void;
}>()
</script>
