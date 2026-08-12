<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="close">
    <div class="modal-card">
      <!-- Header -->
      <div class="modal-header">
        <h3>{{ isEdit ? 'Edit Communication Record' : 'Log Communication Record' }}</h3>
        <button class="close-btn" type="button" @click="close">&times;</button>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm">
        <div class="modal-body">
          <!-- Communication Type -->
          <div class="form-group">
            <label for="communication_type">Communication Direction / Type <span class="required">*</span></label>
            <select
              id="communication_type"
              v-model="form.communication_type"
              :class="{ 'input-error': errors.communication_type }"
            >
              <option value="Incoming">Incoming Communication</option>
              <option value="Outgoing">Outgoing Communication</option>
            </select>
            <span v-if="errors.communication_type" class="error-msg">{{ errors.communication_type }}</span>
          </div>

          <!-- Responsible Office -->
          <div class="form-group">
            <label for="office_id">Responsible / Originating Office <span class="required">*</span></label>
            <select
              id="office_id"
              v-model.number="form.office_id"
              :class="{ 'input-error': errors.office_id }"
            >
              <option :value="0" disabled>Select Office</option>
              <option v-for="off in options.offices" :key="off.id" :value="off.id">
                {{ off.office_name }} ({{ off.office_abbv || off.office_code }})
              </option>
            </select>
            <span v-if="errors.office_id" class="error-msg">{{ errors.office_id }}</span>
          </div>

          <!-- Grid Row: Category & Purpose -->
          <div class="form-row">
            <div class="form-group half-width">
              <label for="category_id">Category <span class="required">*</span></label>
              <select
                id="category_id"
                v-model.number="form.category_id"
                :class="{ 'input-error': errors.category_id }"
              >
                <option :value="0" disabled>Select Category</option>
                <option v-for="cat in options.categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }} {{ cat.code ? `(${cat.code})` : '' }}
                </option>
              </select>
              <span v-if="errors.category_id" class="error-msg">{{ errors.category_id }}</span>
            </div>

            <div class="form-group half-width">
              <label for="purpose_id">Purpose <span class="required">*</span></label>
              <select
                id="purpose_id"
                v-model.number="form.purpose_id"
                :class="{ 'input-error': errors.purpose_id }"
              >
                <option :value="0" disabled>Select Purpose</option>
                <option v-for="pur in options.purposes" :key="pur.id" :value="pur.id">
                  {{ pur.name }}
                </option>
              </select>
              <span v-if="errors.purpose_id" class="error-msg">{{ errors.purpose_id }}</span>
            </div>
          </div>

          <!-- Grid Row: Date & Status -->
          <div class="form-row">
            <div class="form-group half-width">
              <label for="communication_date">Communication Date <span class="required">*</span></label>
              <input
                id="communication_date"
                v-model="form.communication_date"
                type="date"
                :class="{ 'input-error': errors.communication_date }"
              />
              <span v-if="errors.communication_date" class="error-msg">{{ errors.communication_date }}</span>
            </div>

            <div class="form-group half-width">
              <label for="status">Status <span class="required">*</span></label>
              <select id="status" v-model="form.status">
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Released">Released</option>
                <option value="Archived">Archived</option>
              </select>
            </div>
          </div>

          <!-- Subject -->
          <div class="form-group">
            <label for="subject">Subject / Title <span class="required">*</span></label>
            <textarea
              id="subject"
              v-model="form.subject"
              rows="3"
              placeholder="Enter details of the subject or description..."
              :class="{ 'input-error': errors.subject }"
            ></textarea>
            <span v-if="errors.subject" class="error-msg">{{ errors.subject }}</span>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button class="btn-cancel" type="button" @click="close">Cancel</button>
          <button class="btn-save" type="submit" :disabled="submitting">
            <span v-if="submitting">Saving...</span>
            <span v-else>{{ isEdit ? 'Update Communication' : 'Save Communication' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import type {
  Communication,
  CommunicationOptions,
  CommunicationFormPayload
} from '../../types/communication'
import {
  createCommunication,
  updateCommunication
} from '../../services/communicationService'

const props = defineProps<{
  isOpen: boolean;
  options: CommunicationOptions;
  editData?: Communication | null;
  defaultType?: 'Incoming' | 'Outgoing';
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>()

const submitting = ref(false)
const errors = reactive<Record<string, string>>({})

const isEdit = computed(() => !!props.editData?.id)

const form = reactive<CommunicationFormPayload>({
  communication_type: 'Incoming',
  office_id: 0,
  category_id: 0,
  purpose_id: 0,
  subject: '',
  communication_date: new Date().toISOString().split('T')[0],
  status: 'Pending'
})

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    clearErrors()
    if (props.editData) {
      form.id = props.editData.id
      form.communication_type = props.editData.communication_type || 'Incoming'
      form.office_id = props.editData.office_id
      form.category_id = props.editData.category_id
      form.purpose_id = props.editData.purpose_id
      form.subject = props.editData.subject || ''
      form.communication_date = props.editData.communication_date || new Date().toISOString().split('T')[0]
      form.status = props.editData.status || 'Pending'
    } else {
      form.id = undefined
      form.communication_type = props.defaultType || 'Incoming'
      form.office_id = props.options.offices.length > 0 ? props.options.offices[0].id : 0
      form.category_id = props.options.categories.length > 0 ? props.options.categories[0].id : 0
      form.purpose_id = props.options.purposes.length > 0 ? props.options.purposes[0].id : 0
      form.subject = ''
      form.communication_date = new Date().toISOString().split('T')[0]
      form.status = 'Pending'
    }
  }
})

function clearErrors() {
  Object.keys(errors).forEach((key) => delete errors[key])
}

function validate() {
  clearErrors()
  let valid = true

  if (!form.communication_type) {
    errors.communication_type = 'Communication type is required.'
    valid = false
  }

  if (!form.office_id || form.office_id <= 0) {
    errors.office_id = 'Please select a responsible office.'
    valid = false
  }

  if (!form.category_id || form.category_id <= 0) {
    errors.category_id = 'Please select a category.'
    valid = false
  }

  if (!form.purpose_id || form.purpose_id <= 0) {
    errors.purpose_id = 'Please select a purpose.'
    valid = false
  }

  if (!form.communication_date) {
    errors.communication_date = 'Communication date is required.'
    valid = false
  }

  if (!form.subject || form.subject.trim().length === 0) {
    errors.subject = 'Subject is required.'
    valid = false
  }

  return valid
}

async function submitForm() {
  if (!validate()) return

  submitting.value = true
  let res

  if (isEdit.value && form.id) {
    res = await updateCommunication(form.id, form)
  } else {
    res = await createCommunication(form)
  }

  submitting.value = false

  if (res.success) {
    emit('saved')
    emit('close')
  } else if (res.errors) {
    Object.assign(errors, res.errors)
  } else {
    alert(res.message || 'Operation failed.')
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
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 600px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  color: #64748b;
  cursor: pointer;
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-height: 70vh;
  overflow-y: auto;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-row {
  display: flex;
  gap: 16px;
}

.half-width {
  flex: 1;
}

label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.required {
  color: #ef4444;
}

input, select, textarea {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
  transition: border-color 0.15s ease;
}

input:focus, select:focus, textarea:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.input-error {
  border-color: #ef4444 !important;
}

.error-msg {
  font-size: 12px;
  color: #ef4444;
}

.modal-footer {
  padding: 16px 24px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-cancel {
  background: #ffffff;
  color: #475569;
  border: 1px solid #cbd5e1;
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn-save {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 9px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.btn-save:hover { background: #1d4ed8; }
.btn-save:disabled { opacity: 0.7; cursor: not-allowed; }

@media (max-width: 640px) {
  .form-row {
    flex-direction: column;
    gap: 16px;
  }
  .modal-card {
    max-height: 90vh;
  }
  .modal-body {
    padding: 16px;
  }
}
</style>
