<template>
  <MainLayout :title="pageTitle">
    <div class="equipment-container">
      
      <!-- Top Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>{{ pageTitle }}</h2>
          <p class="subtitle">{{ pageSubtitle }}</p>
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

      <!-- Search & Filter Controls Bar -->
      <div class="table-filter-toolbar">
        <div class="search-box-input">
          <ion-icon :icon="searchOutline" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search description, serial number, equipment type, office..."
          />
        </div>

        <div class="filters-group-row">
          <select v-model.number="filterOfficeId" class="filter-select">
            <option :value="0">-Select Office-</option>
            <option v-for="off in officeList" :key="off.id" :value="off.id">
              {{ off.office_name ? `${off.office_abbv} (${off.office_name})` : off.office_abbv }}
            </option>
          </select>

          <select v-model.number="filterStatusId" class="filter-select">
            <option :value="0">-Select Status-</option>
            <option v-for="st in equipmentStatuses" :key="st.id" :value="st.id">
              {{ st.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Equipment Table Card -->
      <div class="table-card">
        <div class="table-card-header">
          <h3>Equipment Records ({{ totalItems }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading equipment records...</p>
        </div>

        <div v-else-if="totalItems === 0" class="empty-state">
          <p>No equipment records found for this period.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th class="sortable-th" @click="toggleSort('office_abbv')">
                  <div class="th-content">
                    <span>Office</span>
                    <ion-icon :icon="getSortIcon('office_abbv')" :class="['sort-icon', sortKey === 'office_abbv' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th v-if="categoryScope === 'All'" class="sortable-th" @click="toggleSort('equipment_type_name')">
                  <div class="th-content">
                    <span>Equipment Type</span>
                    <ion-icon :icon="getSortIcon('equipment_type_name')" :class="['sort-icon', sortKey === 'equipment_type_name' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('equipment_subtype_name')">
                  <div class="th-content">
                    <span>Equipment Subtype</span>
                    <ion-icon :icon="getSortIcon('equipment_subtype_name')" :class="['sort-icon', sortKey === 'equipment_subtype_name' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('description')">
                  <div class="th-content">
                    <span>Description</span>
                    <ion-icon :icon="getSortIcon('description')" :class="['sort-icon', sortKey === 'description' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('serial_number')">
                  <div class="th-content">
                    <span>Serial Number</span>
                    <ion-icon :icon="getSortIcon('serial_number')" :class="['sort-icon', sortKey === 'serial_number' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="sortable-th" @click="toggleSort('date_acquired')">
                  <div class="th-content">
                    <span>Date Acquired</span>
                    <ion-icon :icon="getSortIcon('date_acquired')" :class="['sort-icon', sortKey === 'date_acquired' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center sortable-th" @click="toggleSort('status_name')">
                  <div class="th-content justify-center">
                    <span>Status</span>
                    <ion-icon :icon="getSortIcon('status_name')" :class="['sort-icon', sortKey === 'status_name' ? 'active-sort' : '']" />
                  </div>
                </th>
                <th class="text-center" style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedItems" :key="item.id" class="clickable-row" @click="navigateToEquipmentDetail(item)">
                <td>
                  <span class="office-tag" :title="item.office_name">{{ item.office_abbv }}</span>
                </td>
                <td v-if="categoryScope === 'All'" class="font-semibold">{{ item.equipment_type_name || item.equipment_type }}</td>
                <td class="font-bold text-primary">{{ item.equipment_subtype_name || item.equipment_subtype }}</td>
                <td>{{ item.description || '-' }}</td>
                <td class="code-text">{{ item.serial_number || 'N/A' }}</td>
                <td>{{ formatDate(item.date_acquired) }}</td>
                <td class="text-center">
                  <span :class="['status-badge', getStatusClass(item.status_name || item.status || '')]">
                    {{ item.status_name || item.status }}
                  </span>
                </td>
                <td class="text-center" @click.stop>
                  <div class="action-buttons">
                    <button class="icon-btn view-btn" title="View Equipment Details" @click="navigateToEquipmentDetail(item)">
                      <ion-icon :icon="eyeOutline" />
                    </button>
                    <button v-if="periodInfo?.is_current" class="icon-btn edit-btn" title="Edit Equipment" @click="navigateToEquipmentDetail(item)">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button v-if="periodInfo?.is_current" class="icon-btn delete-btn" title="Delete Equipment" @click="openDeleteModal(item)">
                      <ion-icon :icon="trashOutline" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- 10-Item Limit Pagination -->
          <TablePagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total-items="totalItems"
            :start-index="startIndex"
            :end-index="endIndex"
            @change-page="setPage"
          />
        </div>
      </div>

      <!-- Equipment Details Modal (View Mode) -->
      <div v-if="showDetailModal" class="modal-backdrop">
        <div class="modal-card detail-card">
          <div class="modal-header">
            <div>
              <h3>{{ detailItem?.equipment_subtype_name || detailItem?.equipment_subtype }} Details</h3>
              <p class="modal-subtitle">{{ detailItem?.equipment_type_name || detailItem?.equipment_type }} Equipment</p>
            </div>
            <button type="button" class="close-btn" @click="showDetailModal = false">&times;</button>
          </div>

          <div v-if="loadingDetail" class="modal-body loading-state">
            <span class="spinner"></span>
            <p>Loading details...</p>
          </div>

          <div v-else-if="detailItem" class="modal-body detail-body">
            <!-- Section 1: General Information -->
            <div class="detail-section">
              <h4 class="section-title">General Information</h4>
              <div class="detail-grid">
                <div class="detail-field">
                  <span class="field-label">Office Assignment:</span>
                  <span class="field-value font-bold">{{ detailItem.office_abbv }} — {{ detailItem.office_name }}</span>
                </div>
                <div class="detail-field">
                  <span class="field-label">Equipment Type:</span>
                  <span class="field-value">{{ detailItem.equipment_type_name || detailItem.equipment_type }}</span>
                </div>
                <div class="detail-field">
                  <span class="field-label">Equipment Subtype:</span>
                  <span class="field-value font-bold text-primary">{{ detailItem.equipment_subtype_name || detailItem.equipment_subtype }}</span>
                </div>
                <div class="detail-field">
                  <span class="field-label">Serial Number:</span>
                  <span class="field-value code-text">{{ detailItem.serial_number || 'N/A' }}</span>
                </div>
                <div class="detail-field">
                  <span class="field-label">Date Acquired:</span>
                  <span class="field-value">{{ formatDate(detailItem.date_acquired) }}</span>
                </div>
                <div class="detail-field">
                  <span class="field-label">Status / Condition:</span>
                  <span :class="['status-badge', getStatusClass(detailItem.status_name || detailItem.status || '')]">
                    {{ detailItem.status_name || detailItem.status }}
                  </span>
                </div>
                <div class="detail-field full-width" v-if="detailItem.description">
                  <span class="field-label">Description:</span>
                  <span class="field-value">{{ detailItem.description }}</span>
                </div>
              </div>
            </div>

            <!-- Section 2: Dynamic Equipment Details -->
            <div class="detail-section">
              <h4 class="section-title">Equipment Specifications / Details</h4>
              <div v-if="!detailItem.attributes || detailItem.attributes.length === 0" class="no-attributes">
                <p>No specific attribute details configured for this {{ detailItem.equipment_subtype_name || detailItem.equipment_subtype }}.</p>
              </div>
              <div v-else class="detail-grid">
                <div v-for="attr in detailItem.attributes" :key="attr.attribute_definition_id" class="detail-field">
                  <span class="field-label">{{ attr.attribute_name }}:</span>
                  <span class="field-value font-semibold">{{ attr.display_value }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="cancel-btn" @click="showDetailModal = false">Close</button>
          </div>
        </div>
      </div>

      <!-- Add / Edit Equipment Modal -->
      <div v-if="showModal" class="modal-backdrop">
        <div class="modal-card form-card">
          <div class="modal-header">
            <h3>{{ isEditMode ? 'Edit Equipment Record' : 'Add New Equipment' }}</h3>
            <button type="button" class="close-btn" @click="showModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveEquipment" class="modal-body">
            <!-- Cascading Selection: Equipment Type -->
            <div class="form-group">
              <label for="eqType">Equipment Type <span class="required-star">*</span></label>
              <select
                id="eqType"
                v-model="formPayload.equipment_type_id"
                @change="handleTypeChange"
                required
                class="input-select"
              >
                <option value="0" disabled>Select Equipment Type...</option>
                <option v-for="t in equipmentTypes" :key="t.id" :value="t.id">
                  {{ t.name }}
                </option>
              </select>
            </div>

            <!-- Cascading Selection: Equipment Subtype -->
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
                <option value="0" disabled>Select Equipment Subtype...</option>
                <option v-for="st in filteredSubtypes" :key="st.id" :value="st.id">
                  {{ st.name }}
                </option>
              </select>
            </div>

            <!-- Office Selection -->
            <div class="form-group">
              <label for="eqOffice">Office Assignment <span class="required-star">*</span></label>
              <select id="eqOffice" v-model="formPayload.office_id" required class="input-select">
                <option value="0" disabled>Select Office...</option>
                <option v-for="off in officeList" :key="off.id" :value="off.id">
                  {{ off.office_abbv }}{{ off.office_name ? ' — ' + off.office_name : '' }}
                </option>
              </select>
            </div>

            <!-- Serial Number -->
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

            <!-- Status Dropdown -->
            <div class="form-group">
              <label for="eqStatus">Status / Condition <span class="required-star">*</span></label>
              <select id="eqStatus" v-model="formPayload.status_id" required class="input-select">
                <option value="0" disabled>Select Status...</option>
                <option v-for="s in equipmentStatuses" :key="s.id" :value="s.id">
                  {{ s.name }}
                </option>
              </select>
            </div>

            <!-- Description -->
            <div class="form-group">
              <label for="eqDesc">Description (Optional)</label>
              <textarea
                id="eqDesc"
                v-model="formPayload.description"
                rows="2"
                placeholder="Brief model or overview notes..."
                class="input-textarea"
              ></textarea>
            </div>

            <!-- Dynamic Equipment Details Fields -->
            <div v-if="attributeDefs.length > 0" class="attributes-form-section">
              <h4 class="section-subheading">Equipment Details ({{ currentSubtypeName }})</h4>
              
              <div v-for="def in attributeDefs" :key="def.id" class="form-group">
                <label :for="'attr_' + def.id">
                  {{ def.attribute_name }}
                  <span v-if="def.is_required" class="required-star">*</span>
                </label>

                <!-- Render by data_type -->
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
            <p>Are you sure you want to delete the equipment record <strong>{{ targetEquipment?.equipment_subtype_name || targetEquipment?.equipment_subtype }}</strong> (SN: {{ targetEquipment?.serial_number || 'N/A' }})?</p>
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
import { useRoute, useRouter } from 'vue-router'
import { ref, reactive, computed, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  calendarOutline,
  timeOutline,
  addOutline,
  eyeOutline,
  createOutline,
  trashOutline,
  searchOutline,
  swapVerticalOutline,
  chevronUpOutline,
  chevronDownOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { useTablePagination } from '../../composables/useTablePagination'
import TablePagination from '../../components/common/TablePagination.vue'
import {
  fetchReportingPeriods,
  fetchEquipmentList,
  fetchEquipmentDetail,
  fetchReferenceOptions,
  fetchAttributeDefinitions,
  fetchOffices,
  createEquipment,
  updateEquipment,
  deleteEquipment
} from '../../services/inventoryService'
import { formatDate } from '../../utils/dateUtils'
import type {
  ReportingPeriod,
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

function navigateToEquipmentDetail(item: EquipmentItem) {
  router.push(`/inventory/equipment/detail/${item.id}`)
}

const categoryScope = computed(() => {
  if (route.path.endsWith('/ict')) return 'ICT'
  if (route.path.endsWith('/communications')) return 'Communications'
  return 'All'
})

const pageTitle = computed(() => {
  if (categoryScope.value === 'ICT') return 'ICT Equipment Registry'
  if (categoryScope.value === 'Communications') return 'Communications Equipment Registry'
  return 'Equipment Registry'
})

const pageSubtitle = computed(() => {
  if (categoryScope.value === 'ICT') return 'Complete ICT equipment inventory listings for all offices.'
  if (categoryScope.value === 'Communications') return 'Complete communications equipment inventory listings for all offices.'
  return 'Complete equipment inventory listings for all offices.'
})

const periods = ref<ReportingPeriod[]>([])
const selectedPeriod = ref('')
const equipmentList = ref<EquipmentItem[]>([])
const officeList = ref<OfficeItem[]>([])
const equipmentTypes = ref<EquipmentType[]>([])
const equipmentSubtypes = ref<EquipmentSubtype[]>([])
const equipmentStatuses = ref<EquipmentStatusOption[]>([])

const searchQuery = ref('')
const filterOfficeId = ref(0)
const filterStatusId = ref(0)

const filteredEquipment = computed(() => {
  return equipmentList.value.filter(item => {
    // Route Subpage Filtering (ICT vs Communications)
    if (categoryScope.value === 'ICT') {
      const typeStr = (item.equipment_type_name || item.equipment_type || '').toUpperCase()
      if (item.equipment_type_id !== 1 && !typeStr.includes('ICT')) return false
    } else if (categoryScope.value === 'Communications') {
      const typeStr = (item.equipment_type_name || item.equipment_type || '').toUpperCase()
      if (item.equipment_type_id !== 2 && !typeStr.includes('COMM')) return false
    }

    if (filterOfficeId.value > 0 && item.office_id !== filterOfficeId.value) return false
    if (filterStatusId.value > 0 && item.status_id !== filterStatusId.value) return false
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const matchDesc = (item.description || '').toLowerCase().includes(q)
      const matchSerial = (item.serial_number || '').toLowerCase().includes(q)
      const matchType = (item.equipment_type_name || item.equipment_type || '').toLowerCase().includes(q)
      const matchSubtype = (item.equipment_subtype_name || item.equipment_subtype || '').toLowerCase().includes(q)
      const matchOffice = (item.office_abbv || item.office_name || '').toLowerCase().includes(q)
      if (!matchDesc && !matchSerial && !matchType && !matchSubtype && !matchOffice) return false
    }
    return true
  })
})

const {
  currentPage,
  totalItems,
  totalPages,
  startIndex,
  endIndex,
  sortKey,
  sortOrder,
  paginatedItems,
  toggleSort,
  setPage
} = useTablePagination(filteredEquipment, { pageSize: 10, defaultSortKey: 'office_abbv', defaultSortOrder: 'asc' })

function getSortIcon(key: string) {
  if (sortKey.value !== key) return swapVerticalOutline
  return sortOrder.value === 'asc' ? chevronUpOutline : chevronDownOutline
}

const periodInfo = ref<{ period_label: string; is_current: boolean } | null>(null)
const loading = ref(true)
const loadingDetail = ref(false)
const saving = ref(false)

const showModal = ref(false)
const showDetailModal = ref(false)
const showDeleteModal = ref(false)
const isEditMode = ref(false)

const targetEquipment = ref<EquipmentItem | null>(null)
const detailItem = ref<EquipmentItem | null>(null)
const attributeDefs = ref<AttributeDefinition[]>([])
const modalError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'success' | 'error'>('success')

const formPayload = reactive<EquipmentFormPayload>({
  office_id: 0,
  equipment_type_id: 0,
  equipment_subtype_id: 0,
  status_id: 0,
  description: '',
  serial_number: '',
  date_acquired: new Date().toISOString().slice(0, 10),
  attributes: {}
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

async function loadData() {
  loading.value = true
  
  // Load reporting periods
  const periodRes = await fetchReportingPeriods()
  if (periodRes.success && periodRes.data.length > 0) {
    periods.value = periodRes.data
    if (!selectedPeriod.value) {
      selectedPeriod.value = periods.value[0].year_month
    }
  }

  // Load equipment records for period
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

  // Load reference options (Types, Subtypes, Statuses)
  const refRes = await fetchReferenceOptions()
  if (refRes.success) {
    equipmentTypes.value = refRes.data.equipment_types
    equipmentSubtypes.value = refRes.data.equipment_subtypes
    equipmentStatuses.value = refRes.data.statuses
  }

  // Load offices
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

function handleTypeChange() {
  // Reset subtype and attribute definitions when equipment type changes
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

async function openAddModal() {
  isEditMode.value = false
  targetEquipment.value = null
  modalError.value = ''
  attributeDefs.value = []
  
  formPayload.id = undefined
  formPayload.office_id = officeList.value.length > 0 ? officeList.value[0].id : 0
  formPayload.equipment_type_id = equipmentTypes.value.length > 0 ? equipmentTypes.value[0].id : 0
  formPayload.description = ''
  formPayload.serial_number = ''
  formPayload.date_acquired = new Date().toISOString().slice(0, 10)
  formPayload.status_id = equipmentStatuses.value.length > 0 ? equipmentStatuses.value[0].id : 0
  formPayload.attributes = {}

  if (formPayload.equipment_type_id > 0) {
    const subs = equipmentSubtypes.value.filter(st => Number(st.equipment_type_id) === Number(formPayload.equipment_type_id))
    if (subs.length > 0) {
      formPayload.equipment_subtype_id = subs[0].id
      await handleSubtypeChange()
    }
  }

  showModal.value = true
}

async function openEditModal(item: EquipmentItem) {
  isEditMode.value = true
  targetEquipment.value = item
  modalError.value = ''
  
  formPayload.id = item.id
  formPayload.office_id = item.office_id
  formPayload.equipment_type_id = item.equipment_type_id
  formPayload.equipment_subtype_id = item.equipment_subtype_id
  formPayload.status_id = item.status_id
  formPayload.description = item.description || ''
  formPayload.serial_number = item.serial_number || ''
  formPayload.date_acquired = item.date_acquired ? item.date_acquired.slice(0, 10) : new Date().toISOString().slice(0, 10)
  formPayload.attributes = {}

  // Fetch attribute definitions & single detail record
  const detailRes = await fetchEquipmentDetail(item.id)
  if (detailRes.success && detailRes.data) {
    const fullItem = detailRes.data
    formPayload.equipment_type_id = fullItem.equipment_type_id
    formPayload.equipment_subtype_id = fullItem.equipment_subtype_id
    formPayload.status_id = fullItem.status_id

    const defRes = await fetchAttributeDefinitions(fullItem.equipment_subtype_id)
    if (defRes.success) {
      attributeDefs.value = defRes.data
      const attrsMap = fullItem.attributes_map || {}
      defRes.data.forEach(def => {
        formPayload.attributes[def.id] = attrsMap[def.id] !== undefined ? attrsMap[def.id] : undefined
      })
    }
  }

  showModal.value = true
}

async function openDetailModal(item: EquipmentItem) {
  loadingDetail.value = true
  showDetailModal.value = true
  detailItem.value = null

  const res = await fetchEquipmentDetail(item.id)
  if (res.success && res.data) {
    detailItem.value = res.data
  }
  loadingDetail.value = false
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
  if (!formPayload.equipment_type_id || formPayload.equipment_type_id <= 0) {
    modalError.value = 'Equipment type is required.'
    return
  }
  if (!formPayload.equipment_subtype_id || formPayload.equipment_subtype_id <= 0) {
    modalError.value = 'Equipment subtype is required.'
    return
  }
  if (!formPayload.status_id || formPayload.status_id <= 0) {
    modalError.value = 'Equipment status is required.'
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

  // Required attribute validation
  for (const def of attributeDefs.value) {
    if (def.is_required) {
      const val = formPayload.attributes[def.id]
      if (val === undefined || val === null || String(val).trim() === '') {
        modalError.value = `Attribute field '${def.attribute_name}' is required.`
        return
      }
    }
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

.clickable-row {
  cursor: pointer;
  transition: background 0.15s ease;
}

.clickable-row:hover {
  background: #f8fafc;
}

.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; }
.text-primary { color: #2563eb; }
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

.view-btn { color: #0284c7; }
.view-btn:hover { background: #f0f9ff; border-color: #7dd3fc; }

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
  max-width: 580px;
  max-height: 90vh;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.detail-card { max-width: 640px; }
.delete-card { max-width: 440px; }
.form-card { max-width: 580px; }

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

.modal-subtitle {
  font-size: 12px;
  color: #64748b;
  margin: 2px 0 0 0;
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
  overflow-y: auto;
}

.detail-body {
  gap: 24px;
}

.detail-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  padding-bottom: 6px;
  border-bottom: 2px solid #e2e8f0;
}

.section-subheading {
  font-size: 14px;
  font-weight: 700;
  color: #1e293b;
  margin: 8px 0 4px 0;
  padding-top: 12px;
  border-top: 1px dashed #cbd5e1;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px 20px;
}

.detail-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.full-width { grid-column: span 2; }

.field-label {
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.field-value {
  font-size: 14px;
  color: #0f172a;
}

.no-attributes {
  font-size: 13px;
  color: #64748b;
  font-style: italic;
}

.attributes-form-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
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
  padding: 16px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.cancel-btn {
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  border: none;
  cursor: pointer;
}

.save-btn {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 700;
  border-radius: 8px;
  background: #2563eb;
  color: #ffffff;
  border: none;
  cursor: pointer;
}

.save-btn:hover { background: #1d4ed8; }

.confirm-delete-btn {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 700;
  border-radius: 8px;
  background: #dc2626;
  color: #ffffff;
  border: none;
  cursor: pointer;
}

.confirm-delete-btn:hover { background: #b91c1c; }
</style>