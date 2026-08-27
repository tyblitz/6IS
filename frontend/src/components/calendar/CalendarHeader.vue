<!-- frontend/src/components/calendar/CalendarHeader.vue -->
<template>
  <div class="calendar-page-header">
    <div class="header-info">
      <h2>Calendar of Activities</h2>
    </div>
    <button class="btn-primary-add" type="button" @click="$emit('add-event')">
      <ion-icon :icon="addOutline" />
      <span>Add Activity</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { addOutline } from 'ionicons/icons'

defineEmits<{
  (e: 'add-event'): void
}>()
</script>
