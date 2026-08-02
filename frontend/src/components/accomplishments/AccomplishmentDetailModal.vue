<template>
  <div v-if="isOpen && data" class="modal-backdrop" @click.self="close">
    <div class="modal-content">
      
      <div class="modal-header">
        <h2>Accomplishment Details</h2>
        <button class="close-btn" type="button" @click="close">&times;</button>
      </div>

      <div class="modal-body">
        
        <div class="title-section">
          <h3>{{ data.title }}</h3>
          <div class="badges-row">
            <span :class="['badge', 'status-badge', data.status.toLowerCase()]">
              {{ data.status }}
            </span>
            <span :class="['badge', 'priority-badge', data.priority.toLowerCase()]">
              {{ data.priority }} Priority
            </span>
          </div>
        </div>

        <div class="info-grid">
          <div class="info-item">
            <span class="label">Office</span>
            <span class="value">{{ data.office_name }} ({{ data.office_code }})</span>
          </div>

          <div class="info-item">
            <span class="label">Category</span>
            <span class="value">{{ data.category_name }}</span>
          </div>

          <div class="info-item">
            <span class="label">Assigned Employee</span>
            <span class="value">{{ data.assigned_employee_name }}</span>
          </div>

          <div class="info-item">
            <span class="label">Date Started</span>
            <span class="value">{{ data.date_started }}</span>
          </div>

          <div class="info-item">
            <span class="label">Date Completed</span>
            <span class="value">{{ data.date_completed || 'N/A' }}</span>
          </div>
        </div>

        <div v-if="data.description" class="text-block">
          <span class="label">Description</span>
          <p>{{ data.description }}</p>
        </div>

        <div v-if="data.remarks" class="text-block">
          <span class="label">Remarks</span>
          <p>{{ data.remarks }}</p>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" @click="close">
          Close
        </button>
        <button class="btn btn-primary" type="button" @click="$emit('edit', data)">
          Edit Record
        </button>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import type { Accomplishment } from '../../types/accomplishment'

defineProps<{
  isOpen: boolean;
  data: Accomplishment | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'edit', record: Accomplishment): void;
}>()

function close() {
  emit('close')
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-content {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 600px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 24px;
  color: #9ca3af;
  cursor: pointer;
}

.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
}

.title-section h3 {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.badges-row {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
}

.badge {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 12px;
}

.status-badge.completed { background: #dcfce7; color: #15803d; }
.status-badge.ongoing { background: #dbeafe; color: #1d4ed8; }
.status-badge.pending { background: #fef3c7; color: #b45309; }
.status-badge.cancelled { background: #fee2e2; color: #b91c1c; }

.priority-badge.low { background: #f3f4f6; color: #4b5563; }
.priority-badge.medium { background: #e0e7ff; color: #4338ca; }
.priority-badge.high { background: #ffedd5; color: #c2410c; }
.priority-badge.critical { background: #ffe4e6; color: #be123c; }

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 8px;
}

.info-item {
  display: flex;
  flex-direction: column;
}

.label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-bottom: 2px;
}

.value {
  font-size: 14px;
  color: #1f2937;
  font-weight: 500;
}

.text-block {
  margin-bottom: 16px;
}

.text-block p {
  margin: 4px 0 0 0;
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  padding: 8px 18px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
}

.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-primary { background: #2563eb; color: #ffffff; }
</style>
