<template>
  <div v-if="totalItems > 0" class="table-pagination-container">
    <div class="pagination-info">
      Showing <span class="font-semibold">{{ startIndex }}</span> to
      <span class="font-semibold">{{ endIndex }}</span> of
      <span class="font-semibold">{{ totalItems }}</span> entries
    </div>

    <div v-if="totalPages > 1" class="pagination-controls">
      <button
        type="button"
        class="page-nav-btn"
        :disabled="currentPage === 1"
        @click="$emit('change-page', currentPage - 1)"
      >
        <ion-icon :icon="chevronBackOutline" />
        <span>Prev</span>
      </button>

      <div class="page-numbers">
        <button
          v-for="p in visiblePages"
          :key="p"
          type="button"
          :class="['page-num-btn', p === currentPage ? 'active-page' : '']"
          @click="$emit('change-page', p)"
        >
          {{ p }}
        </button>
      </div>

      <button
        type="button"
        class="page-nav-btn"
        :disabled="currentPage === totalPages"
        @click="$emit('change-page', currentPage + 1)"
      >
        <span>Next</span>
        <ion-icon :icon="chevronForwardOutline" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { IonIcon } from '@ionic/vue'
import { chevronBackOutline, chevronForwardOutline } from 'ionicons/icons'

const props = defineProps<{
  currentPage: number
  totalPages: number
  totalItems: number
  startIndex: number
  endIndex: number
}>()

defineEmits<{
  (e: 'change-page', page: number): void
}>()

const visiblePages = computed(() => {
  const pages: number[] = []
  const total = props.totalPages
  const curr = props.currentPage

  let start = Math.max(1, curr - 2)
  let end = Math.min(total, curr + 2)

  if (curr <= 3) {
    end = Math.min(total, 5)
  } else if (curr >= total - 2) {
    start = Math.max(1, total - 4)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  return pages
})
</script>

<style scoped>
.table-pagination-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  background: #ffffff;
  border-top: 1px solid #e2e8f0;
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
  font-size: 13px;
  color: #64748b;
  flex-wrap: wrap;
  gap: 12px;
}

.font-semibold {
  font-weight: 700;
  color: #1e293b;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-numbers {
  display: flex;
  align-items: center;
  gap: 4px;
}

.page-nav-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.page-nav-btn:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #0f172a;
}

.page-nav-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-num-btn {
  min-width: 32px;
  height: 32px;
  padding: 0 6px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #475569;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.page-num-btn:hover {
  background: #f8fafc;
  color: #0f172a;
}

.active-page {
  background: #2563eb !important;
  border-color: #2563eb !important;
  color: #ffffff !important;
  font-weight: 700;
}
</style>
