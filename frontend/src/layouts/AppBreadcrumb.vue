<template>
  <div class="app-breadcrumb">
    <span>Dashboard</span>

    <span class="separator">/</span>

    <span>{{ pageTitle }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pageTitle = computed(() => {
  return route.name?.toString() ?? 'Page'
})
</script>

<style scoped>
.app-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  padding: 0 24px;
  background: var(--ion-color-light, #f8f9fa);
  border-bottom: 1px solid #e5e7eb;
  font-size: 14px;
  color: #6b7280;
}

.separator {
  color: #9ca3af;
}

.app-breadcrumb span:last-child {
  font-weight: 600;
  color: #1f2937;
}
</style>