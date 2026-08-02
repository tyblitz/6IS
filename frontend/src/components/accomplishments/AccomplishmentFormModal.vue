<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="close">
    <div class="modal-content">
      
      <div class="modal-header">
        <h2>{{ isEditing ? 'Edit Accomplishment' : 'Add New Accomplishment' }}</h2>
        <button class="close-btn" type="button" @click="close">&times;</button>
      </div>

      <form @submit.prevent="handleSubmit" class="form-body">

        <div v-if="serverError" class="alert-banner danger">
          {{ serverError }}
        </div>

        <!-- Title -->
        <div class="form-group">
          <label for="title">Title <span class="required">*</span></label>
          <input
            id="title"
            v-model="form.title"
            type="text"
            placeholder="Enter accomplishment title"
            :class="{ 'has-error': fieldErrors.title }"
          />
          <span v-if="fieldErrors.title" class="error-msg">{{ fieldErrors.title }}</span>
        </div>

        <!-- Office & Category Row -->
        <div class="form-row">
          <div class="form-group col">
            <label for="office_id">Office <span class="required">*</span></label>
            <select
              id="office_id"
              v-model.number="form.office_id"
              :class="{ 'has-error': fieldErrors.office_id }"
            >
              <option :value="0" disabled>Select Office</option>
              <option v-for="opt in options.offices" :key="opt.id" :value="opt.id">
                {{ opt.office_name }} ({{ opt.office_code }})
              </option>
            </select>
            <span v-if="fieldErrors.office_id" class="error-msg">{{ fieldErrors.office_id }}</span>
          </div>

          <div class="form-group col">
            <label for="category_id">Category <span class="required">*</span></label>
            <select
              id="category_id"
              v-model.number="form.category_id"
              :class="{ 'has-error': fieldErrors.category_id }"
            >
              <option :value="0" disabled>Select Category</option>
              <option v-for="opt in options.categories" :key="opt.id" :value="opt.id">
                {{ opt.category_name }}
              </option>
            </select>
            <span v-if="fieldErrors.category_id" class="error-msg">{{ fieldErrors.category_id }}</span>
          </div>
        </div>

        <!-- Assigned Employee & Priority Row -->
        <div class="form-row">
          <div class="form-group col">
            <label for="assigned_employee_id">Assigned Employee <span class="required">*</span></label>
            <select
              id="assigned_employee_id"
              v-model.number="form.assigned_employee_id"
              :class="{ 'has-error': fieldErrors.assigned_employee_id }"
            >
              <option :value="0" disabled>Select Employee</option>
              <option v-for="opt in options.users" :key="opt.id" :value="opt.id">
                {{ opt.full_name }}
              </option>
            </select>
            <span v-if="fieldErrors.assigned_employee_id" class="error-msg">{{ fieldErrors.assigned_employee_id }}</span>
          </div>

          <div class="form-group col">
            <label for="priority">Priority <span class="required">*</span></label>
            <select
              id="priority"
              v-model="form.priority"
              :class="{ 'has-error': fieldErrors.priority }"
            >
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
              <option value="Critical">Critical</option>
            </select>
            <span v-if="fieldErrors.priority" class="error-msg">{{ fieldErrors.priority }}</span>
          </div>
        </div>

        <!-- Dates & Status Row -->
        <div class="form-row">
          <div class="form-group col">
            <label for="date_started">Date Started <span class="required">*</span></label>
            <input
              id="date_started"
              v-model="form.date_started"
              type="date"
              :class="{ 'has-error': fieldErrors.date_started }"
            />
            <span v-if="fieldErrors.date_started" class="error-msg">{{ fieldErrors.date_started }}</span>
          </div>

          <div class="form-group col">
            <label for="date_completed">Date Completed</label>
            <input
              id="date_completed"
              v-model="form.date_completed"
              type="date"
              :class="{ 'has-error': fieldErrors.date_completed }"
            />
            <span v-if="fieldErrors.date_completed" class="error-msg">{{ fieldErrors.date_completed }}</span>
          </div>

          <div class="form-group col">
            <label for="status">Status <span class="required">*</span></label>
            <select
              id="status"
              v-model="form.status"
              :class="{ 'has-error': fieldErrors.status }"
            >
              <option value="Pending">Pending</option>
              <option value="Ongoing">Ongoing</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
            <span v-if="fieldErrors.status" class="error-msg">{{ fieldErrors.status }}</span>
          </div>
        </div>

        <!-- Description -->
        <div class="form-group">
          <label for="description">Description</label>
          <textarea
            id="description"
            v-model="form.description"
            rows="3"
            placeholder="Enter accomplishment details or background context..."
          ></textarea>
        </div>

        <!-- Remarks -->
        <div class="form-group">
          <label for="remarks">Remarks</label>
          <textarea
            id="remarks"
            v-model="form.remarks"
            rows="2"
            placeholder="Additional notes or remarks..."
          ></textarea>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" @click="close" :disabled="submitting">
            Cancel
          </button>
          <button class="btn btn-primary" type="submit" :disabled="submitting">
            <ion-spinner v-if="submitting" name="crescent" class="btn-spinner"></ion-spinner>
            <span>{{ isEditing ? 'Update Record' : 'Save Accomplishment' }}</span>
          </button>
        </div>

      </form>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import { IonSpinner } from '@ionic/vue'
import type {
  Accomplishment,
  AccomplishmentFormPayload,
  AccomplishmentOptions
} from '../../types/accomplishment'
import {
  createAccomplishment,
  updateAccomplishment
} from '../../services/accomplishmentService'

const props = defineProps<{
  isOpen: boolean;
  options: AccomplishmentOptions;
  editData?: Accomplishment | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>()

const isEditing = computed(() => !!props.editData?.id)
const submitting = ref(false)
const serverError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const form = reactive<AccomplishmentFormPayload>({
  title: '',
  description: '',
  office_id: 0,
  category_id: 0,
  assigned_employee_id: 0,
  date_started: new Date().toISOString().split('T')[0],
  date_completed: '',
  status: 'Pending',
  priority: 'Medium',
  remarks: ''
})

watch(() => props.isOpen, (open) => {
  if (open) {
    resetForm()
    if (props.editData) {
      form.id = props.editData.id
      form.title = props.editData.title || ''
      form.description = props.editData.description || ''
      form.office_id = props.editData.office_id || 0
      form.category_id = props.editData.category_id || 0
      form.assigned_employee_id = props.editData.assigned_employee_id || 0
      form.date_started = props.editData.date_started || new Date().toISOString().split('T')[0]
      form.date_completed = props.editData.date_completed || ''
      form.status = props.editData.status || 'Pending'
      form.priority = props.editData.priority || 'Medium'
      form.remarks = props.editData.remarks || ''
    }
  }
})

function resetForm() {
  serverError.value = ''
  Object.keys(fieldErrors).forEach(key => delete fieldErrors[key])
  form.id = undefined
  form.title = ''
  form.description = ''
  form.office_id = props.options.offices[0]?.id || 0
  form.category_id = props.options.categories[0]?.id || 0
  form.assigned_employee_id = props.options.users[0]?.id || 0
  form.date_started = new Date().toISOString().split('T')[0]
  form.date_completed = ''
  form.status = 'Pending'
  form.priority = 'Medium'
  form.remarks = ''
}

function validateClient(): boolean {
  Object.keys(fieldErrors).forEach(key => delete fieldErrors[key])
  let valid = true

  if (!form.title || !form.title.trim()) {
    fieldErrors.title = 'Title is required.'
    valid = false
  }

  if (!form.office_id || form.office_id <= 0) {
    fieldErrors.office_id = 'Office selection is required.'
    valid = false
  }

  if (!form.category_id || form.category_id <= 0) {
    fieldErrors.category_id = 'Category selection is required.'
    valid = false
  }

  if (!form.assigned_employee_id || form.assigned_employee_id <= 0) {
    fieldErrors.assigned_employee_id = 'Employee selection is required.'
    valid = false
  }

  if (!form.date_started) {
    fieldErrors.date_started = 'Date started is required.'
    valid = false
  }

  if (form.date_started && form.date_completed) {
    if (new Date(form.date_completed) < new Date(form.date_started)) {
      fieldErrors.date_completed = 'Completion date cannot precede start date.'
      valid = false
    }
  }

  return valid
}

async function handleSubmit() {
  if (!validateClient()) return

  submitting.value = true
  serverError.value = ''

  const res = isEditing.value && form.id
    ? await updateAccomplishment(form.id, form)
    : await createAccomplishment(form)

  submitting.value = false

  if (res.success) {
    emit('saved')
    close()
  } else {
    serverError.value = res.message
    if (res.errors) {
      Object.assign(fieldErrors, res.errors)
    }
  }
}

function close() {
  emit('close')
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-content {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 680px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.modal-header {
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.close-btn {
  background: transparent;
  border: none;
  font-size: 24px;
  color: #9ca3af;
  cursor: pointer;
}

.form-body {
  padding: 20px 24px;
  overflow-y: auto;
}

.alert-banner {
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  margin-bottom: 16px;
}

.alert-banner.danger {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.form-group {
  margin-bottom: 16px;
  display: flex;
  flex-direction: column;
}

.form-row {
  display: flex;
  gap: 16px;
}

.form-row .col {
  flex: 1;
}

label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.required {
  color: #dc2626;
}

input, select, textarea {
  width: 100%;
  padding: 8px 12px;
  font-size: 14px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  outline: none;
  transition: border-color 0.15s ease;
  background: #ffffff;
}

input:focus, select:focus, textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

input.has-error, select.has-error {
  border-color: #dc2626;
}

.error-msg {
  font-size: 12px;
  color: #dc2626;
  margin-top: 4px;
}

.modal-footer {
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  padding: 8px 18px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover {
  background: #e5e7eb;
}

.btn-primary {
  background: #2563eb;
  color: #ffffff;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-spinner {
  width: 16px;
  height: 16px;
}
</style>
