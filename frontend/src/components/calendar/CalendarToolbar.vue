<!-- frontend/src/components/calendar/CalendarToolbar.vue -->
<template>
  <div class="calendar-toolbar">
    <!-- Left Section: Navigation Controls & View Tabs -->
    <div class="toolbar-left">
      <div class="nav-controls">
        <button class="btn-nav-arrow" type="button" @click="$emit('prev')" title="Previous Period">
          <ion-icon :icon="chevronBackOutline" />
        </button>

        <span class="current-period-title">{{ periodTitle }}</span>

        <button class="btn-nav-arrow" type="button" @click="$emit('next')" title="Next Period">
          <ion-icon :icon="chevronForwardOutline" />
        </button>

        <button class="btn-today" type="button" @click="$emit('today')">
          Today
        </button>
      </div>

      <!-- View Switcher Tabs (Month, Week, Day) -->
      <div class="view-tabs">
        <button
          v-for="v in viewOptions"
          :key="v.key"
          type="button"
          :class="['tab-btn', currentView === v.key ? 'active' : '']"
          @click="$emit('change-view', v.key)"
        >
          {{ v.label }}
        </button>
      </div>
    </div>

    <!-- Right Section: Search & Event Type Filter -->
    <div class="toolbar-right">
      <!-- Search Input -->
      <div class="search-box">
        <ion-icon :icon="searchOutline" class="search-icon" />
        <input
          type="text"
          :value="searchQuery"
          @input="$emit('update:searchQuery', ($event.target as HTMLInputElement).value)"
          placeholder="Search activity..."
          class="search-input"
        />
      </div>

      <!-- Event Type Filter -->
      <select
        :value="selectedTypeId"
        @change="$emit('update:selectedTypeId', Number(($event.target as HTMLSelectElement).value))"
        class="filter-select"
      >
        <option :value="0">All Event Types</option>
        <option v-for="t in eventTypeOptions" :key="t.id" :value="t.id">
          {{ t.type_code }} - {{ t.type_name }}
        </option>
      </select>
    </div>
  </div>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { chevronBackOutline, chevronForwardOutline, searchOutline } from 'ionicons/icons'
import type { CalendarViewMode, CalendarEventTypeOption } from '../../types/calendar'

defineProps<{
  periodTitle: string;
  currentView: CalendarViewMode;
  searchQuery: string;
  selectedTypeId: number;
  eventTypeOptions: CalendarEventTypeOption[];
}>()

defineEmits<{
  (e: 'prev'): void;
  (e: 'next'): void;
  (e: 'today'): void;
  (e: 'change-view', view: CalendarViewMode): void;
  (e: 'update:searchQuery', value: string): void;
  (e: 'update:selectedTypeId', value: number): void;
}>()

const viewOptions: Array<{ key: CalendarViewMode; label: string }> = [
  { key: 'month', label: 'Month' },
  { key: 'week', label: 'Week' },
  { key: 'day', label: 'Day' }
]
</script>

<style scoped>
.calendar-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  border-top: none;
  border-left: none;
  border-right: none;
  border-radius: 0;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.nav-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-nav-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-nav-arrow:hover {
  background: #ffffff;
  border-color: #2563eb;
  color: #2563eb;
  box-shadow: 0 1px 3px rgba(37, 99, 235, 0.12);
}

.current-period-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  min-width: 150px;
  text-align: center;
  letter-spacing: -0.01em;
}

.btn-today {
  padding: 6px 14px;
  font-size: 0.825rem;
  font-weight: 600;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-today:hover {
  background: #eff6ff;
  border-color: #2563eb;
  color: #2563eb;
}

.view-tabs {
  display: flex;
  background: #e2e8f0;
  padding: 3px;
  border-radius: 8px;
}

.tab-btn {
  padding: 5px 14px;
  font-size: 0.825rem;
  font-weight: 600;
  border: none;
  background: transparent;
  color: #475569;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}
.tab-btn.active {
  background: #ffffff;
  color: #2563eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  color: #94a3b8;
  font-size: 16px;
}

.search-input {
  padding: 7px 12px 7px 32px;
  font-size: 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  width: 220px;
  outline: none;
  background: #ffffff;
  transition: all 0.15s ease;
}
.search-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.filter-select {
  padding: 7px 12px;
  font-size: 0.85rem;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  color: #334155;
  outline: none;
  transition: all 0.15s ease;
}
.filter-select:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
</style>
