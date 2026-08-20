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
            </div>

            <!-- Description -->
            <div class="form-group full-width mt-16">
              <label for="eqDesc">Equipment Description / Model Specs</label>
              <textarea
                id="eqDesc"
                v-model="formPayload.description"
                rows="3"
                placeholder="Enter complete technical specifications, brand, model..."
                class="textarea-input"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- CARD 2: Dynamic Attribute Specifications -->
        <div class="form-card">
          <div class="card-header border-header">
            <div>
              <h3>Extensible Specifications</h3>
              <p class="section-subheading">Dynamic attribute fields configured for {{ currentSubtypeName }}.</p>
            </div>
          </div>

          <div class="card-body">
            <div v-if="attributeDefs.length === 0" class="no-attributes-box">
              <p>No specific attribute fields configured for this equipment subtype ({{ currentSubtypeName }}).</p>
            </div>

            <div v-else class="form-grid">
              <div
                v-for="attr in attributeDefs"
                :key="attr.id"
                class="form-group"
              >
                <label :for="'attr_' + attr.id">
                  {{ attr.attribute_name }}
                  <span v-if="attr.is_required" class="required-star">*</span>
                </label>

                <!-- Data Type: Text -->
                <input
                  v-if="attr.data_type === 'text'"
                  :id="'attr_' + attr.id"
                  v-model="attributesForm[attr.id]"
                  type="text"
                  :placeholder="'Enter ' + attr.attribute_name.toLowerCase()"
                  :required="Boolean(attr.is_required)"
                  class="input-text"
                />

                <!-- Data Type: Number -->
                <input
                  v-else-if="attr.data_type === 'number'"
                  :id="'attr_' + attr.id"
                  v-model.number="attributesForm[attr.id]"
                  type="number"
                  step="1"
                  :placeholder="'Enter ' + attr.attribute_name.toLowerCase()"
                  :required="Boolean(attr.is_required)"
                  class="input-text"
                />

                <!-- Data Type: Decimal -->
                <input
                  v-else-if="attr.data_type === 'decimal'"
                  :id="'attr_' + attr.id"
                  v-model.number="attributesForm[attr.id]"
                  type="number"
                  step="0.01"
                  :placeholder="'Enter ' + attr.attribute_name.toLowerCase()"
                  :required="Boolean(attr.is_required)"
                  class="input-text"
                />

                <!-- Data Type: Date -->
                <input
                  v-else-if="attr.data_type === 'date'"
                  :id="'attr_' + attr.id"
                  v-model="attributesForm[attr.id]"
                  type="date"
                  :required="Boolean(attr.is_required)"
                  class="input-text"
                />

                <!-- Data Type: Boolean (Dropdown Yes / No) -->
                <select
                  v-else-if="attr.data_type === 'boolean'"
                  :id="'attr_' + attr.id"
                  v-model="attributesForm[attr.id]"
                  :required="Boolean(attr.is_required)"
                  class="input-select"
                >
                  <option :value="undefined">-- Select --</option>
                  <option :value="true">Yes</option>
                  <option :value="false">No</option>
                </select>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import { saveOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import {
  fetchEquipmentDetail,
  fetchOffices,
  fetchReferenceOptions,
  fetchAttributeDefinitions,
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

const equipmentId = computed(() => Number(route.params.id) || 0)

const loading = ref(true)
const saving = ref(false)
const pageError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'toast-success' | 'toast-error'>('toast-success')

const equipmentItem = ref<EquipmentItem | null>(null)
const officeList = ref<OfficeItem[]>([])
const equipmentTypes = ref<EquipmentType[]>([])
const equipmentSubtypes = ref<EquipmentSubtype[]>([])
const equipmentStatuses = ref<EquipmentStatusOption[]>([])
const attributeDefs = ref<AttributeDefinition[]>([])

const formPayload = reactive<EquipmentFormPayload>({
  id: 0,
  office_id: 0,
  equipment_type_id: 0,
  equipment_subtype_id: 0,
  status_id: 0,
  serial_number: '',
  description: '',
  date_acquired: '',
  attributes: {}
})

const attributesForm = reactive<Record<number, any>>({})

const pageHeading = computed(() => {
  if (equipmentItem.value) {
    const sn = equipmentItem.value.serial_number || 'Equipment'
    const sub = equipmentItem.value.equipment_subtype_name || 'Details'
    return `${sub} (${sn})`
  }
  return 'Equipment Specifications'
})

const filteredSubtypes = computed(() => {
  if (formPayload.equipment_type_id <= 0) return []
  return equipmentSubtypes.value.filter(st => st.equipment_type_id === formPayload.equipment_type_id)
})

const currentSubtypeName = computed(() => {
  const st = equipmentSubtypes.value.find(s => s.id === formPayload.equipment_subtype_id)
  return st ? st.name : 'Subtype'
})

function showToast(msg: string, type: 'toast-success' | 'toast-error' = 'toast-success') {
  feedbackMessage.value = msg
  feedbackType.value = type
  setTimeout(() => {
    feedbackMessage.value = ''
  }, 4000)
}

function goBack() {
  router.back()
}

async function loadData() {
  loading.value = true
  pageError.value = ''

  try {
    const [offRes, refRes, eqRes] = await Promise.all([
      fetchOffices(),
      fetchReferenceOptions(),
      fetchEquipmentDetail(equipmentId.value)
    ])

    if (offRes.success && offRes.data) {
      officeList.value = offRes.data
    }
    if (refRes.success && refRes.data) {
      equipmentTypes.value = refRes.data.equipment_types || []
      equipmentSubtypes.value = refRes.data.equipment_subtypes || []
      equipmentStatuses.value = refRes.data.statuses || []
    }

    if (eqRes.success && eqRes.data) {
      const item = eqRes.data
      equipmentItem.value = item

      formPayload.id = item.id
      formPayload.office_id = item.office_id
      formPayload.equipment_type_id = item.equipment_type_id
      formPayload.equipment_subtype_id = item.equipment_subtype_id
      formPayload.status_id = item.status_id
      formPayload.serial_number = item.serial_number || ''
      formPayload.description = item.description || ''
      formPayload.date_acquired = item.date_acquired || ''

      if (item.equipment_subtype_id > 0) {
        const attrRes = await fetchAttributeDefinitions(item.equipment_subtype_id)
        if (attrRes.success && attrRes.data) {
          attributeDefs.value = attrRes.data
        }
      }

      if (item.attributes && Array.isArray(item.attributes)) {
        item.attributes.forEach((attr: any) => {
          attributesForm[attr.attribute_definition_id] = attr.value
        })
      }
    } else {
      pageError.value = eqRes.message || 'Equipment record not found.'
    }
  } catch (err: any) {
    pageError.value = err.message || 'Failed to load equipment specifications.'
  } finally {
    loading.value = false
  }
}

async function handleTypeChange() {
  formPayload.equipment_subtype_id = 0
  attributeDefs.value = []
  Object.keys(attributesForm).forEach(k => delete attributesForm[Number(k)])
}

async function handleSubtypeChange() {
  Object.keys(attributesForm).forEach(k => delete attributesForm[Number(k)])
  attributeDefs.value = []

  if (formPayload.equipment_subtype_id > 0) {
    const attrRes = await fetchAttributeDefinitions(formPayload.equipment_subtype_id)
    if (attrRes.success && attrRes.data) {
      attributeDefs.value = attrRes.data
    }
  }
}

async function handleSaveEquipment() {
  if (formPayload.office_id <= 0 || formPayload.equipment_type_id <= 0 || formPayload.equipment_subtype_id <= 0 || formPayload.status_id <= 0) {
    showToast('Please select Office, Equipment Category, Subtype, and Status.', 'toast-error')
    return
  }
  if (!formPayload.serial_number.trim() || !formPayload.date_acquired) {
    showToast('Serial Number and Date Acquired are required.', 'toast-error')
    return
  }

  for (const def of attributeDefs.value) {
    if (def.is_required) {
      const val = attributesForm[def.id]
      if (val === undefined || val === null || String(val).trim() === '') {
        showToast(`Field '${def.attribute_name}' is required.`, 'toast-error')
        return
      }
    }
  }

  saving.value = true
  formPayload.attributes = { ...attributesForm }

  try {
    const res = await updateEquipment(formPayload)
    if (res.success) {
      showToast(res.message || 'Equipment record updated successfully!', 'toast-success')
      await loadData()
    } else {
      showToast(res.message || 'Failed to update equipment record.', 'toast-error')
    }
  } catch (err: any) {
    showToast(err.message || 'Error updating equipment record.', 'toast-error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.equipment-detail-page {
  padding: 24px;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.title-group h2 {
  font-size: 24px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.header-right {
  display: flex;
  gap: 12px;
}

.cancel-btn {
  padding: 9px 18px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cancel-btn:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.save-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  border-radius: 8px;
  border: none;
  background: #2563eb;
  color: #ffffff;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.save-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.toast-feedback {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 500;
  font-size: 14px;
}

.toast-success {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.toast-error {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.error-banner {
  padding: 14px 18px;
  background: #fee2e2;
  color: #991b1b;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 600;
}

.loading-card {
  padding: 60px;
  text-align: center;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
}

.spinner {
  display: inline-block;
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 12px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.detail-content-grid {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.card-header {
  padding: 18px 24px;
  border-bottom: 1px solid #f1f5f9;
}

.card-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.section-subheading {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.card-body {
  padding: 24px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.mt-16 {
  margin-top: 16px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.required-star {
  color: #ef4444;
}

.input-text,
.input-select,
.textarea-input {
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  font-size: 14px;
  color: #1e293b;
  outline: none;
  background: #ffffff;
  transition: all 0.2s ease;
}

.input-text:focus,
.input-select:focus,
.textarea-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.code-font {
  font-family: monospace;
  font-weight: 600;
}

.no-attributes-box {
  padding: 24px;
  text-align: center;
  background: #f8fafc;
  border-radius: 8px;
  color: #64748b;
  font-size: 14px;
}
</style>
