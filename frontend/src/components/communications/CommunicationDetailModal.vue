<template>
  <div v-if="isOpen && data" class="modal-backdrop" @click.self="close">
    <div class="modal-card">
      <!-- Header -->
      <div class="modal-header">
        <div class="header-title-box">
          <span
            class="type-badge"
            :class="data.communication_type === 'Incoming' ? 'badge-incoming' : 'badge-outgoing'"
          >
            {{ data.communication_type }}
          </span>
          <h3>Communication Details</h3>
        </div>
        <button class="close-btn" type="button" @click="close">&times;</button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <!-- Subject Banner -->
        <div class="subject-banner">
          <h4>{{ data.subject }}</h4>
          <div class="banner-meta">
            <span class="status-badge" :class="getStatusClass(data.status)">{{ data.status }}</span>
            <span class="age-pill">
              <strong>{{ data.age_days ?? 0 }}</strong> days active
            </span>
          </div>
        </div>

        <!-- Meta Grid -->
        <div class="meta-grid">
          <div class="meta-item">
            <span class="meta-label">Responsible Office</span>
            <span class="meta-value">{{ data.office_name }} ({{ data.office_abbv || data.office_code }})</span>
          </div>

          <div class="meta-item">
            <span class="meta-label">Category</span>
            <span class="meta-value">{{ data.category_name }} {{ data.category_code ? `(${data.category_code})` : '' }}</span>
          </div>

          <div class="meta-item">
            <span class="meta-label">Purpose</span>
            <span class="meta-value">{{ data.purpose_name }}</span>
          </div>

          <div class="meta-item">
            <span class="meta-label">Communication Date</span>
            <span class="meta-value">{{ formatDate(data.communication_date) }}</span>
          </div>
        </div>

        <!-- Timeline Section -->
        <div class="timeline-section">
          <div class="timeline-header">
            <h4>Process Activity History</h4>
            <button class="add-act-btn" type="button" @click="showAddActivity = !showAddActivity">
              <ion-icon :icon="addOutline" />
              <span>{{ showAddActivity ? 'Cancel Activity' : 'Add Activity Log' }}</span>
            </button>
          </div>

          <!-- Add Activity Form -->
          <form v-if="showAddActivity" class="add-activity-box" @submit.prevent="submitNewActivity">
            <div class="form-group">
              <label for="act_type">Activity Type / Event <span class="required">*</span></label>
              <input
                id="act_type"
                v-model="newActivity.activity_type"
                type="text"
                placeholder="e.g. Reviewed, Forwarded to Director, Pass Issued..."
                required
              />
            </div>

            <div class="form-group">
              <label for="act_date">Activity Date & Time <span class="required">*</span></label>
              <input
                id="act_date"
                v-model="newActivity.activity_date"
                type="datetime-local"
                required
              />
            </div>

            <div class="form-group">
              <label for="act_remarks">Remarks / Process Notes</label>
              <textarea
                id="act_remarks"
                v-model="newActivity.remarks"
                rows="2"
                placeholder="Add optional processing remarks..."
              ></textarea>
            </div>

            <div class="act-form-footer">
              <button class="btn-submit-act" type="submit" :disabled="submittingAct">
                {{ submittingAct ? 'Saving...' : 'Save Activity Event' }}
              </button>
            </div>
          </form>

          <!-- Timeline Items -->
          <div v-if="!data.activities || data.activities.length === 0" class="empty-timeline">
            <p>No activity history logged yet.</p>
          </div>

          <div v-else class="timeline-list">
            <div
              v-for="act in data.activities"
              :key="act.id"
              class="timeline-item"
            >
              <div class="timeline-dot" />
              <div class="timeline-content">
                <div class="timeline-top">
                  <span class="act-type">{{ act.activity_type }}</span>
                  <span class="act-date">{{ formatDateTime(act.activity_date) }}</span>
                </div>
                <p v-if="act.remarks" class="act-remarks">{{ act.remarks }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button class="btn-edit" type="button" @click="$emit('edit', data)">
          <ion-icon :icon="createOutline" />
          <span>Edit Record</span>
        </button>
        <button class="btn-close" type="button" @click="close">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { IonIcon } from '@ionic/vue'
import { addOutline, createOutline } from 'ionicons/icons'
import type { Communication, CommunicationActivityPayload } from '../../types/communication'
import { addCommunicationActivity } from '../../services/communicationService'

const props = defineProps<{
  isOpen: boolean;
  data: Communication | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'edit', record: Communication): void;
  (e: 'refresh'): void;
}>()

const showAddActivity = ref(false)
const submittingAct = ref(false)

const newActivity = reactive<CommunicationActivityPayload>({
  communication_id: 0,
  activity_type: '',
  activity_date: new Date().toISOString().slice(0, 16),
  remarks: ''
})

watch(() => props.data, (newVal) => {
  if (newVal) {
    newActivity.communication_id = newVal.id
    newActivity.activity_type = ''
    newActivity.activity_date = new Date().toISOString().slice(0, 16)
    newActivity.remarks = ''
    showAddActivity.value = false
  }
})

async function submitNewActivity() {
  if (!newActivity.communication_id || !newActivity.activity_type) return

  submittingAct.value = true
  const res = await addCommunicationActivity(newActivity)
  submittingAct.value = false

  if (res.success) {
    showAddActivity.value = false
    emit('refresh')
  } else {
    alert(res.message || 'Failed to add activity log.')
  }
}

function formatDate(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr
  const day = date.getDate().toString().padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[date.getMonth()]
  const year = date.getFullYear()
  return `${day} ${month} ${year}`
}

function formatDateTime(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getStatusClass(status?: string): string {
  if (!status) return 'status-pending'
  const s = status.toLowerCase()
  if (s.includes('completed') || s.includes('released') || s.includes('approved')) return 'status-completed'
  if (s.includes('progress') || s.includes('processing')) return 'status-ongoing'
  return 'status-pending'
}

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
  max-width: 680px;
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

.header-title-box {
  display: flex;
  align-items: center;
  gap: 10px;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.type-badge {
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
}

.badge-incoming { background: #eff6ff; color: #2563eb; }
.badge-outgoing { background: #f0fdf4; color: #16a34a; }

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
  gap: 20px;
  max-height: 75vh;
  overflow-y: auto;
}

.subject-banner {
  background: #f8fafc;
  border-radius: 10px;
  padding: 16px;
  border: 1px solid #e2e8f0;
}

.subject-banner h4 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 10px 0;
}

.banner-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.status-pending { background: #fff7ed; color: #c2410c; }
.status-ongoing { background: #eff6ff; color: #1d4ed8; }
.status-completed { background: #f0fdf4; color: #15803d; }

.age-pill {
  font-size: 13px;
  color: #475569;
  background: #ffffff;
  padding: 3px 10px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
}

.meta-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.meta-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.meta-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.meta-value {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.timeline-section {
  border-top: 1px solid #e2e8f0;
  padding-top: 20px;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.timeline-header h4 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.add-act-btn {
  background: #f1f5f9;
  color: #2563eb;
  border: 1px solid #cbd5e1;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.add-activity-box {
  background: #f8fafc;
  border-radius: 10px;
  padding: 16px;
  border: 1px solid #cbd5e1;
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group label {
  font-size: 12px;
  font-weight: 600;
  color: #334155;
}

.form-group input, .form-group textarea {
  padding: 8px 10px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  outline: none;
}

.act-form-footer {
  display: flex;
  justify-content: flex-end;
}

.btn-submit-act {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.empty-timeline {
  padding: 16px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}

.timeline-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  padding-left: 12px;
}

.timeline-item {
  display: flex;
  gap: 12px;
  position: relative;
}

.timeline-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #2563eb;
  margin-top: 4px;
  flex-shrink: 0;
}

.timeline-content {
  background: #f8fafc;
  border-radius: 8px;
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  flex: 1;
}

.timeline-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.act-type {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.act-date {
  font-size: 11px;
  color: #64748b;
}

.act-remarks {
  font-size: 13px;
  color: #475569;
  margin: 4px 0 0 0;
  line-height: 1.4;
}

.modal-footer {
  padding: 16px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-edit {
  background: #ffffff;
  color: #16a34a;
  border: 1px solid #86efac;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-close {
  background: #64748b;
  color: #ffffff;
  border: none;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
</style>
