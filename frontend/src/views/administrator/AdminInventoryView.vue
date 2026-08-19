<template>
  <MainLayout title="Inventory Management">
    <div class="admin-inventory-container">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Inventory Management</h2>
          <p class="subtitle">Maintain equipment registry, serial numbers, office assignments, and specifications.</p>
        </div>

        <button class="action-main-btn" @click="openAddEquipmentModal">
          <ion-icon :icon="addOutline" />
          <span>Add Equipment</span>
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button
          :class="['tab-btn', activeTab === 'ict' ? 'active-tab' : '']"
          @click="switchTab('ict')"
        >
          <ion-icon :icon="cubeOutline" />
          <span>ICT Equipment</span>
        </button>

        <button
          :class="['tab-btn', activeTab === 'comm' ? 'active-tab' : '']"
          @click="switchTab('comm')"
        >
          <ion-icon :icon="chatbubblesOutline" />
          <span>Communications</span>
        </button>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="filter-controls-card">
        <div class="filter-group search-group">
          <label for="invSearch">Search</label>
          <div class="search-input-wrapper">
            <ion-icon :icon="searchOutline" class="search-icon" />
            <input
              id="invSearch"
              type="text"
              v-model="searchQuery"
              @input="currentPage = 1"
              placeholder="Search serial number, office, subtype, or description..."
              class="input-search"
            />
          </div>
        </div>
      </div>

      <!-- Equipment Card -->
      <div class="table-card">

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading equipment records...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="sortedList.length === 0" class="empty-state">
          <p v-if="searchQuery">No equipment matches your search query "{{ searchQuery }}".</p>
          <p v-else>No {{ activeTab === 'ict' ? 'ICT' : 'Communications' }} equipment records found.</p>
        </div>

        <!-- Table View -->
        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <!-- Column 1: Serial Number (Sortable) -->
                <th class="sortable-th" @click="toggleSort('serial_number')">
                  <div class="th-content">
                    <span>Serial Number</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'serial_number' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'serial_number' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <!-- Column 2: Office (Sortable) -->
                <th class="sortable-th" @click="toggleSort('office')">
                  <div class="th-content">
                    <span>Office</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'office' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'office' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <!-- Column 3: Equipment Subtype (Sortable) -->
                <th class="sortable-th" @click="toggleSort('subtype')">
                  <div class="th-content">
                    <span>Equipment Subtype</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'subtype' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'subtype' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <!-- Column 4: Date Acquired (Sortable) -->
                <th class="sortable-th" @click="toggleSort('date_acquired')">
                  <div class="th-content">
                    <span>Date Acquired</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'date_acquired' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'date_acquired' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <!-- Column 5: Description (Non-Sortable) -->
                <th>Description</th>

                <!-- Column 6: Status (Sortable) -->
                <th class="sortable-th text-center" @click="toggleSort('status')">
                  <div class="th-content center-th">
                    <span>Status</span>
                    <span class="sort-icon">
                      <ion-icon v-if="sortColumn === 'status' && sortDirection === 'asc'" :icon="arrowUpOutline" />
                      <ion-icon v-else-if="sortColumn === 'status' && sortDirection === 'desc'" :icon="arrowDownOutline" />
                      <ion-icon v-else :icon="swapVerticalOutline" class="inactive-sort" />
                    </span>
                  </div>
                </th>

                <!-- Column 7: Actions (Non-Sortable) -->
                <th class="text-center" style="width: 100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedList" :key="item.id">
                <!-- Serial Number as Link to Dedicated Page -->
                <td>
                  <a
                    href="#"
                    class="serial-link"
                    title="Click to view & edit equipment details"
                    @click.prevent="openEquipmentDetail(item)"
                  >
                    {{ item.serial_number || 'N/A' }}
                  </a>
                </td>
                <!-- Office Tag -->
                <td>
                  <span class="office-tag" :title="item.office_name">{{ item.office_abbv }}</span>
                </td>
                <!-- Equipment Subtype -->
                <td class="font-bold text-primary">
                  {{ item.equipment_subtype_name || item.equipment_subtype }}
                </td>
                <!-- Date Acquired -->
                <td>{{ formatDate(item.date_acquired) }}</td>
                <!-- Description -->
                <td class="desc-cell">{{ item.description || '-' }}</td>
                <!-- Status Badge -->
                <td class="text-center">
                  <span :class="['status-badge', getStatusClass(item.status_name || item.status)]">
                    {{ item.status_name || item.status }}
                  </span>
                </td>
                <!-- Icon Only Actions -->
                <td class="text-center">
                  <div class="action-buttons">
                    <button class="icon-btn edit-btn" title="View & Edit Equipment Details" @click="openEquipmentDetail(item)">
                      <ion-icon :icon="createOutline" />
                    </button>
                    <button class="icon-btn delete-btn" title="Delete Equipment" @click="handleSoftDeleteEquipment(item)">
                      <ion-icon :icon="trashOutline" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls (20 per page) -->
        <div v-if="sortedList.length > 0" class="pagination-footer">
          <div class="pagination-info">
            Showing <strong>{{ paginationStart }}</strong> to <strong>{{ paginationEnd }}</strong> of <strong>{{ sortedList.length }}</strong> items
          </div>

          <div v-if="totalPages > 1" class="pagination-controls">
            <button
              type="button"
              class="page-btn nav-btn"
              :disabled="currentPage <= 1"
              @click="currentPage--"
            >
              Previous
            </button>

            <button
              v-for="page in visiblePageNumbers"
              :key="page"
              type="button"
              :class="['page-btn', currentPage === page ? 'active-page' : '']"
              @click="currentPage = page"
            >
              {{ page }}
            </button>

            <button
              type="button"
              class="page-btn nav-btn"
              :disabled="currentPage >= totalPages"
              @click="currentPage++"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- Add Equipment Modal -->
      <div v-if="showEquipmentModal" class="modal-backdrop">
        <div class="modal-card form-card">
          <div class="modal-header">
            <h3>Add New Equipment Record</h3>
            <button class="close-btn" @click="closeEquipmentModal">&times;</button>
          </div>

          <form @submit.prevent="handleSaveEquipment" class="modal-body">
            <div class="form-group">
              <label for="eqType">Equipment Type <span class="required-star">*</span></label>
              <select id="eqType" v-model="eqForm.equipment_type_id" @change="handleTypeChange" class="input-select" required>
                <option value="0" disabled>Select Type...</option>
                <option v-for="t in equipmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqSubtype">Equipment Subtype <span class="required-star">*</span></label>
              <select id="eqSubtype" v-model="eqForm.equipment_subtype_id" @change="handleSubtypeChange" :disabled="eqForm.equipment_type_id <= 0" class="input-select" required>
                <option value="0" disabled>Select Subtype...</option>
                <option v-for="st in filteredSubtypes" :key="st.id" :value="st.id">{{ st.name }}</option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqOffice">Office Assignment <span class="required-star">*</span></label>
              <select id="eqOffice" v-model="eqForm.office_id" class="input-select" required>
                <option value="0" disabled>Select Office...</option>
                <option v-for="off in offices" :key="off.id" :value="off.id">
                  {{ off.office_abbv }}{{ off.office_name ? ' — ' + off.office_name : '' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqSN">Serial Number <span class="required-star">*</span></label>
              <input id="eqSN" v-model="eqForm.serial_number" type="text" placeholder="e.g. SN-HP800-001" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="eqDate">Date Acquired <span class="required-star">*</span></label>
              <input id="eqDate" v-model="eqForm.date_acquired" type="date" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="eqStatus">Condition / Status <span class="required-star">*</span></label>
              <select id="eqStatus" v-model="eqForm.status_id" class="input-select" required>
                <option value="0" disabled>Select Status...</option>
                <option v-for="s in equipmentStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqDesc">Description</label>
              <input id="eqDesc" v-model="eqForm.description" type="text" placeholder="Brief notes..." class="input-text" />
            </div>

            <!-- Dynamic Equipment Details -->
            <div v-if="attributeDefs.length > 0" class="attributes-form-section">
              <h4 class="section-subheading">Equipment Details ({{ currentSubtypeName }})</h4>
              <div v-for="def in attributeDefs" :key="def.id" class="form-group">
                <label :for="'admin_attr_' + def.id">
                  {{ def.attribute_name }}
                  <span v-if="def.is_required" class="required-star">*</span>
                </label>
                <template v-if="def.data_type === 'boolean'">
                  <select :id="'admin_attr_' + def.id" v-model="eqForm.attributes[def.id]" class="input-select" :required="Boolean(def.is_required)">
                    <option :value="undefined">-- Select --</option>
                    <option :value="true">Yes</option>
                    <option :value="false">No</option>
                  </select>
                </template>
                <template v-else-if="def.data_type === 'number'">
                  <input :id="'admin_attr_' + def.id" v-model.number="eqForm.attributes[def.id]" type="number" class="input-text" :required="Boolean(def.is_required)" />
                </template>
                <template v-else-if="def.data_type === 'decimal'">
                  <input :id="'admin_attr_' + def.id" v-model.number="eqForm.attributes[def.id]" type="number" step="0.01" class="input-text" :required="Boolean(def.is_required)" />
                </template>
                <template v-else-if="def.data_type === 'date'">
                  <input :id="'admin_attr_' + def.id" v-model="eqForm.attributes[def.id]" type="date" class="input-text" :required="Boolean(def.is_required)" />
                </template>
                <template v-else>
                  <input :id="'admin_attr_' + def.id" v-model="eqForm.attributes[def.id]" type="text" class="input-text" :required="Boolean(def.is_required)" />
                </template>
              </div>
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="closeEquipmentModal">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Equipment' }}
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  addOutline,
  cubeOutline,
  chatbubblesOutline,
  searchOutline,
  swapVerticalOutline,
  arrowUpOutline,
  arrowDownOutline,
  createOutline,
  trashOutline,
  layersOutline,
  gridOutline,
  optionsOutline,
  listOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { formatDate } from '../../utils/dateUtils'
import {
  fetchEquipmentList,
  fetchReferenceOptions,
  fetchAttributeDefinitions,
  fetchOffices,
  createEquipment,
  deleteEquipment
} from '../../services/inventoryService'
import type {
  EquipmentItem,
  EquipmentType,
  EquipmentSubtype,
  EquipmentStatusOption,
  AttributeDefinition
} from '../../types/inventory'

const router = useRouter()
const activeTab = ref<'ict' | 'comm'>('ict')
const equipmentList = ref<EquipmentItem[]>([])
const offices = ref<{ id: number; office_name: string; office_abbv: string }[]>([])
const equipmentTypes = ref<EquipmentType[]>([])
const equipmentSubtypes = ref<EquipmentSubtype[]>([])
const equipmentStatuses = ref<EquipmentStatusOption[]>([])
const attributeDefs = ref<AttributeDefinition[]>([])

const loading = ref(true)
const saving = ref(false)

const searchQuery = ref('')
const sortColumn = ref<'serial_number' | 'office' | 'subtype' | 'date_acquired' | 'status' | null>(null)
const sortDirection = ref<'asc' | 'desc'>('asc')

const currentPage = ref(1)
const pageSize = ref(20)

const showEquipmentModal = ref(false)
const modalError = ref('')

const eqForm = ref({
  office_id: 1,
  equipment_type_id: 1,
  equipment_subtype_id: 1,
  status_id: 1,
  description: '',
  serial_number: '',
  date_acquired: '',
  attributes: {} as Record<number, any>
})

function openEquipmentDetail(item: EquipmentItem) {
  router.push(`/administrator/inventory/equipment/${item.id}`)
}

// Tab Switch Handler
function switchTab(tab: 'ict' | 'comm') {
  activeTab.value = tab
  searchQuery.value = ''
  currentPage.value = 1
  sortColumn.value = null
  sortDirection.value = 'asc'
}

// 1. Filter equipment list by active tab (ICT vs Communications)
const tabFilteredList = computed(() => {
  return equipmentList.value.filter(item => {
    const typeId = Number(item.equipment_type_id)
    const typeCode = (item.equipment_type_code || '').toUpperCase()
    const typeName = (item.equipment_type_name || item.equipment_type || '').toLowerCase()

    if (activeTab.value === 'ict') {
      return typeId === 1 || typeCode === 'ICT' || typeName === 'ict'
    } else {
      return typeId === 2 || typeCode === 'COMM' || typeName.includes('communication')
    }
  })
})

// 2. Filter tab-filtered list by search query
const searchedList = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return tabFilteredList.value

  return tabFilteredList.value.filter(item => {
    const sn = (item.serial_number || '').toLowerCase()
    const offAbbv = (item.office_abbv || '').toLowerCase()
    const offName = (item.office_name || '').toLowerCase()
    const subtype = (item.equipment_subtype_name || item.equipment_subtype || '').toLowerCase()
    const desc = (item.description || '').toLowerCase()

    return sn.includes(query) ||
           offAbbv.includes(query) ||
           offName.includes(query) ||
           subtype.includes(query) ||
           desc.includes(query)
  })
})

// 3. Sort searched list by selected column & direction
const sortedList = computed(() => {
  const items = [...searchedList.value]
  if (!sortColumn.value) return items

  const col = sortColumn.value
  const isAsc = sortDirection.value === 'asc'

  items.sort((a, b) => {
    let valA = ''
    let valB = ''

    switch (col) {
      case 'serial_number':
        valA = a.serial_number || ''
        valB = b.serial_number || ''
        break
      case 'office':
        valA = a.office_abbv || ''
        valB = b.office_abbv || ''
        break
      case 'subtype':
        valA = a.equipment_subtype_name || a.equipment_subtype || ''
        valB = b.equipment_subtype_name || b.equipment_subtype || ''
        break
      case 'date_acquired':
        valA = a.date_acquired || ''
        valB = b.date_acquired || ''
        break
      case 'status':
        valA = a.status_name || a.status || ''
        valB = b.status_name || b.status || ''
        break
    }

    const cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
    return isAsc ? cmp : -cmp
  })

  return items
})

// 4. Pagination calculations
const totalPages = computed(() => {
  return Math.ceil(sortedList.value.length / pageSize.value) || 1
})

const paginatedList = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return sortedList.value.slice(start, start + pageSize.value)
})

const paginationStart = computed(() => {
  return sortedList.value.length === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1
})

const paginationEnd = computed(() => {
  return Math.min(currentPage.value * pageSize.value, sortedList.value.length)
})

const visiblePageNumbers = computed(() => {
  const pages: number[] = []
  const maxButtons = 5
  let start = Math.max(1, currentPage.value - 2)
  let end = Math.min(totalPages.value, start + maxButtons - 1)

  if (end - start + 1 < maxButtons) {
    start = Math.max(1, end - maxButtons + 1)
  }

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  return pages
})

function toggleSort(col: 'serial_number' | 'office' | 'subtype' | 'date_acquired' | 'status') {
  if (sortColumn.value === col) {
    if (sortDirection.value === 'asc') {
      sortDirection.value = 'desc'
    } else {
      sortColumn.value = null
      sortDirection.value = 'asc'
    }
  } else {
    sortColumn.value = col
    sortDirection.value = 'asc'
  }
}

const filteredSubtypes = computed(() => {
  if (eqForm.value.equipment_type_id <= 0) return []
  return equipmentSubtypes.value.filter(st => Number(st.equipment_type_id) === Number(eqForm.value.equipment_type_id))
})

const currentSubtypeName = computed(() => {
  const found = equipmentSubtypes.value.find(st => Number(st.id) === Number(eqForm.value.equipment_subtype_id))
  return found ? found.name : ''
})

async function loadData() {
  loading.value = true
  const currentYm = new Date().toISOString().slice(0, 7)
  
  const eqRes = await fetchEquipmentList(currentYm)
  if (eqRes.success) {
    equipmentList.value = eqRes.data.items
  }

  const refRes = await fetchReferenceOptions()
  if (refRes.success) {
    equipmentTypes.value = refRes.data.equipment_types
    equipmentSubtypes.value = refRes.data.equipment_subtypes
    equipmentStatuses.value = refRes.data.statuses
  }

  const offRes = await fetchOffices()
  if (offRes.success) {
    offices.value = offRes.data
  }

  loading.value = false
}

function handleTypeChange() {
  eqForm.value.equipment_subtype_id = 0
  attributeDefs.value = []
  eqForm.value.attributes = {}
}

async function handleSubtypeChange() {
  eqForm.value.attributes = {}
  if (eqForm.value.equipment_subtype_id > 0) {
    const defRes = await fetchAttributeDefinitions(eqForm.value.equipment_subtype_id)
    if (defRes.success) {
      attributeDefs.value = defRes.data
      defRes.data.forEach(def => {
        eqForm.value.attributes[def.id] = undefined
      })
    }
  } else {
    attributeDefs.value = []
  }
}

async function openAddEquipmentModal() {
  const defaultTypeId = activeTab.value === 'ict' ? 1 : 2

  eqForm.value = {
    office_id: offices.value[0]?.id || 1,
    equipment_type_id: defaultTypeId,
    equipment_subtype_id: 0,
    status_id: equipmentStatuses.value[0]?.id || 1,
    description: '',
    serial_number: '',
    date_acquired: new Date().toISOString().slice(0, 10),
    attributes: {}
  }

  if (eqForm.value.equipment_type_id > 0) {
    const subs = equipmentSubtypes.value.filter(st => Number(st.equipment_type_id) === Number(eqForm.value.equipment_type_id))
    if (subs.length > 0) {
      eqForm.value.equipment_subtype_id = subs[0].id
      await handleSubtypeChange()
    }
  }

  modalError.value = ''
  showEquipmentModal.value = true
}

function closeEquipmentModal() {
  showEquipmentModal.value = false
}

async function handleSaveEquipment() {
  if (eqForm.value.office_id <= 0 || eqForm.value.equipment_type_id <= 0 || eqForm.value.equipment_subtype_id <= 0 || eqForm.value.status_id <= 0) {
    modalError.value = 'Please select valid Office, Equipment Type, Subtype, and Status.'
    return
  }

  for (const def of attributeDefs.value) {
    if (def.is_required) {
      const val = eqForm.value.attributes[def.id]
      if (val === undefined || val === null || String(val).trim() === '') {
        modalError.value = `Attribute '${def.attribute_name}' is required.`
        return
      }
    }
  }

  saving.value = true
  modalError.value = ''

  const payload = {
    office_id: eqForm.value.office_id,
    equipment_type_id: eqForm.value.equipment_type_id,
    equipment_subtype_id: eqForm.value.equipment_subtype_id,
    status_id: eqForm.value.status_id,
    description: eqForm.value.description,
    serial_number: eqForm.value.serial_number,
    date_acquired: eqForm.value.date_acquired,
    attributes: eqForm.value.attributes
  }

  const res = await createEquipment(payload)
  saving.value = false

  if (res.success) {
    closeEquipmentModal()
    loadData()
  } else {
    modalError.value = res.message || 'Failed to save equipment record.'
  }
}

async function handleSoftDeleteEquipment(item: EquipmentItem) {
  if (!confirm(`Are you sure you want to soft-delete equipment SN '${item.serial_number}'? Historical snapshots will remain untouched.`)) return

  const res = await deleteEquipment(item.id)
  if (res.success) {
    loadData()
  } else {
    alert(res.message || 'Failed to delete equipment.')
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
.admin-inventory-container {
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

.subtitle { font-size: 14px; color: #64748b; margin: 0; }

.action-main-btn {
  background: #082f6d; color: #ffffff; border: none;
  padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 700;
  cursor: pointer; display: flex; align-items: center; gap: 8px;
  transition: background 0.15s ease;
  box-shadow: 0 2px 6px rgba(8, 47, 109, 0.2);
}
.action-main-btn:hover { background: #1d4ed8; }

.tab-switcher {
  display: flex; gap: 12px; margin-bottom: 24px;
}

.tab-btn {
  background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569;
  padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 700;
  cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.15s ease;
}

.active-tab {
  background: #eff6ff; border-color: #2563eb; color: #2563eb;
}

.table-card {
  background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0;
  overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}.filter-controls-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 18px 24px;
  margin-bottom: 24px;
  display: flex;
  align-items: flex-end;
  gap: 16px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.search-group {
  flex: 1;
  min-width: 260px;
}

.filter-group label {
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.search-icon {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: #94a3b8;
  pointer-events: none;
}

.input-search {
  width: 100%;
  padding: 9px 14px 9px 38px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  box-sizing: border-box;
}

.input-search:focus { border-color: #2563eb; }

.table-toolbar {
  padding: 16px 24px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  max-width: 440px;
}

.search-input {
  width: 100%;
  padding: 9px 36px 9px 38px;
  font-size: 14px;
  border-radius: 8px;
  border: 1.5px solid #cbd5e1;
  outline: none;
  background: #ffffff;
  transition: border-color 0.15s ease;
}

.search-input:focus {
  border-color: #2563eb;
}

.clear-search {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  font-size: 18px;
  color: #94a3b8;
  cursor: pointer;
}

.clear-search:hover { color: #475569; }

.total-count-badge {
  font-size: 13px;
  font-weight: 700;
  color: #475569;
  background: #e2e8f0;
  padding: 6px 12px;
  border-radius: 20px;
}

.loading-state, .empty-state { text-align: center; padding: 48px; color: #64748b; }
.spinner {
  display: inline-block; width: 24px; height: 24px; border: 3px solid #cbd5e1;
  border-radius: 50%; border-top-color: #2563eb; animation: spin 0.8s linear infinite; margin-bottom: 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 14px; }

.data-table th {
  background: #f8fafc; padding: 12px 20px; font-size: 12px; font-weight: 700;
  color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0;
}

.sortable-th {
  cursor: pointer;
  user-select: none;
  transition: background 0.15s ease;
}

.sortable-th:hover {
  background: #f1f5f9;
}

.th-content {
  display: flex;
  align-items: center;
  gap: 6px;
}

.center-th {
  justify-content: center;
}

.sort-icon {
  display: inline-flex;
  align-items: center;
  font-size: 14px;
  color: #2563eb;
}

.inactive-sort {
  color: #cbd5e1;
}

.data-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }

.serial-link {
  font-family: monospace;
  font-weight: 700;
  color: #2563eb;
  text-decoration: none;
  transition: color 0.15s ease;
}

.serial-link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.desc-cell {
  max-width: 260px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.text-primary { color: #2563eb; }
.code-text { font-family: monospace; color: #475569; }
.text-center { text-align: center; }

.office-tag { background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.status-serviceable { background: #f0fdf4; color: #16a34a; }
.status-repair { background: #fff7ed; color: #c2410c; }
.status-unserviceable { background: #fef2f2; color: #dc2626; }

.action-buttons { display: flex; gap: 8px; justify-content: center; }

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

.edit-btn { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
.edit-btn:hover { background: #2563eb; color: #ffffff; }

.delete-btn { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
.delete-btn:hover { background: #dc2626; color: #ffffff; }

/* Pagination Footer */
.pagination-footer {
  padding: 16px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.pagination-info {
  font-size: 13px;
  color: #64748b;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 6px;
}

.page-btn {
  min-width: 32px;
  height: 32px;
  padding: 0 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.page-btn:hover:not(:disabled) {
  border-color: #2563eb;
  color: #2563eb;
}

.active-page {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

.nav-btn {
  padding: 0 12px;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal Styles */
.modal-backdrop {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;
}

.modal-card {
  background: #ffffff; border-radius: 16px; width: 100%; max-width: 580px; max-height: 90vh; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; display: flex; flex-direction: column;
}

.form-card { max-width: 580px; }

.modal-header {
  padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;
}

.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }

.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.section-subheading { font-size: 14px; font-weight: 700; color: #1e293b; margin: 8px 0 4px 0; padding-top: 12px; border-top: 1px dashed #cbd5e1; }

.attributes-form-section { display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: #334155; }
.required-star { color: #dc2626; }

.input-text, .input-select {
  width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; box-sizing: border-box; background: #ffffff;
}

.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
