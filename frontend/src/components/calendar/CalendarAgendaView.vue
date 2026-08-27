<!-- frontend/src/components/calendar/CalendarAgendaView.vue -->
<template>
  <div class="agenda-view-wrapper">
    <div v-if="groupedAgenda.length === 0" class="empty-agenda-state">
      <p style="text-align: center; color: #64748B; padding: 40px;">No operational activities scheduled for this period.</p>
    </div>

    <div v-else v-for="group in groupedAgenda" :key="group.date" class="agenda-date-group">
      <div class="agenda-date-header">
        <span>{{ formatDateTitle(group.date) }}</span>
        <span class="count-badge" style="font-size: 0.75rem; color: #64748B; font-weight: 600;">
          {{ group.activities.length }} {{ group.activities.length === 1 ? 'activity' : 'activities' }}
        </span>
      </div>

      <div class="agenda-items-list">
        <div
          v-for="act in group.activities"
          :key="act.id"
          class="agenda-item-row"
          @click="$emit('click-activity', act)"
        >
          <div class="agenda-time-col">
            {{ formatTime(act) }}
          </div>

          <div class="agenda-body-col">
            <h4 class="agenda-item-title">{{ formatDisplayTitle(act) }}</h4>
            <div class="agenda-item-meta">
              <span class="source-badge badge-event">
                {{ act.category_code || 'CONF' }}
              </span>
              <span class="cat-tag">Office: <strong>{{ act.office_abbv || act.office_name || 'HQ' }}</strong></span>
              <span class="status-tag">Status: <strong>{{ act.status }}</strong></span>
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
  activities: CalendarActivity[]
}>()

defineEmits<{
  (e: 'click-activity', activity: CalendarActivity): void
}>()

interface AgendaDateGroup {
  date: string;
  activities: CalendarActivity[];
}

const groupedAgenda = computed(() => {
  const groupsMap: Record<string, CalendarActivity[]> = {}

  for (const act of props.activities) {
    if (!groupsMap[act.date]) {
      groupsMap[act.date] = []
    }
    groupsMap[act.date].push(act)
  }

  const sortedDates = Object.keys(groupsMap).sort()
  return sortedDates.map(d => ({
    date: d,
    activities: groupsMap[d]
  }))
})

function formatDateTitle(dateStr: string): string {
  try {
    const dt = new Date(dateStr + 'T00:00:00')
    return dt.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
  } catch (e) {
    return dateStr
  }
}

function formatTime(act: CalendarActivity): string {
  if (act.all_day) return 'All Day'
  if (!act.start_datetime) return '09:00 AM'
  try {
    const dt = new Date(act.start_datetime)
    return dt.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })
  } catch (e) {
    return '09:00 AM'
  }
}

function formatDisplayTitle(act: CalendarActivity): string {
  const typeCode = act.category_code || act.event_type_code || 'CONF'
  return `${typeCode}`
}
</script>
