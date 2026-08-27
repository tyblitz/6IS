<!-- frontend/src/components/calendar/CalendarEventChip.vue -->
<template>
  <div
    :class="['event-chip', chipTypeClass]"
    :title="formattedTooltip"
    @click.stop="$emit('click-activity', activity)"
  >
    <span class="chip-title">{{ displayTitle }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CalendarActivity } from '../../types/calendar'

const props = defineProps<{
  activity: CalendarActivity
}>()

defineEmits<{
  (e: 'click-activity', activity: CalendarActivity): void
}>()

const typeCode = computed(() => {
  return props.activity.category_code || props.activity.event_type_code || 'CONF'
})

const chipTypeClass = computed(() => `chip-type-${typeCode.value}`)

const timeDisplay = computed(() => {
  if (props.activity.all_day) return ''
  if (!props.activity.start_datetime) return ''
  try {
    const dt = new Date(props.activity.start_datetime)
    if (isNaN(dt.getTime())) return ''
    const h = String(dt.getHours()).padStart(2, '0')
    const m = String(dt.getMinutes()).padStart(2, '0')
    return `${h}${m}` // 24-hr format e.g. 1400
  } catch (e) {
    return ''
  }
})

const displayTitle = computed(() => {
  const timeStr = timeDisplay.value ? `${timeDisplay.value} - ` : ''
  const titleStr = props.activity.title ? ` - ${props.activity.title}` : ''
  return `${timeStr}${typeCode.value}${titleStr}`
})

const formattedTooltip = computed(() => {
  return displayTitle.value
})
</script>

<style scoped>
.event-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
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
}

/* Color Coding by Event Type */
.chip-type-PAS { background: #f0fdf4; border-left-color: #16a34a; color: #14532d; }
.chip-type-CONF { background: #eff6ff; border-left-color: #2563eb; color: #1e3a8a; }
.chip-type-VTC { background: #faf5ff; border-left-color: #9333ea; color: #581c87; }
</style>
