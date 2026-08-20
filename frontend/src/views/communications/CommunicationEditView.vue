<template>
  <MainLayout title="Edit Communication">
    <div class="communication-edit-page">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div class="header-left">
          <div class="title-group">
            <h2>Edit Details: {{ pageHeading }}</h2>
            <p class="subtitle">Modify communication metadata, office routing, purpose, or status tracking.</p>
          </div>
        </div>

        <div class="header-right">
          <button type="button" class="cancel-btn" @click="cancelEdit">Cancel</button>
          <button type="button" class="save-btn" :disabled="saving || loading" @click="handleSaveCommunication">
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
        <p>Loading communication record for editing...</p>
      </div>

      <div v-else-if="commItem" class="detail-content-grid">
        
        <!-- CARD: Communication Edit Form -->
        <div class="form-card">
          <div class="card-header">
            <h3>Edit Communication Specifications</h3>
          </div>

          <div class="card-body">
            <div class="form-grid">
              <!-- Communication Type -->
              <div class="form-group">
                <label for="commType">Communication Type <span class="required-star">*</span></label>
                <select id="commType" v-model="formPayload.communication_type" required class="input-select">
                  <option value="Incoming">Incoming</option>
                  <option value="Outgoing">Outgoing</option>
                </select>
              </div>

              <!-- Communication Date -->
              <div class="form-group">
                <label for="commDate">Communication Date <span class="required-star">*</span></label>
                <input
                  id="commDate"
                  v-model="formPayload.communication_date"
                  type="date"
                  required
                  class="input-text"
                />
              </div>

              <!-- Office Assignment -->
              <div class="form-group">
                <label for="commOffice">Office <span class="required-star">*</span></label>
                <select id="commOffice" v-model="formPayload.office_id" required class="input-select">
                  <option :value="0" disabled>Select Office...</option>
                  <option v-for="off in officeList" :key="off.id" :value="off.id">
                    {{ off.office_name ? `${off.office_name} (${off.office_code || off.office_abbv})` : off.office_abbv }}
                  </option>
                </select>
              </div>

              <!-- Category -->
              <div class="form-group">
                <label for="commCategory">Category <span class="required-star">*</span></label>
                <select id="commCategory" v-model="formPayload.category_id" required class="input-select">
                  <option :value="0" disabled>Select Category...</option>
                  <option v-for="cat in categoryList" :key="cat.id" :value="cat.id">
                    {{ cat.category_name || cat.name }}
                  </option>
                </select>
              </div>

              <!-- Purpose -->
              <div class="form-group">
                <label for="commPurpose">Purpose <span class="required-star">*</span></label>
                <select id="commPurpose" v-model="formPayload.purpose_id" required class="input-select">
                  <option :value="0" disabled>Select Purpose...</option>
                  <option v-for="pur in purposeList" :key="pur.id" :value="pur.id">
                    {{ pur.purpose_name || pur.name }}
                  </option>
                </select>
              </div>

              <!-- Status -->
              <div class="form-group">
                <label for="commStatus">Status <span class="required-star">*</span></label>
                <select id="commStatus" v-model="formPayload.status" required class="input-select">
                  <option value="Pending">Pending</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Released">Released</option>
                  <option value="Completed">Completed</option>
                  <option value="Archived">Archived</option>
                </select>
              </div>
            </div>

            <!-- Subject -->
            <div class="form-group full-width mt-16">
              <label for="commSubject">Subject / Title <span class="required-star">*</span></label>
              <textarea
                id="commSubject"
                v-model="formPayload.subject"
                rows="3"
                placeholder="Enter communication subject or topic details..."
                required
                class="textarea-input"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- CARD 2: Attachment (Multiple Image Upload) -->
        <div class="form-card mt-24">
          <div class="card-header">
            <h3>Attachment</h3>
          </div>

          <div class="card-body">
            <div class="upload-dropzone">
              <input
                ref="fileInputRef"
                type="file"
                multiple
                accept="image/png, image/jpeg, image/webp"
                class="file-input-hidden"
                @change="handleFileChange"
              />

              <!-- Multi Image Previews Grid -->
              <div v-if="allEditImageUrls.length > 0" class="multi-preview-grid">
                <div v-for="(imgUrl, idx) in allEditImageUrls" :key="idx" class="preview-thumb-card">
                  <img :src="imgUrl" alt="Attachment Thumbnail" class="preview-thumb-img" />
                  <button type="button" class="btn-thumb-remove" title="Remove attachment" @click="removeImageAt(idx)">
                    &times;
                  </button>
                </div>

                <div class="add-more-card" @click="triggerFileInput">
                  <ion-icon :icon="addOutline" class="add-icon" />
                  <span>Add Image</span>
                </div>
              </div>

              <div v-else class="dropzone-content" @click="triggerFileInput">
                <ion-icon :icon="cloudUploadOutline" class="upload-icon" />
                <div class="upload-text">
                  <span class="upload-title">Click to upload image attachments</span>
                  <span class="upload-sub">Supports PNG, JPG, JPEG, WEBP files</span>
                </div>
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
import { saveOutline, cloudUploadOutline, trashOutline, addOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import {
  fetchCommunications,
  fetchCommunicationOptions,
  updateCommunication
} from '../../services/communicationService'

const route = useRoute()
const router = useRouter()

const commId = computed(() => Number(route.params.id) || 0)

const loading = ref(true)
const saving = ref(false)
const pageError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'toast-success' | 'toast-error'>('toast-success')

const commItem = ref<any | null>(null)
const officeList = ref<any[]>([])
const categoryList = ref<any[]>([])
const purposeList = ref<any[]>([])

const fileInputRef = ref<HTMLInputElement | null>(null)
const existingImageUrls = ref<string[]>([])
const newImagesDataBase64 = ref<string[]>([])

const allEditImageUrls = computed<string[]>(() => {
  return [...existingImageUrls.value, ...newImagesDataBase64.value]
})

const formPayload = reactive({
  id: 0,
  communication_type: 'Incoming' as 'Incoming' | 'Outgoing',
  communication_date: '',
  office_id: 0,
  category_id: 0,
  purpose_id: 0,
  subject: '',
  status: 'Pending',
  image_url: null as string | null
})

function triggerFileInput() {
  fileInputRef.value?.click()
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  const files = target.files
  if (!files || files.length === 0) return

  Array.from(files).forEach((file) => {
    if (file.size > 10 * 1024 * 1024) {
      showToast(`Image ${file.name} exceeds 10MB limit.`, 'toast-error')
      return
    }

    const reader = new FileReader()
    reader.onload = (e) => {
      const result = e.target?.result as string
      if (result) {
        newImagesDataBase64.value.push(result)
      }
    }
    reader.readAsDataURL(file)
  })

  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

function removeImageAt(idx: number) {
  if (idx < existingImageUrls.value.length) {
    existingImageUrls.value.splice(idx, 1)
  } else {
    const newIdx = idx - existingImageUrls.value.length
    newImagesDataBase64.value.splice(newIdx, 1)
  }
}

const pageHeading = computed(() => {
  if (commItem.value) {
    const code = commItem.value.category_code || commItem.value.category_name || ''
    const subject = commItem.value.subject || ''
    return code ? `${code} - ${subject}` : subject
  }
  return 'Communication Details'
})

function showToast(msg: string, type: 'toast-success' | 'toast-error' = 'toast-success') {
  feedbackMessage.value = msg
  feedbackType.value = type
  setTimeout(() => {
    feedbackMessage.value = ''
  }, 4000)
}

function cancelEdit() {
  router.push(`/communications/detail/${commId.value}`)
}

async function loadData() {
  loading.value = true
  pageError.value = ''

  try {
    const [optRes, listRes] = await Promise.all([
      fetchCommunicationOptions(),
      fetchCommunications()
    ])

    if (optRes.success && optRes.data) {
      officeList.value = optRes.data.offices || []
      categoryList.value = optRes.data.categories || []
      purposeList.value = optRes.data.purposes || []
    }

    if (listRes.success && Array.isArray(listRes.data)) {
      const item = listRes.data.find((c: any) => c.id === commId.value)
      if (item) {
        commItem.value = item
        formPayload.id = item.id
        formPayload.communication_type = item.communication_type || 'Incoming'
        formPayload.communication_date = item.communication_date || ''
        formPayload.office_id = item.office_id || 0
        formPayload.category_id = item.category_id || 0
        formPayload.purpose_id = item.purpose_id || 0
        formPayload.subject = item.subject || ''
        formPayload.status = item.status || 'Pending'
        formPayload.image_url = item.image_url || null

        if (Array.isArray(item.image_urls) && item.image_urls.length > 0) {
          existingImageUrls.value = [...item.image_urls]
        } else if (item.image_url) {
          existingImageUrls.value = [item.image_url]
        } else {
          existingImageUrls.value = []
        }

        // Populate route meta for dynamic breadcrumb rendering
        route.meta.subject = item.subject || 'Communication Detail'
        route.meta.commType = item.communication_type || 'Incoming'
        route.meta.categoryCode = item.category_code || item.category_name || ''
      } else {
        pageError.value = 'Communication record not found.'
      }
    } else {
      pageError.value = listRes.message || 'Failed to retrieve communication record.'
    }
  } catch (err: any) {
    pageError.value = err.message || 'Failed to load communication record.'
  } finally {
    loading.value = false
  }
}

async function handleSaveCommunication() {
  if (!formPayload.subject.trim()) {
    showToast('Subject is required.', 'toast-error')
    return
  }
  if (formPayload.office_id <= 0 || formPayload.category_id <= 0 || formPayload.purpose_id <= 0) {
    showToast('Please select Office, Category, and Purpose.', 'toast-error')
    return
  }

  saving.value = true

  try {
    const payloadToSend = {
      ...formPayload,
      image_url: existingImageUrls.value[0] || null,
      images_data: newImagesDataBase64.value.length > 0 ? newImagesDataBase64.value : undefined
    }

    const res = await updateCommunication(formPayload.id, payloadToSend)
    if (res.success) {
      showToast('Communication details saved successfully! Redirecting...', 'toast-success')
      setTimeout(() => {
        router.push(`/communications/detail/${commId.value}`)
      }, 1000)
    } else {
      showToast(res.message || 'Failed to update communication record.', 'toast-error')
    }
  } catch (err: any) {
    showToast(err.message || 'Error updating communication record.', 'toast-error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.communication-edit-page {
  padding: 24px;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 16px;
}

.title-group h2 {
  font-size: 22px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px 0;
  line-height: 1.3;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.header-right {
  display: flex;
  gap: 12px;
  flex-shrink: 0;
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

.mt-24 {
  margin-top: 24px;
}

/* Image Upload Dropzone Styles */
.file-input-hidden {
  display: none;
}

.upload-dropzone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  padding: 24px;
  background: #f8fafc;
  transition: all 0.2s ease;
}

.upload-dropzone:hover {
  border-color: #2563eb;
  background: #eff6ff;
}

.dropzone-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  padding: 20px 0;
}

.upload-icon {
  font-size: 48px;
  color: #2563eb;
}

.upload-text {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.upload-title {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.upload-sub {
  font-size: 13px;
  color: #64748b;
}

/* Multi-image preview grid */
.multi-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 16px;
  align-items: center;
}

.preview-thumb-card {
  position: relative;
  height: 120px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.preview-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.btn-thumb-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: none;
  background: rgba(239, 68, 68, 0.9);
  color: #ffffff;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-thumb-remove:hover {
  background: #dc2626;
  transform: scale(1.1);
}

.add-more-card {
  height: 120px;
  border: 2px dashed #94a3b8;
  border-radius: 10px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  color: #2563eb;
  font-weight: 600;
  font-size: 13px;
  transition: all 0.2s ease;
}

.add-more-card:hover {
  border-color: #2563eb;
  background: #eff6ff;
}

.add-icon {
  font-size: 28px;
}

.preview-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.preview-img {
  max-width: 100%;
  max-height: 400px;
  object-fit: contain;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.preview-actions {
  display: flex;
  gap: 12px;
}

.btn-change-image {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-change-image:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.btn-remove-image {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid #fecaca;
  background: #fee2e2;
  color: #dc2626;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-remove-image:hover {
  background: #dc2626;
  color: #ffffff;
}
</style>
