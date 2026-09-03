<template>
  <MainLayout title="Organization Profile">
    <div class="org-container">
      
      <!-- Top Action Bar -->
      <div class="header-action-bar">
        <div>
          <h2>Organization Profile</h2>
          <p class="subtitle">Maintain enterprise organization identity, headquarters location, and official communication channels.</p>
        </div>

        <div class="action-buttons">
          <button 
            v-if="canConfigure" 
            class="btn-primary" 
            @click="openEditModal"
          >
            <ion-icon :icon="createOutline" />
            <span>Edit Profile</span>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state">
        <span class="spinner"></span>
        <p>Loading organization profile...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="errorMessage" class="error-banner">
        <ion-icon :icon="alertCircleOutline" />
        <span>{{ errorMessage }}</span>
        <button class="retry-btn" @click="loadOrganization">Retry</button>
      </div>

      <!-- Organization Details Card -->
      <div v-else-if="org" class="org-card">
        <div class="org-card-header">
          <div class="org-identity">
            <div class="org-icon-box">
              <ion-icon :icon="businessOutline" />
            </div>
            <div>
              <div class="org-title-row">
                <h3 class="org-name">{{ org.name }}</h3>
                <span v-if="org.short_name" class="org-badge">{{ org.short_name }}</span>
                <span :class="['status-pill', org.is_active ? 'pill-active' : 'pill-inactive']">
                  {{ org.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <p class="org-sub-desc">{{ org.description || 'Primary platform organization.' }}</p>
            </div>
          </div>
        </div>

        <div class="org-details-grid">
          <div class="detail-item">
            <span class="detail-label">Full Organization Name</span>
            <span class="detail-value font-semibold">{{ org.name }}</span>
          </div>

          <div class="detail-item">
            <span class="detail-label">Abbreviation / Short Name</span>
            <span class="detail-value">{{ org.short_name || '—' }}</span>
          </div>

          <div class="detail-item full-width">
            <span class="detail-label">Command Headquarters / Address</span>
            <span class="detail-value">
              <ion-icon :icon="locationOutline" class="detail-inline-icon" />
              {{ org.address || 'Address not specified' }}
            </span>
          </div>

          <div class="detail-item">
            <span class="detail-label">Contact Telephone</span>
            <span class="detail-value">
              <ion-icon :icon="callOutline" class="detail-inline-icon" />
              {{ org.contact_number || '—' }}
            </span>
          </div>

          <div class="detail-item">
            <span class="detail-label">Official Email</span>
            <span class="detail-value">
              <ion-icon :icon="mailOutline" class="detail-inline-icon" />
              {{ org.email || '—' }}
            </span>
          </div>

          <div class="detail-item">
            <span class="detail-label">Platform Registration Date</span>
            <span class="detail-value">{{ org.created_at ? formatDate(org.created_at) : '—' }}</span>
          </div>

          <div class="detail-item">
            <span class="detail-label">Last Profile Update</span>
            <span class="detail-value">{{ org.updated_at ? formatDateTime(org.updated_at) : '—' }}</span>
          </div>
        </div>

        <div class="org-meta-footer">
          <div class="notice-box">
            <ion-icon :icon="informationCircleOutline" />
            <span>6IS operates with a single organization deployment architecture. All offices and users are anchored to this primary organization.</span>
          </div>
        </div>
      </div>

      <!-- Edit Organization Modal -->
      <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
        <div class="modal-dialog">
          <div class="modal-header">
            <h3>Edit Organization Profile</h3>
            <button class="close-btn" @click="closeModal">&times;</button>
          </div>

          <form @submit.prevent="handleSave">
            <div class="modal-body">
              <div class="form-group">
                <label for="orgName">Organization Name *</label>
                <input
                  id="orgName"
                  v-model="form.name"
                  type="text"
                  required
                  maxlength="255"
                  placeholder="e.g. 6th Infantry Division"
                  class="input-text"
                />
              </div>

              <div class="form-row">
                <div class="form-group flex-1">
                  <label for="orgShortName">Short Name / Code</label>
                  <input
                    id="orgShortName"
                    v-model="form.short_name"
                    type="text"
                    maxlength="50"
                    placeholder="e.g. 6ID"
                    class="input-text"
                  />
                </div>

                <div class="form-group flex-1">
                  <label for="orgContact">Contact Telephone</label>
                  <input
                    id="orgContact"
                    v-model="form.contact_number"
                    type="text"
                    maxlength="50"
                    placeholder="e.g. +63 (64) 431-0123"
                    class="input-text"
                  />
                </div>
              </div>

              <div class="form-group">
                <label for="orgEmail">Official Email</label>
                <input
                  id="orgEmail"
                  v-model="form.email"
                  type="email"
                  maxlength="100"
                  placeholder="e.g. contact@organization.mil.ph"
                  class="input-text"
                />
              </div>

              <div class="form-group">
                <label for="orgAddress">Headquarters Address</label>
                <textarea
                  id="orgAddress"
                  v-model="form.address"
                  rows="2"
                  placeholder="Enter physical headquarters location..."
                  class="input-textarea"
                ></textarea>
              </div>

              <div class="form-group">
                <label for="orgDescription">Mission / Description</label>
                <textarea
                  id="orgDescription"
                  v-model="form.description"
                  rows="3"
                  placeholder="Brief summary of organization mandate..."
                  class="input-textarea"
                ></textarea>
              </div>

              <div v-if="modalError" class="modal-error">
                <ion-icon :icon="alertCircleOutline" />
                <span>{{ modalError }}</span>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" class="btn-primary" :disabled="saving">
                {{ saving ? 'Saving Changes...' : 'Save Profile' }}
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
import { IonIcon } from '@ionic/vue'
import {
  businessOutline,
  createOutline,
  locationOutline,
  callOutline,
  mailOutline,
  alertCircleOutline,
  informationCircleOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { usePermissions } from '../../composables/usePermissions'
import { fetchOrganization, updateOrganization } from '../../services/organizationService'
import type { Organization, OrganizationUpdatePayload } from '../../types/organization'
import { formatDate as formatDisplayDate, formatDateTime as formatDisplayDateTime } from '../../utils/dateUtils'

const { hasPermission } = usePermissions()

const canConfigure = computed(() => hasPermission('organization', 'configure'))

const org = ref<Organization | null>(null)
const loading = ref(true)
const errorMessage = ref('')

const showModal = ref(false)
const saving = ref(false)
const modalError = ref('')

const form = ref<OrganizationUpdatePayload>({
  name: '',
  short_name: '',
  description: '',
  address: '',
  contact_number: '',
  email: ''
})

function formatDate(dateStr: string): string {
  try {
    return formatDisplayDate(dateStr)
  } catch {
    return dateStr
  }
}

function formatDateTime(dateStr: string): string {
  try {
    return formatDisplayDateTime(dateStr)
  } catch {
    return dateStr
  }
}

async function loadOrganization() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await fetchOrganization()
    if (data) {
      org.value = data
    } else {
      errorMessage.value = 'Failed to load organization profile.'
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Error communicating with server.'
  } finally {
    loading.value = false
  }
}

function openEditModal() {
  if (!org.value) return
  modalError.value = ''
  form.value = {
    name: org.value.name,
    short_name: org.value.short_name || '',
    description: org.value.description || '',
    address: org.value.address || '',
    contact_number: org.value.contact_number || '',
    email: org.value.email || ''
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  modalError.value = ''
}

async function handleSave() {
  modalError.value = ''
  if (!form.value.name.trim()) {
    modalError.value = 'Organization name cannot be empty.'
    return
  }

  saving.value = true
  try {
    const res = await updateOrganization(form.value)
    if (res.success && res.data) {
      org.value = res.data
      closeModal()
    } else {
      modalError.value = res.message || 'Failed to update organization profile.'
    }
  } catch (err: any) {
    modalError.value = err.message || 'Network error updating organization.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadOrganization()
})
</script>

<style scoped>
.org-container {
  padding: 1.5rem;
  max-width: 1000px;
  margin: 0 auto;
}

.header-action-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.header-action-bar h2 {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.subtitle {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 4rem 1rem;
  color: var(--color-text-secondary);
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid var(--color-border);
  border-top-color: var(--color-primary-light);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background-color: #FEF2F2;
  border: 1px solid #F87171;
  border-radius: var(--radius-sm);
  color: #991B1B;
  margin-bottom: 1.5rem;
}

.retry-btn {
  margin-left: auto;
  padding: 0.25rem 0.75rem;
  background: #FFFFFF;
  border: 1px solid #F87171;
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.org-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.org-card-header {
  padding: 1.5rem;
  border-bottom: 1px solid var(--color-border);
  background-color: var(--color-surface-hover);
}

.org-identity {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.org-icon-box {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-md);
  background-color: #EFF6FF;
  color: var(--color-primary-light);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  flex-shrink: 0;
  border: 1px solid #BFDBFE;
}

.org-title-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.org-name {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.org-badge {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-sm);
  background-color: #EFF6FF;
  border: 1px solid #93C5FD;
  color: var(--color-primary-light);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.status-pill {
  display: inline-block;
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 700;
}

.pill-active {
  background-color: #DCFCE7;
  color: #15803D;
  border: 1px solid #16A34A;
}

.pill-inactive {
  background-color: #FEE2E2;
  color: #B91C1C;
  border: 1px solid #DC2626;
}

.org-sub-desc {
  margin: 0.35rem 0 0;
  font-size: 0.875rem;
  color: var(--color-text-secondary);
}

.org-details-grid {
  padding: 1.5rem;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.detail-item.full-width {
  grid-column: span 2;
}

.detail-label {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-text-secondary);
}

.detail-value {
  font-size: 0.95rem;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.font-semibold {
  font-weight: 600;
}

.detail-inline-icon {
  color: var(--color-primary-light);
  font-size: 1.1rem;
}

.org-meta-footer {
  padding: 1rem 1.5rem;
  background-color: var(--color-surface-hover);
  border-top: 1px solid var(--color-border);
}

.notice-box {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.8125rem;
  color: var(--color-text-secondary);
}

.notice-box ion-icon {
  font-size: 1.1rem;
  color: var(--color-primary-light);
  flex-shrink: 0;
}

/* Modal styles */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-dialog {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 580px;
  overflow: hidden;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  border: 1px solid var(--color-border);
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--color-surface-hover);
}

.modal-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-primary-dark);
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--color-text-secondary);
  cursor: pointer;
  line-height: 1;
}

.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: flex;
  gap: 1rem;
}

.flex-1 {
  flex: 1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.form-group label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
}

.input-text,
.input-textarea {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  color: var(--color-text);
  font-family: inherit;
  outline: none;
  background-color: #FFFFFF;
}

.input-text:focus,
.input-textarea:focus {
  border-color: var(--color-primary-light);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
}

.input-textarea {
  resize: vertical;
}

.modal-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #FEF2F2;
  border: 1px solid #F87171;
  border-radius: var(--radius-sm);
  color: #991B1B;
  font-size: 0.8125rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--color-border);
  background-color: var(--color-surface-hover);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: var(--color-primary-light);
  color: #FFFFFF;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-primary:hover:not(:disabled) {
  background-color: #1D4ED8;
}

.btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background-color: #FFFFFF;
  color: #334155;
  border: 1px solid var(--color-border);
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-secondary:hover {
  background-color: var(--color-surface-hover);
}

@media (max-width: 640px) {
  .org-details-grid {
    grid-template-columns: 1fr;
  }
  .detail-item.full-width {
    grid-column: span 1;
  }
  .form-row {
    flex-direction: column;
  }
}
</style>
