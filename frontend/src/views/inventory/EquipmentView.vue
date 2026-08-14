<template>
  <MainLayout title="Inventory Equipment">
    <div class="equipment-container">
      
      <!-- Top Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>ICT Equipment Registry</h2>
          <p class="subtitle">Complete equipment inventory listings for all offices.</p>
        </div>

        <div class="header-right">
          <button
            v-if="periodInfo?.is_current"
            type="button"
            class="add-btn"
            @click="openAddModal"
          >
            <ion-icon :icon="addOutline" />
            <span>Add Equipment</span>
          </button>

          <div class="period-selector-wrapper">
            <label for="periodSelect" class="period-label">Reporting Period:</label>
            <div class="select-box">
              <ion-icon :icon="calendarOutline" class="calendar-icon" />
              <select
                id="periodSelect"
                v-model="selectedPeriod"
                @change="handlePeriodChange"
                class="period-select"
              >
                <option
                  v-for="p in periods"
                  :key="p.year_month"
                  :value="p.year_month"
                >
                  {{ p.label }} {{ p.is_current ? '(Current)' : '' }}
                </option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Historical Snapshot Indicator Banner -->
      <div v-if="periodInfo && !periodInfo.is_current" class="historical-banner">
        <ion-icon :icon="timeOutline" class="banner-icon" />
        <span>Viewing Historical Equipment Snapshot for <strong>{{ periodInfo.period_label }}</strong> (Read-Only).</span>
      </div>

      <!-- Toast Feedback -->
      <div v-if="feedbackMessage" :class="['toast-feedback', feedbackType]">
        <span>{{ feedbackMessage }}</span>
      </div>

      <!-- Equipment Table Card -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Equipment Records ({{ equipmentList.length }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading equipment records...</p>
        </div>

        <div v-else-if="equipmentList.length === 0" class="empty-state">
          <p>No equipment records found for this period.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Office</th>
                <th>Equipment Type</th>
                <th>Description</th>
                <th>Serial Number</th>
                <th>Date Acquired</th>
                <th class="text-center">Status</th>
                <th v-if="periodInfo?.is_current" class="text-center" style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in equipmentList" :key="item.id">
                <td>
                  <span class="office-tag" :title="item.office_name">{{ item.office_abbv }}</span>
                </td>
                <td class="font-semibold">{{ item.equipment_type }}</td>
                <td>{{ item.description || '-' }}</td>
                <td class="code-text">{{ item.serial_number || 'N/A' }}</td>
                <td>{{ formatDate(item.date_acquired) }}</td>
                <td class="text-center">
                  <span :class="['status-badge', getStatusClass(item.status)]">
                    {{ item.status }}
                  </span>
                </td>
                <td v-if="periodInfo?.is_current" class="text-center">
                  <div class="action-buttons">
                    <button class="icon-btn edit-btn" title="Edit Equipment" @click="openEditModal(item)">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button class="icon-btn delete-btn" title="Delete Equipment" @click="openDeleteModal(item)">
                      <ion-icon :icon="trashOutline" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add / Edit Equipment Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>{{ isEditMode ? 'Edit Equipment Record' : 'Add New Equipment' }}</h3>
            <button type="button" class="close-btn" @click="showModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveEquipment" class="modal-body">
            <div class="form-group">
              <label for="eqOffice">Office <span class="required-star">*</span></label>
              <select id="eqOffice" v-model="formPayload.office_id" required class="input-select">
                <option value="0" disabled>Select Office...</option>
                <option v-for="off in officeList" :key="off.id" :value="off.id">
                  {{ off.office_abbv }}{{ off.office_name ? ' — ' + off.office_name : '' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqType">Equipment Type <span class="required-star">*</span></label>
              <input
                id="eqType"
                v-model="formPayload.equipment_type"
                type="text"
                placeholder="e.g. PAS, Desktop Computer, Radio"
                required
                class="input-text"
              />
            </div>

            <div class="form-group">
              <label for="eqSerial">Serial Number <span class="required-star">*</span></label>
              <input
                id="eqSerial"
                v-model="formPayload.serial_number"
                type="text"
                placeholder="e.g. SN-984210"
                required
                class="input-text"
              />
            </div>

            <div class="form-group">
              <label for="eqDate">Date Acquired <span class="required-star">*</span></label>
              <input
                id="eqDate"
                v-model="formPayload.date_acquired"
                type="date"
                required
                class="input-text"
              />
            </div>

            <div class="form-group">
              <label for="eqStatus">Status <span class="required-star">*</span></label>
              <select id="eqStatus" v-model="formPayload.status" required class="input-select">
                <option value="Serviceable">Serviceable</option>
                <option value="For Repair">For Repair</option>
                <option value="For Turn-In / Unserviceable">For Turn-In / Unserviceable</option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqDesc">Description (Optional)</label>
              <textarea
                id="eqDesc"
                v-model="formPayload.description"
                rows="3"
                placeholder="Detailed specifications or description of equipment..."
                class="input-textarea"
              ></textarea>
            </div>

            <div v-if="modalError" class="modal-error">
              {{ modalError }}
            </div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : (isEditMode ? 'Update Equipment' : 'Save Equipment') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Confirmation Modal -->
      <div v-if="showDeleteModal" class="modal-backdrop">
        <div class="modal-card delete-card">
          <div class="modal-header">
            <h3>Confirm Delete Equipment</h3>
            <button type="button" class="close-btn" @click="showDeleteModal = false">&times;</button>
          </div>

          <div class="modal-body">
            <p>Are you sure you want to delete the equipment record <strong>{{ targetEquipment?.equipment_type }}</strong> (SN: {{ targetEquipment?.serial_number || 'N/A' }})?</p>
            <p class="warning-text">This will remove the item from active inventory calculations.</p>

            <div v-if="modalError" class="modal-error">
              {{ modalError }}
            </div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showDeleteModal = false">Cancel</button>
              <button type="button" class="confirm-delete-btn" :disabled="saving" @click="handleConfirmDelete">
                {{ saving ? 'Deleting...' : 'Delete Equipment' }}
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  calendarOutline,
  timeOutline,
  addOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import {
  fetchReportingPeriods,
  fetchEquipmentList,
  fetchOffices,
  createEquipment,
  updateEquipment,
  deleteEquipment
} from '../../services/inventoryService'
import { formatDate } from '../../utils/dateUtils'
import type {
  ReportingPeriod,
  EquipmentItem,
  EquipmentStatus,
  OfficeItem,
  EquipmentFormPayload
} from '../../types/inventory'

const periods = ref<ReportingPeriod[]>([])
const selectedPeriod = ref('')
const equipmentList = ref<EquipmentItem[]>([])
const officeList = ref<OfficeItem[]>([])
const periodInfo = ref<{ period_label: string; is_current: boolean } | null>(null)

const loading = ref(true)
const saving = ref(false)
const showModal = ref(false)
const showDeleteModal = ref(false)
const isEditMode = ref(false)

const targetEquipment = ref<EquipmentItem | null>(null)
const modalError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'success' | 'error'>('success')

const formPayload = reactive<EquipmentFormPayload>({
  office_id: 0,
  equipment_type: '',
  description: '',
  serial_number: '',
  date_acquired: new Date().toISOString().slice(0, 10),
  status: 'Serviceable'
})

function showFeedback(msg: string, type: 'success' | 'error' = 'success') {
  feedbackMessage.value = msg
  feedbackType.value = type
  setTimeout(() => {
    feedbackMessage.value = ''
  }, 4000)
}

async function loadData() {
  loading.value = true
  const periodRes = await fetchReportingPeriods()
  if (periodRes.success && periodRes.data.length > 0) {
    periods.value = periodRes.data
    if (!selectedPeriod.value) {
      selectedPeriod.value = periods.value[0].year_month
    }
  }

  if (selectedPeriod.value) {
    const listRes = await fetchEquipmentList(selectedPeriod.value)
    if (listRes.success) {
      equipmentList.value = listRes.data.items
      periodInfo.value = {
        period_label: listRes.data.period_label,
        is_current: listRes.data.is_current
      }
    }
  }

  const officeRes = await fetchOffices()
  if (officeRes.success) {
    officeList.value = officeRes.data
  }

  loading.value = false
}

async function handlePeriodChange() {
  loading.value = true
  const listRes = await fetchEquipmentList(selectedPeriod.value)
  if (listRes.success) {
    equipmentList.value = listRes.data.items
    periodInfo.value = {
      period_label: listRes.data.period_label,
      is_current: listRes.data.is_current
    }
  }
  loading.value = false
}

function openAddModal() {
  isEditMode.value = false
  targetEquipment.value = null
  modalError.value = ''
  formPayload.id = undefined
  formPayload.office_id = officeList.value.length > 0 ? officeList.value[0].id : 0
  formPayload.equipment_type = ''
  formPayload.description = ''
  formPayload.serial_number = ''
  formPayload.date_acquired = new Date().toISOString().slice(0, 10)
  formPayload.status = 'Serviceable'
  showModal.value = true
}

function openEditModal(item: EquipmentItem) {
  isEditMode.value = true
  targetEquipment.value = item
  modalError.value = ''
  formPayload.id = item.id
  formPayload.office_id = item.office_id
  formPayload.equipment_type = item.equipment_type
  formPayload.description = item.description || ''
  formPayload.serial_number = item.serial_number || ''
  formPayload.date_acquired = item.date_acquired ? item.date_acquired.slice(0, 10) : new Date().toISOString().slice(0, 10)
  formPayload.status = item.status
  showModal.value = true
}

function openDeleteModal(item: EquipmentItem) {
  targetEquipment.value = item
  modalError.value = ''
  showDeleteModal.value = true
}

async function handleSaveEquipment() {
  modalError.value = ''
  if (!formPayload.office_id || formPayload.office_id <= 0) {
    modalError.value = 'Please select a valid office.'
    return
  }
  if (!formPayload.equipment_type.trim()) {
    modalError.value = 'Equipment type is required.'
    return
  }
  if (!formPayload.serial_number.trim()) {
    modalError.value = 'Serial number is required.'
    return
  }
  if (!formPayload.date_acquired.trim()) {
    modalError.value = 'Date acquired is required.'
    return
  }

  saving.value = true
  const res = isEditMode.value
    ? await updateEquipment(formPayload)
    : await createEquipment(formPayload)
  saving.value = false

  if (res.success) {
    showModal.value = false
    showFeedback(res.message || (isEditMode.value ? 'Equipment updated successfully.' : 'Equipment created successfully.'))
    handlePeriodChange()
  } else {
    modalError.value = res.message || 'Failed to save equipment record.'
  }
}

async function handleConfirmDelete() {
  if (!targetEquipment.value) return
  saving.value = true
  const res = await deleteEquipment(targetEquipment.value.id)
  saving.value = false

  if (res.success) {
    showDeleteModal.value = false
    showFeedback(res.message || 'Equipment soft-deleted successfully.')
    handlePeriodChange()
  } else {
    modalError.value = res.message || 'Failed to delete equipment record.'
  }
}

function getStatusClass(status: EquipmentStatus): string {
  switch (status) {
    case 'Serviceable':
      return 'status-serviceable'
    case 'For Repair':
      return 'status-repair'
    case 'For Turn-In / Unserviceable':
      return 'status-unserviceable'
    default:
      return ''
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.equipment-container {
  padding: 32px 40px;
  max-width: 1280px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 24px;
}

.header-action-bar h2 {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.add-btn {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.15s ease;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
}

.add-btn:hover { background: #1d4ed8; }

.period-selector-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.period-label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}

.select-box {
  position: relative;
  display: flex;
  align-items: center;
}

.calendar-icon {
  position: absolute;
  left: 12px;
  font-size: 16px;
  color: #64748b;
  pointer-events: none;
}

.period-select {
  padding: 10px 16px 10px 38px;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.period-select:focus {
  border-color: #2563eb;
}

.historical-banner {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}

.banner-icon {
  font-size: 18px;
}

.toast-feedback {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 20px;
}

.toast-feedback.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.toast-feedback.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.loading-state, .empty-state {
  text-align: center;
  padding: 48px;
  color: #64748b;
}

.spinner {
  display: inline-block;
  width: 24px;
  height: 24px;
  border: 3px solid #cbd5e1;
  border-radius: 50%;
  border-top-color: #2563eb;
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.table-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.table-card-header {
  padding: 16px 24px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}

.table-card-header h3 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.table-responsive {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.data-table th {
  background: #f8fafc;
  padding: 12px 20px;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #e2e8f0;
}

.data-table td {
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
}

.font-semibold { font-weight: 600; }
.code-text { font-family: monospace; font-weight: 600; color: #475569; }
.text-center { text-align: center; }

.office-tag {
  background: #f1f5f9;
  color: #0f172a;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
}

.status-serviceable { background: #f0fdf4; color: #16a34a; }
.status-repair { background: #fff7ed; color: #c2410c; }
.status-unserviceable { background: #fef2f2; color: #dc2626; }

.action-buttons {
  display: flex;
  justify-content: center;
  gap: 8px;
}

.icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  font-size: 16px;
}

.edit-btn { color: #2563eb; }
.edit-btn:hover { background: #eff6ff; border-color: #93c5fd; }

.delete-btn { color: #dc2626; }
.delete-btn:hover { background: #fef2f2; border-color: #fca5a5; }

/* Modal Styles */
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
  z-index: 1000;
  padding: 20px;
}

.modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 520px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.delete-card {
  max-width: 440px;
}

.modal-header {
  padding: 18px 24px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 16px;
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
  line-height: 1;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}

.required-star {
  color: #dc2626;
}

.input-text, .input-select, .input-textarea {
  width: 100%;
  padding: 10px 14px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  box-sizing: border-box;
  background: #ffffff;
}

.input-text:focus, .input-select:focus, .input-textarea:focus {
  border-color: #2563eb;
}

.modal-error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}

.warning-text {
  font-size: 13px;
  color: #dc2626;
  font-weight: 600;
  margin-top: 8px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 8px;
}

.cancel-btn {
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
}

.save-btn {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  border: none;
  background: #2563eb;
  color: #ffffff;
  cursor: pointer;
}

.save-btn:disabled, .confirm-delete-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.confirm-delete-btn {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  border: none;
  background: #dc2626;
  color: #ffffff;
  cursor: pointer;
}
</style>