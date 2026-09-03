<!-- frontend/src/components/calendar/CalendarDayDrawer.vue -->
<template>
  <div v-if="isOpen" class="drawer-overlay" @click.self="$emit('close')">
    <div class="day-drawer">
      <!-- Drawer Header -->
      <div class="drawer-header">
        <div>
          <h3>{{ formattedDateTitle }}</h3>
          <span style="font-size: 0.8rem; opacity: 0.85;">Scheduled Operational Activities</span>
        </div>
        <button class="drawer-close-btn" type="button" @click="$emit('close')">&times;</button>
      </div>

      <!-- Drawer Body -->
      <div class="drawer-body">
        <div class="drawer-actions-bar">
          <button class="btn-primary-add" type="button" style="padding: 6px 14px; font-size: 0.8125rem;" @click="$emit('add-event-for-date', selectedDate)">
            <ion-icon :icon="addOutline" />
            <span>Add Activity</span>
          </button>
        </div>

        <!-- Empty State -->
        <div v-if="dayActivities.length === 0" style="text-align: center; padding: 50px 10px; color: #64748B;">
          <ion-icon :icon="calendarClearOutline" style="font-size: 2.8rem; color: #94A3B8; margin-bottom: 12px;" />
          <h4 style="margin: 0 0 6px 0; color: #334155; font-size: 1rem;">No activities scheduled.</h4>
          <p style="font-size: 0.85rem; margin: 0; color: #64748B;">No calendar activities recorded for this date.</p>
        </div>

        <!-- Single Clean List of Calendar Activities with Event Type Distinctions -->
        <div v-else style="display: flex; flex-direction: column; gap: 10px; margin-top: 6px;">
          <div
            v-for="act in dayActivities"
            :key="act.id"
            :class="['drawer-activity-card', getCardTypeClass(act)]"
            @click="$emit('click-activity', act)"
          >
            <div class="card-content-col">
              <p class="card-activity-title">
                {{ formatDisplayTitle(act) }}
              </p>
              <div class="card-office-meta">
                <span>🏢 Office: <strong>{{ act.office_abbv || act.office_name || 'HQ' }}</strong></span>
              </div>
            </div>
            <ion-icon :icon="chevronForwardOutline" class="card-chevron-icon" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { IonIcon } from '@ionic/vue'
import { addOutline, chevronForwardOutline, calendarClearOutline } from 'ionicons/icons'
import type { CalendarActivity } from '../../types/calendar'

const props = defineProps<{
  isOpen: boolean;
  selectedDate: string;
  activities: CalendarActivity[];
}>()

defineEmits<{
  (e: 'close'): void;
  (e: 'add-event-for-date', date: string): void;
  (e: 'click-activity', activity: CalendarActivity): void;
}>()

const dayActivities = computed(() => {
  return props.activities.filter(a => a.date === props.selectedDate)
})

const formattedDateTitle = computed(() => {
  if (!props.selectedDate) return ''
  try {
    const dt = new Date(props.selectedDate + 'T00:00:00')
    return dt.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', year: 'numeric' })
  } catch (e) {
    return props.selectedDate
  }
})

function getTypeCode(act: CalendarActivity): string {
  return act.category_code || act.event_type_code || 'CONF'
}

function getCardTypeClass(act: CalendarActivity): string {
  return `card-type-${getTypeCode(act)}`
}

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
  const typeCode = getTypeCode(act)
  const titleStr = act.title ? ` - ${act.title}` : ''
  return `${timeStr}${typeCode}${titleStr}`
}
</script>

<style scoped>
.drawer-activity-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-left-width: 4px;
  border-radius: 8px;
  cursor: pointer;
  /* Hardware-accelerated 60fps butter-smooth transition */
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              background-color 0.2s cubic-bezier(0.4, 0, 0.2, 1),
              border-color 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  will-change: transform, box-shadow, background-color;
  backface-visibility: hidden;
}

.drawer-activity-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
}

.card-content-col {
  flex: 1;
  min-width: 0;
}

.card-activity-title {
  font-weight: 700;
  font-size: 0.9rem;
  margin: 0 0 4px 0;
  color: #0f172a;
  line-height: 1.35;
}

.card-office-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.775rem;
  color: #64748b;
}

.card-chevron-icon {
  color: #94a3b8;
  font-size: 20px;
  flex-shrink: 0;
  margin-left: 8px;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-activity-card:hover .card-chevron-icon {
  color: #2563eb;
  transform: translateX(3px);
}

/* Event Type Color Distinctions */
.card-type-PAS {
  border-left-color: #16a34a;
  background: #f0fdf4;
}
.card-type-PAS:hover {
  background: #dcfce7;
  border-color: #bbf7d0;
  border-left-color: #15803d;
}

.card-type-CONF {
  border-left-color: #2563eb;
  background: #eff6ff;
}
.card-type-CONF:hover {
  background: #dbeafe;
  border-color: #bfdbfe;
  border-left-color: #1d4ed8;
}

.card-type-VTC {
  border-left-color: #9333ea;
  background: #faf5ff;
}
.card-type-VTC:hover {
  background: #f3e8ff;
  border-color: #e9d5ff;
  border-left-color: #7e22ce;
}
</style>
