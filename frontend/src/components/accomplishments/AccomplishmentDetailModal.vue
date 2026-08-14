<template>
  <div v-if="isOpen && data" class="modal-backdrop" @click.self="close">
    <div class="modal-card">
      
      <!-- Header -->
      <div class="modal-header">
        <h3>Accomplishment Details</h3>
        <button class="close-btn" type="button" @click="close">&times;</button>
      </div>

      <!-- Details Body -->
      <div class="modal-body">
        
        <div class="detail-row">
          <span class="detail-label">Date</span>
          <span class="detail-value font-semibold">{{ formatDate(data.date) }}</span>
        </div>

        <div class="detail-row">
          <span class="detail-label">Office</span>
          <span class="detail-value office-badge">
            {{ data.office_name }} ({{ data.office_code }})
          </span>
        </div>

        <div class="detail-block">
          <span class="detail-label">Description</span>
          <p class="description-text">{{ data.description }}</p>
        </div>

        <div v-if="data.remarks" class="detail-block">
          <span class="detail-label">Remarks</span>
          <p class="remarks-text">{{ data.remarks }}</p>
        </div>

      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button class="btn-edit" type="button" @click="emitEdit">Edit</button>
        <button class="btn-close" type="button" @click="close">Close</button>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import type { AccomplishmentItem } from '../../types/accomplishment'

const props = defineProps<{
  isOpen: boolean;
  data: AccomplishmentItem | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'edit', data: AccomplishmentItem): void;
}>()

function close() {
  emit('close')
}

import { formatDate } from '../../utils/dateUtils'

function emitEdit() {
  if (props.data) {
    emit('edit', props.data)
  }
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
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.modal-header {
  padding: 18px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #64748b;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.detail-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.detail-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.detail-value {
  font-size: 14px;
  color: #1e293b;
}

.font-semibold { font-weight: 600; }

.office-badge {
  background: #eff6ff;
  color: #2563eb;
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
}

.description-text {
  font-size: 14px;
  color: #334155;
  line-height: 1.6;
  background: #f8fafc;
  padding: 12px;
  border-radius: 8px;
  margin: 0;
}

.remarks-text {
  font-size: 13px;
  color: #475569;
  background: #fffbeb;
  padding: 10px 12px;
  border-radius: 8px;
  margin: 0;
}

.modal-footer {
  padding: 16px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-edit {
  background: #10b981;
  color: #ffffff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn-close {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
</style>
