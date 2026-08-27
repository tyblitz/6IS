<!-- frontend/src/components/calendar/CalendarSummary.vue -->
<template>
  <div class="calendar-summary-bar">
    <div class="summary-title-badge">
      <ion-icon :icon="analyticsOutline" />
      <span>TODAY'S TIMELINE</span>
    </div>

    <div class="metrics-pills">
      <div class="metric-item metric-total">
        <span>Total Activities:</span>
        <span class="count-val">{{ metrics?.total_today ?? 0 }}</span>
      </div>
      <div class="metric-item metric-acc">
        <span>Accomplishments:</span>
        <span class="count-val">{{ metrics?.accomplishments_count ?? 0 }}</span>
      </div>
      <div class="metric-item metric-inc">
        <span>Incoming:</span>
        <span class="count-val">{{ metrics?.incoming_count ?? 0 }}</span>
      </div>
      <div class="metric-item metric-out">
        <span>Outgoing:</span>
        <span class="count-val">{{ metrics?.outgoing_count ?? 0 }}</span>
      </div>
      <div class="metric-item metric-evt">
        <span>Events:</span>
        <span class="count-val">{{ metrics?.events_count ?? 0 }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { analyticsOutline } from 'ionicons/icons'
import type { CalendarSummaryMetrics } from '../../types/calendar'

defineProps<{
  metrics: CalendarSummaryMetrics | null
}>()
</script>
