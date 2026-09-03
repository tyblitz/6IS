<template>
  <MainLayout title="Communication Detail">
    <div class="communication-detail-page">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div class="header-left">
          <div class="title-group">
            <h2>{{ pageHeading }}</h2>
            <p class="subtitle">View complete communication details, purpose, and status tracking.</p>
          </div>
        </div>

        <div class="header-right">
          <button type="button" class="back-btn" @click="goBackList">
            <ion-icon :icon="arrowBackOutline" />
            <span>Back</span>
          </button>
          <button type="button" class="edit-btn" @click="goToEdit">
            <ion-icon :icon="createOutline" />
            <span>Edit Details</span>
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
        <p>Loading communication record...</p>
      </div>

      <!-- Read-Only Detailed Content Grid -->
      <div v-else-if="commItem" class="detail-content-grid">
        
        <!-- CARD: Communication Details (Aligned in Single Row) -->
        <div class="form-card details-card">
          <div class="card-header">
            <h3>Communication Details</h3>
          </div>

          <div class="card-body">
            <div class="info-row-single">
              <!-- Office Origin -->
              <div class="info-item">
                <span class="info-label">Office Origin</span>
                <span class="info-value font-bold text-primary">
                  {{ commItem.office_abbv || commItem.office_code || commItem.originating_office || commItem.office_name || '-' }}
                </span>
              </div>

              <!-- Communication Date -->
              <div class="info-item">
                <span class="info-label">Communication Date</span>
                <span class="info-value font-semibold">{{ formatDate(commItem.communication_date) }}</span>
              </div>

              <!-- Category -->
              <div class="info-item">
                <span class="info-label">Category</span>
                <span class="info-value">
                  <span v-if="commItem.category_name" class="tag-badge category-tag">
                    {{ commItem.category_code || commItem.category_name }}
                  </span>
                  <span v-else>-</span>
                </span>
              </div>

              <!-- Purpose -->
              <div class="info-item">
                <span class="info-label">Purpose</span>
                <span class="info-value">
                  <span v-if="commItem.purpose_name" class="tag-badge purpose-tag">
                    {{ commItem.purpose_name }}
                  </span>
                  <span v-else>-</span>
                </span>
              </div>

              <!-- Current Status -->
              <div class="info-item">
                <span class="info-label">Current Status</span>
                <span class="info-value">
                  <span :class="['status-badge', getStatusClass(commItem.status)]">
                    {{ commItem.status }}
                  </span>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD 2: Attachment (Multiple Images Gallery) -->
        <div class="form-card screenshot-card mt-20">
          <div class="card-header">
            <h3>Attachment</h3>
          </div>

          <div class="card-body">
            <div v-if="allImageUrls.length > 0" class="attachments-gallery-grid">
              <div
                v-for="(imgUrl, idx) in allImageUrls"
                :key="idx"
                class="image-wrapper"
                @click="openImageModal(imgUrl)"
              >
                <img :src="imgUrl" alt="Communication Attachment" class="screenshot-img" />
                <div class="overlay-hint">
                  <ion-icon :icon="expandOutline" class="expand-icon" />
                  <span>Click to expand</span>
                </div>
              </div>
            </div>

            <div v-else class="empty-image-container">
              <ion-icon :icon="imageOutline" class="empty-image-icon" />
              <p class="empty-image-text">No communication attachment uploaded yet.</p>
            </div>
          </div>
        </div>

      </div>

      <!-- Fullsize Image Modal / Lightbox -->
      <div v-if="isImageModalOpen && activeModalImage" class="lightbox-overlay" @click.self="isImageModalOpen = false">
        <div class="lightbox-content">
          <button type="button" class="lightbox-close-btn" @click="isImageModalOpen = false">&times;</button>
          <img :src="activeModalImage" alt="Full Communication Attachment" class="lightbox-img" />
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  createOutline,
  arrowBackOutline,
  expandOutline,
  imageOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import {
  fetchCommunications
} from '../../services/communicationService'
import { formatDate } from '../../utils/dateUtils'

const route = useRoute()
const router = useRouter()

const commId = computed(() => Number(route.params.id) || 0)

const loading = ref(true)
const pageError = ref('')
const feedbackMessage = ref('')
const feedbackType = ref<'toast-success' | 'toast-error'>('toast-success')

const commItem = ref<any | null>(null)
const isImageModalOpen = ref(false)
const activeModalImage = ref<string | null>(null)

const allImageUrls = computed<string[]>(() => {
  if (!commItem.value) return []
  if (Array.isArray(commItem.value.image_urls) && commItem.value.image_urls.length > 0) {
    return commItem.value.image_urls
  }
  if (commItem.value.image_url) {
    return [commItem.value.image_url]
  }
  return []
})

function openImageModal(url: string) {
  activeModalImage.value = url
  isImageModalOpen.value = true
}

const pageHeading = computed(() => {
  if (commItem.value) {
    const code = commItem.value.category_code || commItem.value.category_name || ''
    const subject = commItem.value.subject || ''
    return code ? `${code} - ${subject}` : subject
  }
  return 'Communication Details'
})

function getStatusClass(status: string): string {
  const s = (status || '').toLowerCase()
  if (s === 'pending') return 'badge-pending'
  if (s === 'in progress') return 'badge-progress'
  if (s === 'released') return 'badge-released'
  if (s === 'completed') return 'badge-completed'
  if (s === 'archived') return 'badge-archived'
  return 'badge-pending'
}

function goBackList() {
  const isOutgoing = commItem.value?.communication_type === 'Outgoing'
  router.push(isOutgoing ? '/communications/outgoing' : '/communications/incoming')
}

function goToEdit() {
  router.push(`/communications/detail/${commId.value}/edit`)
}

async function loadData() {
  loading.value = true
  pageError.value = ''

  try {
    const listRes = await fetchCommunications()

    if (listRes.success && Array.isArray(listRes.data)) {
      const item = listRes.data.find((c: any) => c.id === commId.value)
      if (item) {
        commItem.value = item
        
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

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.communication-detail-page {
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

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
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

.back-btn:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.edit-btn {
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

.edit-btn:hover {
  background: #1d4ed8;
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
  gap: 20px;
}

.form-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

.details-card {
  border-left: 4px solid #2563eb;
}

.summary-top {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.subject-heading {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 16px 0;
  line-height: 1.4;
}

.tags-container {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tag-badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.category-tag {
  background: #e0f2fe;
  color: #0369a1;
}

.purpose-tag {
  background: #fef3c7;
  color: #b45309;
}

.type-badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
}

.badge-incoming {
  background: #dbeafe;
  color: #1d4ed8;
}

.badge-outgoing {
  background: #fae8ff;
  color: #86198f;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.badge-pending {
  background: #fef3c7;
  color: #92400e;
}

.badge-progress {
  background: #e0f2fe;
  color: #075985;
}

.badge-released {
  background: #f3e8ff;
  color: #6b21a8;
}

.badge-completed {
  background: #dcfce7;
  color: #166534;
}

.badge-archived {
  background: #f1f5f9;
  color: #475569;
}

.card-header {
  padding: 16px 24px;
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

.info-row-single {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
  align-items: flex-start;
  width: 100%;
}

@media (max-width: 992px) {
  .info-row-single {
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  }
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-item.full-width {
  grid-column: 1 / -1;
  margin-top: 8px;
  padding-top: 16px;
  border-top: 1px solid #f1f5f9;
}

.subject-text-detail {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.5;
}

.info-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 14px;
  color: #1e293b;
}

.font-semibold {
  font-weight: 600;
}

.font-bold {
  font-weight: 700;
}

.text-primary {
  color: #2563eb;
}

.mt-20 {
  margin-top: 20px;
}

/* Screenshot & Gallery Attachment Styles */
.attachments-gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.image-preview-container {
  display: flex;
  justify-content: center;
  align-items: center;
}

.image-wrapper {
  position: relative;
  max-width: 100%;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.image-wrapper:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.screenshot-img {
  max-width: 100%;
  max-height: 480px;
  object-fit: contain;
  display: block;
}

.overlay-hint {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  color: #ffffff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 8px;
  opacity: 0;
  transition: opacity 0.2s ease;
  font-weight: 600;
  font-size: 14px;
}

.image-wrapper:hover .overlay-hint {
  opacity: 1;
}

.expand-icon {
  font-size: 28px;
}

.empty-image-container {
  padding: 40px 20px;
  text-align: center;
  background: #f8fafc;
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.empty-image-icon {
  font-size: 44px;
  color: #94a3b8;
}

.empty-image-text {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.upload-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid #2563eb;
  background: #eff6ff;
  color: #2563eb;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.upload-link-btn:hover {
  background: #2563eb;
  color: #ffffff;
}

/* Lightbox Modal */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
}

.lightbox-content {
  position: relative;
  max-width: 92vw;
  max-height: 92vh;
}

.lightbox-img {
  max-width: 100%;
  max-height: 88vh;
  border-radius: 8px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.lightbox-close-btn {
  position: absolute;
  top: -16px;
  right: -16px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: #ffffff;
  color: #0f172a;
  font-size: 24px;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
  transition: all 0.2s ease;
}

.lightbox-close-btn:hover {
  background: #ef4444;
  color: #ffffff;
}
</style>
