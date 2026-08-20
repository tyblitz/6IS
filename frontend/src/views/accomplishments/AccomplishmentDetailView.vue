<template>
  <MainLayout title="Accomplishment Detail">
    <div class="accomplishment-detail-page">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div class="header-left">
          <div class="title-group">
            <h2>{{ pageHeading }}</h2>
            <p class="subtitle">View and edit accomplishment record, office reporting assignment, and category details.</p>
          </div>
        </div>

        <div class="header-right">
          <button type="button" class="cancel-btn" @click="goBack">Cancel</button>
          <button type="button" class="save-btn" :disabled="saving || loading" @click="handleSaveAccomplishment">
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
        <p>Loading accomplishment record...</p>
      </div>

      <div v-else-if="accItem" class="detail-content-grid">
        
        <!-- CARD 1: Accomplishment Details Form -->
        <div class="form-card">
          <div class="card-header">
            <h3>Accomplishment Information</h3>
          </div>

          <div class="card-body">
            <div class="form-grid">
              <!-- Office Assignment -->
              <div class="form-group">
                <label for="accOffice">Office <span class="required-star">*</span></label>
                <select id="accOffice" v-model="formPayload.office_id" required class="input-select">
                  <option :value="0" disabled>Select Office...</option>
                  <option v-for="off in officeList" :key="off.id" :value="off.id">
                    {{ off.office_name ? `${off.office_name} (${off.office_code || off.office_abbv})` : off.office_abbv }}
                  </option>
                </select>
              </div>

              <!-- Category -->
              <div class="form-group">
                <label for="accCategory">Category <span class="required-star">*</span></label>
                <select id="accCategory" v-model="formPayload.category_id" required class="input-select">
                  <option :value="0" disabled>Select Category...</option>
                  <option v-for="cat in categoryList" :key="cat.id" :value="cat.id">
                    {{ cat.category_code || cat.category_name || cat.name }} — {{ cat.category_name || cat.name }}
                  </option>
                </select>
              </div>

              <!-- Accomplishment Date -->
              <div class="form-group">
                <label for="accDate">Accomplishment Date <span class="required-star">*</span></label>
                <input
                  id="accDate"
                  v-model="formPayload.date"
                  type="date"
                  required
                  class="input-text"
                />
              </div>
            </div>

            <!-- Description -->
            <div class="form-group full-width mt-16">
              <label for="accDesc">Activity Description <span class="required-star">*</span></label>
              <textarea
                id="accDesc"
                v-model="formPayload.description"
                rows="4"
                placeholder="Enter detailed summary of key activity accomplishments..."
                required
                class="textarea-input"
              ></textarea>
            </div>

            <!-- Remarks -->
            <div class="form-group full-width mt-16">
              <label for="accRemarks">Remarks / Notes</label>
              <textarea
                id="accRemarks"
                v-model="formPayload.remarks"
                rows="3"
                placeholder="Enter additional remarks or impact notes..."
                class="textarea-input"
              ></textarea>
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
  fetchAccomplishmentOptions,
  fetchDailyAccomplishments,
  updateAccomplishment
} from '../../services/accomplishmentService'

const route = useRoute()
const router = useRouter()

const accId = computed(() => Number(route.params.id) || 0)

const loading = ref(true)
const saving = ref(false)
const pageError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'toast-success' | 'toast-error'>('toast-success')

const accItem = ref<any | null>(null)
const officeList = ref<any[]>([])
const categoryList = ref<any[]>([])

const formPayload = reactive({
  id: 0,
  office_id: 0,
  category_id: 0,
  date: '',
  description: '',
  remarks: ''
})

const pageHeading = computed(() => {
  if (accItem.value) {
    return `Accomplishment #${accItem.value.id}`
  }
  return 'Accomplishment Details'
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
    const [optRes, listRes] = await Promise.all([
      fetchAccomplishmentOptions(),
      fetchDailyAccomplishments()
    ])

    if (optRes.success && optRes.data) {
      officeList.value = optRes.data.offices || []
      categoryList.value = optRes.data.categories || []
    }

    const records = listRes.data?.records || (Array.isArray(listRes.data) ? listRes.data : [])
    const item = records.find((a: any) => a.id === accId.value)

    if (item) {
      accItem.value = item
      formPayload.id = item.id
      formPayload.office_id = item.office_id || 0
      formPayload.category_id = item.category_id || 0
      formPayload.date = item.date || item.accomplishment_date || ''
      formPayload.description = item.description || ''
      formPayload.remarks = item.remarks || ''
    } else {
      pageError.value = 'Accomplishment record not found.'
    }
  } catch (err: any) {
    pageError.value = err.message || 'Failed to load accomplishment record.'
  } finally {
    loading.value = false
  }
}

async function handleSaveAccomplishment() {
  if (!formPayload.description.trim()) {
    showToast('Activity description is required.', 'toast-error')
    return
  }
  if (formPayload.office_id <= 0 || !formPayload.date) {
    showToast('Please select Office and Date.', 'toast-error')
    return
  }

  saving.value = true

  try {
    const res = await updateAccomplishment(formPayload.id, {
      office_id: formPayload.office_id,
      category_id: formPayload.category_id,
      date: formPayload.date,
      description: formPayload.description,
      remarks: formPayload.remarks
    })
    if (res.success) {
      showToast(res.message || 'Accomplishment record updated successfully!', 'toast-success')
      await loadData()
    } else {
      showToast(res.message || 'Failed to update accomplishment record.', 'toast-error')
    }
  } catch (err: any) {
    showToast(err.message || 'Error updating accomplishment record.', 'toast-error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.accomplishment-detail-page {
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
</style>
