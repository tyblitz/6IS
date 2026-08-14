<template>
  <MainLayout title="Communications Management">
    <div class="admin-comms-container">
      
      <!-- Top Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Communications Data Management</h2>
          <p class="subtitle">Maintain communications logs, categories, purposes, and master reference data.</p>
        </div>

        <div class="action-buttons-group">
          <button v-if="activeTab === 'comms'" class="action-main-btn" @click="openAddCommModal">
            <ion-icon :icon="addOutline" />
            <span>Add Communication</span>
          </button>

          <button v-if="activeTab === 'categories'" class="action-main-btn" @click="openAddCategoryModal">
            <ion-icon :icon="addOutline" />
            <span>Add Category</span>
          </button>

          <button v-if="activeTab === 'purposes'" class="action-main-btn" @click="openAddPurposeModal">
            <ion-icon :icon="addOutline" />
            <span>Add Purpose</span>
          </button>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button :class="['tab-btn', activeTab === 'comms' ? 'active-tab' : '']" @click="activeTab = 'comms'">
          <ion-icon :icon="chatbubbleEllipsesOutline" />
          <span>Communications Log</span>
        </button>

        <button :class="['tab-btn', activeTab === 'categories' ? 'active-tab' : '']" @click="activeTab = 'categories'">
          <ion-icon :icon="folderOutline" />
          <span>Categories</span>
        </button>

        <button :class="['tab-btn', activeTab === 'purposes' ? 'active-tab' : '']" @click="activeTab = 'purposes'">
          <ion-icon :icon="bookmarkOutline" />
          <span>Purposes</span>
        </button>
      </div>

      <!-- TAB 1: COMMUNICATIONS LOG -->
      <div v-if="activeTab === 'comms'" class="table-card">
        <div class="table-card-header">
          <h3>Registered Communications ({{ commsList.length }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading communications...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Control #</th>
                <th>Type</th>
                <th>Date</th>
                <th>Subject</th>
                <th>Originating Office</th>
                <th>Category</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in commsList" :key="item.id">
                <td class="font-bold code-text">{{ item.control_number }}</td>
                <td>
                  <span :class="['type-badge', item.comm_type === 'Incoming' ? 'type-in' : 'type-out']">
                    {{ item.comm_type }}
                  </span>
                </td>
                <td>{{ item.comm_date }}</td>
                <td class="font-semibold">{{ item.subject }}</td>
                <td><span class="office-tag">{{ item.originating_office || 'N/A' }}</span></td>
                <td>{{ item.category_name || 'N/A' }}</td>
                <td class="text-center">
                  <button class="action-btn delete-btn" @click="handleSoftDeleteComm(item)">
                    <ion-icon :icon="trashOutline" />
                    <span>Soft Delete</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 2: CATEGORIES -->
      <div v-if="activeTab === 'categories'" class="table-card">
        <div class="table-card-header">
          <h3>Communication Categories</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading categories...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Category Name</th>
                <th>Description</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in categoryList" :key="cat.id">
                <td class="font-bold">{{ cat.category_name }}</td>
                <td>{{ cat.description || 'N/A' }}</td>
                <td class="text-center">
                  <span :class="['status-badge', cat.is_active === 1 ? 'status-active' : 'status-inactive']">
                    {{ cat.is_active === 1 ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: PURPOSES -->
      <div v-if="activeTab === 'purposes'" class="table-card">
        <div class="table-card-header">
          <h3>Communication Purposes</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading purposes...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Purpose Name</th>
                <th>Description</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in purposeList" :key="p.id">
                <td class="font-bold">{{ p.purpose_name }}</td>
                <td>{{ p.description || 'N/A' }}</td>
                <td class="text-center">
                  <span :class="['status-badge', p.is_active === 1 ? 'status-active' : 'status-inactive']">
                    {{ p.is_active === 1 ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Communication Modal -->
      <div v-if="showCommModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Add New Communication Record</h3>
            <button class="close-btn" @click="showCommModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveComm" class="modal-body">
            <div class="form-group">
              <label for="commCtrl">Control Number</label>
              <input id="commCtrl" v-model="commForm.control_number" type="text" placeholder="e.g. COMM-2026-001" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="commType">Type</label>
              <select id="commType" v-model="commForm.comm_type" class="input-select" required>
                <option value="Incoming">Incoming</option>
                <option value="Outgoing">Outgoing</option>
              </select>
            </div>

            <div class="form-group">
              <label for="commDate">Communication Date</label>
              <input id="commDate" v-model="commForm.comm_date" type="date" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="commSubject">Subject / Title</label>
              <input id="commSubject" v-model="commForm.subject" type="text" placeholder="Enter communication subject" required class="input-text" />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showCommModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">
                {{ saving ? 'Saving...' : 'Create Communication' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add Category Modal -->
      <div v-if="showCatModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Add Communication Category</h3>
            <button class="close-btn" @click="showCatModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveCategory" class="modal-body">
            <div class="form-group">
              <label for="catName">Category Name</label>
              <input id="catName" v-model="catForm.category_name" type="text" placeholder="e.g. Directive" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="catDesc">Description</label>
              <input id="catDesc" v-model="catForm.description" type="text" placeholder="Description..." class="input-text" />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showCatModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">Save Category</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add Purpose Modal -->
      <div v-if="showPurposeModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Add Communication Purpose</h3>
            <button class="close-btn" @click="showPurposeModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSavePurpose" class="modal-body">
            <div class="form-group">
              <label for="pName">Purpose Name</label>
              <input id="pName" v-model="purposeForm.purpose_name" type="text" placeholder="e.g. Information" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="pDesc">Description</label>
              <input id="pDesc" v-model="purposeForm.description" type="text" placeholder="Description..." class="input-text" />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showPurposeModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">Save Purpose</button>
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
  chatbubbleEllipsesOutline,
  folderOutline,
  bookmarkOutline,
  trashOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'

const activeTab = ref<'comms' | 'categories' | 'purposes'>('comms')
const commsList = ref<any[]>([])
const categoryList = ref<any[]>([])
const purposeList = ref<any[]>([])
const loading = ref(true)

const showCommModal = ref(false)
const commForm = ref({
  control_number: '',
  comm_type: 'Incoming',
  comm_date: new Date().toISOString().slice(0, 10),
  subject: '',
  originating_office_id: 1,
  category_id: 1,
  purpose_id: 1,
  remarks: ''
})

const showCatModal = ref(false)
const catForm = ref({ category_name: '', description: '' })

const showPurposeModal = ref(false)
const purposeForm = ref({ purpose_name: '', description: '' })

const saving = ref(false)
const modalError = ref('')

async function loadData() {
  loading.value = true
  try {
    const cRes = await fetch('/6IS/backend/api/communications/index.php?view=communications', { credentials: 'include' })
    const cData = await cRes.json()
    if (cData.success) commsList.value = cData.data

    const catRes = await fetch('/6IS/backend/api/communications/index.php?view=categories', { credentials: 'include' })
    const catData = await catRes.json()
    if (catData.success) categoryList.value = catData.data

    const pRes = await fetch('/6IS/backend/api/communications/index.php?view=purposes', { credentials: 'include' })
    const pData = await pRes.json()
    if (pData.success) purposeList.value = pData.data
  } catch (err) {}
  loading.value = false
}

function openAddCommModal() {
  commForm.value = {
    control_number: `COMM-${new Date().getFullYear()}-${Math.floor(100 + Math.random() * 900)}`,
    comm_type: 'Incoming',
    comm_date: new Date().toISOString().slice(0, 10),
    subject: '',
    originating_office_id: 1,
    category_id: 1,
    purpose_id: 1,
    remarks: ''
  }
  modalError.value = ''
  showCommModal.value = true
}

async function handleSaveComm() {
  saving.value = true
  modalError.value = ''
  try {
    const res = await fetch('/6IS/backend/api/communications/index.php?action=create_communication', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(commForm.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      showCommModal.value = false
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save communication.'
    }
  } catch (err) {
    saving.value = false
    modalError.value = 'Network error.'
  }
}

async function handleSoftDeleteComm(item: any) {
  if (!confirm(`Are you sure you want to soft-delete communication '${item.control_number}'? Activity history will remain preserved.`)) return
  try {
    const res = await fetch('/6IS/backend/api/communications/index.php?action=delete_communication', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id: item.id })
    })
    const data = await res.json()
    if (data.success) loadData()
    else alert(data.message || 'Failed to delete communication.')
  } catch (err) {
    alert('Network error.')
  }
}

function openAddCategoryModal() {
  catForm.value = { category_name: '', description: '' }
  modalError.value = ''
  showCatModal.value = true
}

async function handleSaveCategory() {
  saving.value = true
  modalError.value = ''
  try {
    const res = await fetch('/6IS/backend/api/communications/index.php?action=save_category', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(catForm.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      showCatModal.value = false
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save category.'
    }
  } catch (err) {
    saving.value = false
    modalError.value = 'Network error.'
  }
}

function openAddPurposeModal() {
  purposeForm.value = { purpose_name: '', description: '' }
  modalError.value = ''
  showPurposeModal.value = true
}

async function handleSavePurpose() {
  saving.value = true
  modalError.value = ''
  try {
    const res = await fetch('/6IS/backend/api/communications/index.php?action=save_purpose', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(purposeForm.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      showPurposeModal.value = false
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save purpose.'
    }
  } catch (err) {
    saving.value = false
    modalError.value = 'Network error.'
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.admin-comms-container {
  padding: 32px 40px; max-width: 1280px; margin: 0 auto;
}
.header-action-bar {
  display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;
}
.header-action-bar h2 { font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; }
.subtitle { font-size: 14px; color: #64748b; margin: 0; }

.action-main-btn {
  background: #082f6d; color: #ffffff; border: none; padding: 10px 18px;
  border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
}
.action-main-btn:hover { background: #1d4ed8; }

.tab-switcher { display: flex; gap: 12px; margin-bottom: 24px; }
.tab-btn {
  background: #ffffff; border: 1.5px solid #cbd5e1; color: #475569; padding: 10px 20px;
  border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
}
.active-tab { background: #eff6ff; border-color: #2563eb; color: #2563eb; }

.table-card { background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.table-card-header { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
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
  background: #f8fafc; padding: 12px 20px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0;
}
.data-table td { padding: 14px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.code-text { font-family: monospace; color: #0f172a; }
.text-center { text-align: center; }

.type-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.type-in { background: #eff6ff; color: #2563eb; }
.type-out { background: #f0fdf4; color: #16a34a; }

.office-tag { background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.status-active { background: #f0fdf4; color: #16a34a; }
.status-inactive { background: #fef2f2; color: #dc2626; }

.action-btn { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; }
.delete-btn { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.delete-btn:hover { background: #dc2626; color: #ffffff; }

/* Modal */
.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15,23,42,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-card { background: #ffffff; border-radius: 16px; width: 100%; max-width: 460px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: #334155; }
.input-text, .input-select { width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; box-sizing: border-box; }
.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
