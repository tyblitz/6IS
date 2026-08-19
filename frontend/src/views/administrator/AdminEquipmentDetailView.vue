<template>
  <MainLayout title="Equipment Detail">
    <div class="equipment-detail-page">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div class="header-left">
          <div class="title-group">
            <h2>{{ pageHeading }}</h2>
            <p class="subtitle">View and edit equipment specifications, office assignment, and condition details.</p>
          </div>
        </div>

        <div class="header-right">
          <button type="button" class="cancel-btn" @click="goBack">Cancel</button>
          <button type="button" class="save-btn" :disabled="saving || loading" @click="handleSaveEquipment">
            <ion-icon :icon="saveOutline" />
            <span>{{ saving ? 'Saving Changes...' : 'Save Changes' }}</span>
          </button>
        </div>
      </div>

      <!-- Toast Feedback -->
      <div v-if="feedbackMessage" :class="['toast-feedback', feedbackType]">
        <span>{{ feedbackMessage }}</span>
      </div>

      <!-- Error Banner -->
      <div v-if="pageError" class="error-banner">
        <span>{{ pageError }}</span>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-card">
        <span class="spinner"></span>
        <p>Loading equipment specifications...</p>
      </div>

      <div v-else-if="equipmentItem" class="detail-content-grid">
        
        <!-- CARD 1: General Information -->
        <div class="form-card">
          <div class="card-header">
            <h3>General Information</h3>
          </div>

          <div class="card-body">
            <div class="form-grid">
              <!-- Serial Number -->
              <div class="form-group">
                <label for="eqSerial">Serial Number <span class="required-star">*</span></label>
                <input
                  id="eqSerial"
                  v-model="formPayload.serial_number"
                  type="text"
                  placeholder="e.g. SN-HP400-010"
                  required
                  class="input-text code-font"
                />
              </div>

              <!-- Office Assignment -->
              <div class="form-group">
                <label for="eqOffice">Office Assignment <span class="required-star">*</span></label>
                <select id="eqOffice" v-model="formPayload.office_id" required class="input-select">
                  <option value="0" disabled>Select Office...</option>
                  <option v-for="off in officeList" :key="off.id" :value="off.id">
                    {{ off.office_abbv }}{{ off.office_name ? ' — ' + off.office_name : '' }}
                  </option>
                </select>
              </div>

              <!-- Equipment Category / Type -->
              <div class="form-group">
                <label for="eqType">Equipment Category <span class="required-star">*</span></label>
                <select id="eqType" v-model="formPayload.equipment_type_id" @change="handleTypeChange" required class="input-select">
                  <option value="0" disabled>Select Equipment Category...</option>
                  <option v-for="t in equipmentTypes" :key="t.id" :value="t.id">
                    {{ t.name }}
                  </option>
                </select>
              </div>

              <!-- Equipment Subtype -->
              <div class="form-group">
                <label for="eqSubtype">Equipment Subtype <span class="required-star">*</span></label>
                <select
                  id="eqSubtype"
                  v-model="formPayload.equipment_subtype_id"
                  @change="handleSubtypeChange"
                  :disabled="formPayload.equipment_type_id <= 0"
                  required
                  class="input-select"
                >
                  <option value="0" disabled>Select Subtype...</option>
                  <option v-for="st in filteredSubtypes" :key="st.id" :value="st.id">
                    {{ st.name }}
                  </option>
                </select>
              </div>

              <!-- Date Acquired -->
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

              <!-- Status / Condition -->
              <div class="form-group">
                <label for="eqStatus">Status / Condition <span class="required-star">*</span></label>
                <select id="eqStatus" v-model="formPayload.status_id" required class="input-select">
                  <option value="0" disabled>Select Status...</option>
                  <option v-for="s in equipmentStatuses" :key="s.id" :value="s.id">
                    {{ s.name }}
                  </option>
                </select>
              </div>

              <!-- Remarks -->
              <div class="form-group full-width">
                <label for="eqDesc">Remarks</label>
                <textarea
                  id="eqDesc"
                  v-model="formPayload.description"
                  rows="3"
                  placeholder="Enter remarks..."
                  class="input-textarea"
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD 2: Equipment Specifications / Details -->
        <div class="form-card">
          <div class="card-header">
            <h3>Equipment Specifications & Details</h3>
            <span class="subtype-badge">{{ currentSubtypeName || 'Dynamic Attributes' }}</span>
          </div>

          <div class="card-body">
            <div v-if="attributeDefs.length === 0" class="no-attributes-box">
              <p>No specific attribute fields configured for this equipment subtype ({{ currentSubtypeName }}).</p>
            </div>

            <div v-else class="form-grid">
              <div v-for="def in attributeDefs" :key="def.id" class="form-group">
                <label :for="'attr_' + def.id">
                  {{ def.attribute_name }}
                  <span v-if="def.is_required" class="required-star">*</span>
                </label>

                <!-- Dynamic Input Component by data_type -->
                <template v-if="def.data_type === 'boolean'">
                  <select
                    :id="'attr_' + def.id"
                    v-model="formPayload.attributes[def.id]"
                    class="input-select"
                    :required="Boolean(def.is_required)"
                  >
                    <option :value="undefined">-- Select --</option>
                    <option :value="true">Yes</option>
                    <option :value="false">No</option>
                  </select>
                </template>

                <template v-else-if="def.data_type === 'number'">
                  <input
                    :id="'attr_' + def.id"
                    v-model.number="formPayload.attributes[def.id]"
                    type="number"
                    placeholder="e.g. 1000"
                    class="input-text"
                    :required="Boolean(def.is_required)"
                  />
                </template>

                <template v-else-if="def.data_type === 'decimal'">
                  <input
                    :id="'attr_' + def.id"
                    v-model.number="formPayload.attributes[def.id]"
                    type="number"
                    step="0.01"
                    placeholder="0.00"
                    class="input-text"
                    :required="Boolean(def.is_required)"
                  />
                </template>

                <template v-else-if="def.data_type === 'date'">
                  <input
                    :id="'attr_' + def.id"
                    v-model="formPayload.attributes[def.id]"
                    type="date"
                    class="input-text"
                    :required="Boolean(def.is_required)"
                  />
                </template>

                <template v-else>
                  <input
                    :id="'attr_' + def.id"
                    v-model="formPayload.attributes[def.id]"
                    type="text"
                    placeholder="Enter details..."
                    class="input-text"
                    :required="Boolean(def.is_required)"
                  />
                </template>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Page Action Bar Footer -->
      <div v-if="equipmentItem" class="page-footer-actions">
        <button type="button" class="cancel-btn" @click="goBack">Cancel</button>
        <button type="button" class="save-btn" :disabled="saving || loading" @click="handleSaveEquipment">
          <ion-icon :icon="saveOutline" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Changes' }}</span>
        </button>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  arrowBackOutline,
  saveOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import {
  fetchEquipmentDetail,
  fetchReferenceOptions,
  fetchAttributeDefinitions,
  fetchOffices,
  updateEquipment
} from '../../services/inventoryService'
import type {
  EquipmentItem,
  OfficeItem,
  EquipmentType,
  EquipmentSubtype,
  EquipmentStatusOption,
  AttributeDefinition,
  EquipmentFormPayload
} from '../../types/inventory'

const route = useRoute()
const router = useRouter()
const equipmentId = computed(() => Number(route.params.id))

const equipmentItem = ref<EquipmentItem | null>(null)
const officeList = ref<OfficeItem[]>([])
const equipmentTypes = ref<EquipmentType[]>([])
const equipmentSubtypes = ref<EquipmentSubtype[]>([])
const equipmentStatuses = ref<EquipmentStatusOption[]>([])
const attributeDefs = ref<AttributeDefinition[]>([])

const loading = ref(true)
const saving = ref(false)
const pageError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'success' | 'error'>('success')

const formPayload = reactive<EquipmentFormPayload>({
  id: 0,
  office_id: 0,
  equipment_type_id: 0,
  equipment_subtype_id: 0,
  status_id: 0,
  description: '',
  serial_number: '',
  date_acquired: '',
  attributes: {}
})

const pageHeading = computed(() => {
  if (!equipmentItem.value) return 'Equipment Specifications'
  const sub = equipmentItem.value.equipment_subtype_name || equipmentItem.value.equipment_subtype || 'Equipment'
  const sn = formPayload.serial_number || equipmentItem.value.serial_number || ''
  return `${sub} Details (${sn})`
})

const filteredSubtypes = computed(() => {
  if (formPayload.equipment_type_id <= 0) return []
  return equipmentSubtypes.value.filter(st => Number(st.equipment_type_id) === Number(formPayload.equipment_type_id))
})

const currentSubtypeName = computed(() => {
  const found = equipmentSubtypes.value.find(st => Number(st.id) === Number(formPayload.equipment_subtype_id))
  return found ? found.name : ''
})

function showFeedback(msg: string, type: 'success' | 'error' = 'success') {
  feedbackMessage.value = msg
  feedbackType.value = type
  setTimeout(() => {
    feedbackMessage.value = ''
  }, 4000)
}

function goBack() {
  router.push('/administrator/inventory')
}

async function loadData() {
  loading.value = true
  pageError.value = ''

  if (!equipmentId.value || isNaN(equipmentId.value)) {
    pageError.value = 'Invalid equipment ID specified.'
    loading.value = false
    return
  }

  // Load reference options (Types, Subtypes, Statuses, Offices)
  const [refRes, offRes] = await Promise.all([
    fetchReferenceOptions(),
    fetchOffices()
  ])

  if (refRes.success) {
    equipmentTypes.value = refRes.data.equipment_types
    equipmentSubtypes.value = refRes.data.equipment_subtypes
    equipmentStatuses.value = refRes.data.statuses
  }

  if (offRes.success) {
    officeList.value = offRes.data
  }

  // Load Equipment Detail
  const detailRes = await fetchEquipmentDetail(equipmentId.value)
  if (detailRes.success && detailRes.data) {
    const item = detailRes.data
    equipmentItem.value = item

    formPayload.id = item.id
    formPayload.office_id = item.office_id
    formPayload.equipment_type_id = item.equipment_type_id
    formPayload.equipment_subtype_id = item.equipment_subtype_id
    formPayload.status_id = item.status_id
    formPayload.description = item.description || ''
    formPayload.serial_number = item.serial_number || ''
    formPayload.date_acquired = item.date_acquired ? item.date_acquired.slice(0, 10) : ''
    formPayload.attributes = {}

    // Load Subtype Attributes
    const defRes = await fetchAttributeDefinitions(item.equipment_subtype_id)
    if (defRes.success) {
      attributeDefs.value = defRes.data
      const attrsMap = item.attributes_map || {}
      defRes.data.forEach(def => {
        formPayload.attributes[def.id] = attrsMap[def.id] !== undefined ? attrsMap[def.id] : undefined
      })
    }
  } else {
    pageError.value = detailRes.message || 'Equipment record not found.'
  }

  loading.value = false
}

function handleTypeChange() {
  formPayload.equipment_subtype_id = 0
  attributeDefs.value = []
  formPayload.attributes = {}
}

async function handleSubtypeChange() {
  formPayload.attributes = {}
  if (formPayload.equipment_subtype_id > 0) {
    const defRes = await fetchAttributeDefinitions(formPayload.equipment_subtype_id)
    if (defRes.success) {
      attributeDefs.value = defRes.data
      defRes.data.forEach(def => {
        formPayload.attributes[def.id] = undefined
      })
    }
  } else {
    attributeDefs.value = []
  }
}

async function handleSaveEquipment() {
  pageError.value = ''
  if (!formPayload.office_id || formPayload.office_id <= 0) {
    pageError.value = 'Please select a valid office assignment.'
    return
  }
  if (!formPayload.equipment_type_id || formPayload.equipment_type_id <= 0) {
    pageError.value = 'Equipment category is required.'
    return
  }
  if (!formPayload.equipment_subtype_id || formPayload.equipment_subtype_id <= 0) {
    pageError.value = 'Equipment subtype is required.'
    return
  }
  if (!formPayload.status_id || formPayload.status_id <= 0) {
    pageError.value = 'Equipment status is required.'
    return
  }
  if (!formPayload.serial_number.trim()) {
    pageError.value = 'Serial number is required.'
    return
  }
  if (!formPayload.date_acquired.trim()) {
    pageError.value = 'Date acquired is required.'
    return
  }

  // Required attribute validation
  for (const def of attributeDefs.value) {
    if (def.is_required) {
      const val = formPayload.attributes[def.id]
      if (val === undefined || val === null || String(val).trim() === '') {
        pageError.value = `Specification attribute '${def.attribute_name}' is required.`
        return
      }
    }
  }

  saving.value = true
  const res = await updateEquipment(formPayload)
  saving.value = false

  if (res.success) {
    showFeedback(res.message || 'Equipment details updated successfully.')
    // Reload updated data
    loadData()
  } else {
    pageError.value = res.message || 'Failed to update equipment details.'
  }
}

function getStatusClass(status?: string): string {
  if (!status) return ''
  const s = status.toLowerCase()
  if (s.includes('serviceable') && !s.includes('unserviceable')) return 'status-serviceable'
  if (s.includes('repair')) return 'status-repair'
  if (s.includes('turn-in') || s.includes('unserviceable')) return 'status-unserviceable'
  return ''
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.equipment-detail-page {
  padding: 32px 40px;
  max-width: 1280px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}

.header-left {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.back-link-btn {
  background: none;
  border: none;
  color: #2563eb;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0;
  transition: color 0.15s ease;
}

.back-link-btn:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.title-group h2 {
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
  gap: 12px;
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

.error-banner {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 24px;
}

.loading-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 60px;
  text-align: center;
  color: #64748b;
  border: 1px solid #e2e8f0;
}

.spinner {
  display: inline-block;
  width: 28px;
  height: 28px;
  border: 3px solid #cbd5e1;
  border-radius: 50%;
  border-top-color: #2563eb;
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.detail-content-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  margin-bottom: 32px;
}

@media (max-width: 900px) {
  .detail-content-grid {
    grid-template-columns: 1fr;
  }
}

.form-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.card-header {
  padding: 16px 24px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.header-badge {
  font-size: 12px;
  font-weight: 700;
  background: #e2e8f0;
  color: #475569;
  padding: 4px 10px;
  border-radius: 12px;
}

.subtype-badge {
  font-size: 12px;
  font-weight: 700;
  background: #eff6ff;
  color: #2563eb;
  padding: 4px 10px;
  border-radius: 12px;
}

.card-body {
  padding: 24px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.full-width {
  grid-column: span 2;
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
  transition: border-color 0.15s ease;
}

.code-font {
  font-family: monospace;
  font-weight: 700;
}

.input-text:focus, .input-select:focus, .input-textarea:focus {
  border-color: #2563eb;
}

.no-attributes-box {
  padding: 24px;
  text-align: center;
  color: #64748b;
  font-style: italic;
  background: #f8fafc;
  border-radius: 8px;
}

.page-footer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid #cbd5e1;
}

.cancel-btn {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #cbd5e1;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cancel-btn:hover { background: #e2e8f0; }

.save-btn {
  padding: 10px 22px;
  font-size: 14px;
  font-weight: 700;
  border-radius: 8px;
  background: #082f6d;
  color: #ffffff;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: background 0.15s ease;
  box-shadow: 0 2px 6px rgba(8, 47, 109, 0.2);
}

.save-btn:hover:not(:disabled) { background: #1d4ed8; }

.save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
}

.status-serviceable { background: #f0fdf4; color: #16a34a; }
.status-repair { background: #fff7ed; color: #c2410c; }
.status-unserviceable { background: #fef2f2; color: #dc2626; }
</style>
