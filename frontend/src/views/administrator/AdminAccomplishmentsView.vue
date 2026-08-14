<template>
  <MainLayout title="Accomplishments Management">
    <div class="admin-accomplishments-container">
      
      <!-- Top Header & Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Accomplishments Data Management</h2>
          <p class="subtitle">Maintain accomplishment categories, options, and master reporting entries.</p>
        </div>

        <button v-if="activeTab === 'categories'" class="action-main-btn" @click="openAddCategoryModal">
          <ion-icon :icon="addOutline" />
          <span>Add Category</span>
        </button>
      </div>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button :class="['tab-btn', activeTab === 'categories' ? 'active-tab' : '']" @click="activeTab = 'categories'">
          <ion-icon :icon="folderOutline" />
          <span>Accomplishment Categories</span>
        </button>

        <button :class="['tab-btn', activeTab === 'entries' ? 'active-tab' : '']" @click="activeTab = 'entries'">
          <ion-icon :icon="clipboardOutline" />
          <span>Master Entries Log</span>
        </button>
      </div>

      <!-- TAB 1: ACCOMPLISHMENT CATEGORIES -->
      <div v-if="activeTab === 'categories'" class="table-card">
        <div class="table-card-header">
          <h3>Accomplishment Categories ({{ categories.length }})</h3>
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
              <tr v-for="cat in categories" :key="cat.id">
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

      <!-- TAB 2: MASTER ENTRIES LOG -->
      <div v-if="activeTab === 'entries'" class="table-card">
        <div class="table-card-header">
          <h3>Master Accomplishments Registry ({{ accomplishments.length }} items)</h3>
        </div>

        <div v-if="loading" class="loading-state">
          <span class="spinner"></span>
          <p>Loading accomplishment entries...</p>
        </div>

        <div v-else class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Title / Description</th>
                <th>Office</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in accomplishments" :key="item.id">
                <td>{{ item.accomplishment_date }}</td>
                <td class="font-semibold">{{ item.category_name }}</td>
                <td>{{ item.title }}</td>
                <td><span class="office-tag">{{ item.office_abbv || 'N/A' }}</span></td>
                <td class="text-center">
                  <span class="status-badge status-active">{{ item.status || 'Recorded' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Category Modal -->
      <div v-if="showCategoryModal" class="modal-backdrop">
        <div class="modal-card">
          <div class="modal-header">
            <h3>Add Accomplishment Category</h3>
            <button class="close-btn" @click="showCategoryModal = false">&times;</button>
          </div>

          <form @submit.prevent="handleSaveCategory" class="modal-body">
            <div class="form-group">
              <label for="catName">Category Name</label>
              <input id="catName" v-model="categoryForm.category_name" type="text" placeholder="e.g. Technical Support" required class="input-text" />
            </div>

            <div class="form-group">
              <label for="catDesc">Description</label>
              <input id="catDesc" v-model="categoryForm.description" type="text" placeholder="Description..." class="input-text" />
            </div>

            <div v-if="modalError" class="modal-error">{{ modalError }}</div>

            <div class="modal-footer">
              <button type="button" class="cancel-btn" @click="showCategoryModal = false">Cancel</button>
              <button type="submit" class="save-btn" :disabled="saving">Save Category</button>
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
  folderOutline,
  clipboardOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'

const activeTab = ref<'categories' | 'entries'>('categories')
const categories = ref<any[]>([])
const accomplishments = ref<any[]>([])
const loading = ref(true)

const showCategoryModal = ref(false)
const categoryForm = ref({ category_name: '', description: '' })
const saving = ref(false)
const modalError = ref('')

async function loadData() {
  loading.value = true
  try {
    const catRes = await fetch('/6IS/backend/api/accomplishments/index.php?view=categories', { credentials: 'include' })
    const catData = await catRes.json()
    if (catData.success) categories.value = catData.data

    const accRes = await fetch('/6IS/backend/api/accomplishments/index.php?view=daily', { credentials: 'include' })
    const accData = await accRes.json()
    if (accData.success) accomplishments.value = accData.data
  } catch (err) {}
  loading.value = false
}

function openAddCategoryModal() {
  categoryForm.value = { category_name: '', description: '' }
  modalError.value = ''
  showCategoryModal.value = true
}

async function handleSaveCategory() {
  saving.value = true
  modalError.value = ''
  try {
    const res = await fetch('/6IS/backend/api/accomplishments/index.php?action=create_category', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(categoryForm.value)
    })
    const data = await res.json()
    saving.value = false
    if (data.success) {
      showCategoryModal.value = false
      loadData()
    } else {
      modalError.value = data.message || 'Failed to save category.'
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
.admin-accomplishments-container {
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
.text-center { text-align: center; }

.office-tag { background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; }
.status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.status-active { background: #f0fdf4; color: #16a34a; }
.status-inactive { background: #fef2f2; color: #dc2626; }

/* Modal */
.modal-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15,23,42,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal-card { background: #ffffff; border-radius: 16px; width: 100%; max-width: 440px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
.close-btn { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.modal-body { padding: 24px; display: flex; flex-direction: column; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: #334155; }
.input-text { width: 100%; padding: 10px 14px; font-size: 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; outline: none; box-sizing: border-box; }
.modal-error { background: #fef2f2; color: #dc2626; padding: 10px; border-radius: 6px; font-size: 13px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px; }
.cancel-btn { padding: 10px 16px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
.save-btn { padding: 10px 20px; background: #082f6d; color: #ffffff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
</style>
