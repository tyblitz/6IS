<template>
  <MainLayout title="Inventory Management">
    <div class="admin-inventory-container">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Inventory Data Management</h2>
          <p class="subtitle">Maintain equipment registry, JRRS target quantities, and office assignments.</p>
        </div>

        <button v-if="activeTab === 'equipment'" class="action-main-btn" @click="openAddEquipmentModal">
          <ion-icon :icon="addOutline" />
          <span>Add Equipment Record</span>
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button
          :class="['tab-btn', activeTab === 'equipment' ? 'active-tab' : '']"
          @click="activeTab = 'equipment'"
        >
          <ion-icon :icon="cubeOutline" />
          <span>Equipment Registry</span>
        </button>

        <button
          :class="['tab-btn', activeTab === 'jrrs' ? 'active-tab' : '']"
          @click="activeTab = 'jrrs'"
        >
          <ion-icon :icon="clipboardOutline" />
          <span>JRRS Target Quantities</span>
        </button>
      </div>

      <!-- TAB 1: EQUIPMENT REGISTRY -->
      <div v-if="activeTab === 'equipment'" class="table-card">
        <div class="table-card-header">
          <h3>Active Equipment Registry ({{ equipmentList.length }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading equipment records...</p>
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
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in equipmentList" :key="item.id">
                <td><span class="office-tag">{{ item.office_abbv }}</span></td>
                <td class="font-bold">{{ item.equipment_type }}</td>
                <td>{{ item.description }}</td>
                <td class="code-text">{{ item.serial_number || 'N/A' }}</td>
                <td>{{ formatDate(item.date_acquired) }}</td>
                <td class="text-center">
                  <span :class="['status-badge', getStatusClass(item.status)]">{{ item.status }}</span>
                </td>
                <td class="text-center">
                  <div class="action-buttons">
                    <button class="action-btn edit-btn" @click="openEditEquipmentModal(item)">
                      <ion-icon :icon="createOutline" />
                      <span>Edit</span>
                    </button>
                    <button class="action-btn delete-btn" @click="handleSoftDeleteEquipment(item)">
                      <ion-icon :icon="trashOutline" />
                      <span>Delete</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: JRRS TARGET QUANTITIES -->
      <div v-if="activeTab === 'jrrs'" class="table-card">
        <div class="table-card-header">
          <h3>JRRS Approved Target Quantities</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading JRRS target quantities...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Equipment Type</th>
                <th class="text-center">Target Quantity</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in jrrsList" :key="item.id">
                <td class="font-bold">{{ item.equipment_type }}</td>
                <td class="text-center font-bold text-lg">{{ item.target_quantity }}</td>
                <td class="text-center">
                  <button class="action-btn edit-btn" @click="openEditJrrsModal(item)">
                    <ion-icon :icon="createOutline" />
                    <span>Edit Target</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add / Edit Equipment Modal -->
      <div v-if="showEquipmentModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>{{ isEditEquipment ? 'Edit Equipment Record' : 'Add New Equipment Record' }}</h3>
            <button class="close-btn" @click="closeEquipmentModal">&times;</button>
          </div>

          <form @submit.prevent="handleSaveEquipment" class="modal-body">
            <div class="form-group">
              <label for="eqOffice">Office Assignment</label>
              <select id="eqOffice" v-model="eqForm.office_id" class="input-select" required>
                <option value="0" disabled>Select Office...</option>
                <option v-for="off in offices" :key="off.id" :value="off.id">
                  {{ off.office_abbv }} — {{ off.office_name }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="eqType">Equipment Type</label>
              <input id="eqType" v-model="eqForm.equipment_type" type="text" placeholder="e.g. Desktop Computer" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="eqDesc">Description</label>
              <input id="eqDesc" v-model="eqForm.description" type="text" placeholder="e.g. HP EliteDesk 800 G5 i7" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="eqSN">Serial Number</label>
              <input id="eqSN" v-model="eqForm.serial_number" type="text" placeholder="e.g. SN-HP800-001" class="input-text" />
            </div>

            <div class="form-group">
              <label for="eqDate">Date Acquired</label>
              <input id="eqDate" v-model="eqForm.date_acquired" type="date" class="input-text" />
            </div>

            <div class="form-group">
              <label for="eqStatus">Condition / Status</label>
              <select id="eqStatus" v-model="eqForm.status" class="input-select" required>
                <option value="Serviceable">Serviceable</option>
                <option value="For Repair">For Repair</option>
                <option value="For Turn-In / Unserviceable">For Turn-In / Unserviceable</option>
              </select>
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="closeEquipmentModal">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : (isEditEquipment ? 'Update Record' : 'Save Equipment') }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Edit JRRS Target Modal -->
      <div v-if="showJrrsModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Modify JRRS Target Quantity</h3>
            <button class="close-btn" @click="showJrrsModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveJrrs" class="modal-body">
            <div class="form-group">
              <label>Equipment Type</label>
              <input type="text" :value="editJrrsItem?.equipment_type" disabled class="input-disabled" />
            </div>

            <div class="form-group">
              <label for="jrrsQty">Target Quantity</label>
              <input id="jrrsQty" v-model.number="editJrrsQty" type="number" min="0" required class="input-text" />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showJrrsModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Target' }}
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  addOutline,
  cubeOutline,
  clipboardOutline,
  createOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { formatDate } from '../../utils/dateUtils'
import {
  fetchEquipmentList,
  fetchJrrsList,
  updateJrrsTarget
} from '../../services/inventoryService'
import type { EquipmentItem, JrrsItem, EquipmentStatus } from '../../types/inventory'

const activeTab = ref<'equipment' | 'jrrs'>('equipment')
const equipmentList = ref<EquipmentItem[]>([])
const jrrsList = ref<JrrsItem[]>([])
const offices = ref<{ id: number; office_name: string; office_abbv: string }[]>([])
const loading = ref(true)

const showEquipmentModal = ref(false)
const isEditEquipment = ref(false)
const editEquipmentId = ref(0)
const eqForm = ref({
  office_id: 1,
  equipment_type: '',
  description: '',
  serial_number: '',
  date_acquired: '',
  status: 'Serviceable' as EquipmentStatus
})

const showJrrsModal = ref(false)
const editJrrsItem = ref<JrrsItem | null>(null)
const editJrrsQty = ref(0)

const saving = ref(false)
const modalError = ref('')

async function loadData() {
  loading.value = true
  const eqRes = await fetchEquipmentList(new Date().toISOString().slice(0, 7))
  if (eqRes.success) {
    equipmentList.value = eqRes.data.items
  }

  const jrrsRes = await fetchJrrsList(new Date().toISOString().slice(0, 7))
  if (jrrsRes.success) {
    jrrsList.value = jrrsRes.data.items
  }

  // Fetch offices
  try {
    const offRes = await fetch('/6IS/backend/api/inventory/index.php?view=offices', { credentials: 'include' })
    const offData = await offRes.json()
    if (offData.success) {
      offices.value = offData.data
    }
  } catch (err) {}

  loading.value = false
}

function openAddEquipmentModal() {
  isEditEquipment.value = false
  editEquipmentId.value = 0
  eqForm.value = {
    office_id: offices.value[0]?.id || 1,
    equipment_type: '',
    description: '',
    serial_number: '',
    date_acquired: new Date().toISOString().slice(0, 10),
    status: 'Serviceable'
  }
  modalError.value = ''
  showEquipmentModal.value = true
}

function openEditEquipmentModal(item: EquipmentItem) {
  isEditEquipment.value = true
  editEquipmentId.value = item.id
  eqForm.value = {
    office_id: item.office_id,
    equipment_type: item.equipment_type,
    description: item.description,
    serial_number: item.serial_number || '',
    date_acquired: item.date_acquired || '',
    status: item.status
  }
  modalError.value = ''
  showEquipmentModal.value = true
}

function closeEquipmentModal() {
  showEquipmentModal.value = false
}

async function handleSaveEquipment() {
  saving.value = true
  modalError.value = ''

  const action = isEditEquipment.value ? 'update_equipment' : 'create_equipment'
  const payload = {
    id: editEquipmentId.value,
    ...eqForm.value
  }

  try {
    const res = await fetch(`/6IS/backend/api/inventory/index.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    saving.value = false

    if (data.success) {
      closeEquipmentModal()
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save equipment record.'
    }
  } catch (err: any) {
    saving.value = false;
    modalError.value = err.message || 'Network error.';
  }
}

async function handleSoftDeleteEquipment(item: EquipmentItem) {
  if (!confirm(`Are you sure you want to soft-delete equipment '${item.description}'? Historical snapshots will remain untouched.`)) return

  try {
    const res = await fetch('/6IS/backend/api/inventory/index.php?action=delete_equipment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: item.id })
    })
    const data = await res.json()
    if (data.success) {
      loadData()
    } else {
      alert(data.message || 'Failed to delete equipment.')
    }
  } catch (err) {
    alert('Network error.')
  }
}

function openEditJrrsModal(item: JrrsItem) {
  editJrrsItem.value = item
  editJrrsQty.value = item.target_quantity
  modalError.value = ''
  showJrrsModal.value = true
}

async function handleSaveJrrs() {
  if (!editJrrsItem.value || editJrrsQty.value < 0) return
  saving.value = true
  modalError.value = ''

  const res = await updateJrrsTarget(editJrrsItem.value.equipment_type, editJrrsQty.value)
  saving.value = false

  if (res.success) {
    showJrrsModal.value = false
    loadData()
  } else {
    modalError.value = res.message || 'Failed to update target.'
  }
}

function getStatusClass(status: EquipmentStatus): string {
  switch (status) {
    case 'Serviceable': return 'status-serviceable'
    case 'For Repair': return 'status-repair'
    case 'For Turn-In / Unserviceable': return 'status-unserviceable'
    default: return ''
  }
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
}

.table-card-header {
  padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;
}

.table-card-header h3 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }

.loading-state { text-align: center; padding: 48px; color: #64748b; }
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
.data-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.font-bold { font-weight: 700; }
.text-lg { font-size: 16px; color: #0f172a; }
.code-text { font-family: monospace; color: #475569; }
.text-center { text-align: center; }

.office-tag { background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.status-serviceable { background: #f0fdf4; color: #16a34a; }
.status-repair { background: #fff7ed; color: #c2410c; }
.status-unserviceable { background: #fef2f2; color: #dc2626; }

.action-buttons { display: flex; gap: 8px; justify-content: center; }
.action-btn {
  padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700;
  cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; transition: all 0.15s ease;
}
.edit-btn { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.edit-btn:hover { background: #2563eb; color: #ffffff; }
.delete-btn { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.delete-btn:hover { background: #dc2626; color: #ffffff; }

/* Modal Styles */
.modal-backdrop {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px;
}
.modal-card {
  background: #ffffff; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden;
}
.modal-header {
  padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;
}
.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: #334155; }
.input-text, .input-select {
  width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; box-sizing: border-box;
}
.input-disabled { background: #f8fafc; color: #64748b; }
.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
