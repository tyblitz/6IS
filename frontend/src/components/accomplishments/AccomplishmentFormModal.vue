<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="close">
    <div class="modal-card">
      
      <!-- Header -->
      <div class="modal-header">
        <h3>{{ isEdit ? 'Edit Daily Accomplishment' : (calendarPrefillData ? 'Create Accomplishment from Calendar Event' : 'Add Daily Accomplishment') }}</h3>
        <button class="close-btn" type="button" @click="close">&times;</button>
      </div>

      <!-- Calendar Event Banner -->
      <div v-if="calendarPrefillData" class="calendar-banner">
        <span class="banner-tag">Linked Calendar Event #{{ calendarPrefillData.calendar_event_id }}</span>
        <strong>{{ calendarPrefillData.title }}</strong>
      </div>

      <!-- Form -->
      <form @submit.prevent="submitForm">
        <div class="modal-body">

          <!-- Office Field -->
          <div class="form-group">
            <label for="office_id">Office <span class="required">*</span></label>
            <select
              id="office_id"
              v-model.number="form.office_id"
              :class="{ 'input-error': errors.office_id }"
            >
              <option :value="0" disabled>Select Office</option>
              <option v-for="off in options.offices" :key="off.id" :value="off.id">
                {{ off.office_name }} ({{ off.office_code }})
              </option>
            </select>
            <span v-if="errors.office_id" class="error-msg">{{ errors.office_id }}</span>
          </div>

          <!-- Category Field -->
          <div class="form-group">
            <label for="category_id">Category <span class="required">*</span></label>
            <select
              id="category_id"
              v-model.number="form.category_id"
              :class="{ 'input-error': errors.category_id }"
            >
              <option :value="0" disabled>Select Category</option>
              <option v-for="cat in options.categories || []" :key="cat.id" :value="cat.id">
                {{ cat.category_name }}{{ cat.category_code ? ` (${cat.category_code})` : '' }}
              </option>
            </select>
            <span v-if="errors.category_id" class="error-msg">{{ errors.category_id }}</span>
          </div>

          <!-- Date Field -->
          <div class="form-group">
            <label for="date">Date <span class="required">*</span></label>
            <input
              id="date"
              v-model="form.date"
              type="date"
              :class="{ 'input-error': errors.date }"
            />
            <span v-if="errors.date" class="error-msg">{{ errors.date }}</span>
          </div>

          <!-- Description -->
          <div class="form-group">
            <label for="description">Description <span class="required">*</span></label>
            <textarea
              id="description"
              v-model="form.description"
              rows="4"
              placeholder="Describe the completed accomplishment..."
              :class="{ 'input-error': errors.description }"
            ></textarea>
            <span v-if="errors.description" class="error-msg">{{ errors.description }}</span>
          </div>

          <!-- Remarks Field -->
          <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea
              id="remarks"
              v-model="form.remarks"
              rows="3"
              placeholder="Additional notes or remarks (optional)..."
            ></textarea>
          </div>

        </div>

        <!-- Footer -->
        <div class="modal-footer">
          <button class="btn-cancel" type="button" @click="close">Cancel</button>
          <button class="btn-save" type="submit" :disabled="submitting">
            <span v-if="submitting">Saving...</span>
            <span v-else>{{ isEdit ? 'Update Accomplishment' : 'Save Accomplishment' }}</span>
          </button>
        </div>
      </form>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import type {
  AccomplishmentItem,
  AccomplishmentOptions,
  AccomplishmentFormPayload
} from '../../types/accomplishment'
import {
  createAccomplishment,
  updateAccomplishment
} from '../../services/accomplishmentService'
import { createAccomplishmentFromEvent } from '../../services/calendarService'

const props = defineProps<{
  isOpen: boolean;
  options: AccomplishmentOptions;
  editData?: AccomplishmentItem | null;
  calendarPrefillData?: {
    calendar_event_id: number;
    title: string;
    description?: string;
    date: string;
    location?: string;
    priority?: string;
  } | null;
}>()

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>()

const submitting = ref(false)
const errors = reactive<Record<string, string>>({})

const isEdit = computed(() => !!props.editData?.id)

const form = reactive<AccomplishmentFormPayload & { calendar_event_id?: number }>({
  office_id: 0,
  category_id: 1,
  date: new Date().toISOString().split('T')[0],
  description: '',
  remarks: '',
  calendar_event_id: undefined
})

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    clearErrors()
    if (props.editData) {
      form.id = props.editData.id
      form.office_id = props.editData.office_id
      form.category_id = props.editData.category_id || (props.options.categories?.length ? props.options.categories[0].id : 1)
      form.date = props.editData.date
      form.description = props.editData.description
      form.remarks = props.editData.remarks || ''
      form.calendar_event_id = undefined
    } else if (props.calendarPrefillData) {
      form.id = undefined
      form.office_id = props.options.offices.length > 0 ? props.options.offices[0].id : 1
      form.category_id = props.options.categories?.length ? props.options.categories[0].id : 1
      form.date = props.calendarPrefillData.date || new Date().toISOString().split('T')[0]
      form.description = props.calendarPrefillData.title + (props.calendarPrefillData.description ? ' - ' + props.calendarPrefillData.description : '')
      form.remarks = props.calendarPrefillData.location ? `Location: ${props.calendarPrefillData.location}` : ''
      form.calendar_event_id = props.calendarPrefillData.calendar_event_id
    } else {
      form.id = undefined
      form.office_id = props.options.offices.length > 0 ? props.options.offices[0].id : 0
      form.category_id = props.options.categories?.length ? props.options.categories[0].id : 1
      form.date = new Date().toISOString().split('T')[0]
      form.description = ''
      form.remarks = ''
      form.calendar_event_id = undefined
    }
  }
})

function clearErrors() {
  Object.keys(errors).forEach((key) => delete errors[key])
}

function validate() {
  clearErrors()
  let valid = true

  if (!form.office_id || form.office_id <= 0) {
    errors.office_id = 'Please select an office.'
    valid = false
  }

  if (!form.category_id || form.category_id <= 0) {
    errors.category_id = 'Please select a category.'
    valid = false
  }

  if (!form.date) {
    errors.date = 'Date is required.'
    valid = false
  }

  if (!form.description || form.description.trim().length === 0) {
    errors.description = 'Accomplishment description is required.'
    valid = false
  }

  return valid
}

async function submitForm() {
  if (!validate()) return

  submitting.value = true
  let success = false
  let errorMsg = 'Operation failed.'

  if (isEdit.value && form.id) {
    const res = await updateAccomplishment(form.id, form)
    success = res.success
    if (res.errors) Object.assign(errors, res.errors)
    if (res.message) errorMsg = res.message
  } else if (form.calendar_event_id) {
    const res = await createAccomplishmentFromEvent({
      calendar_event_id: form.calendar_event_id,
      office_id: form.office_id,
      category_id: 1,
      date: form.date,
      description: form.description,
      remarks: form.remarks || ''
    })
    success = res.success
    if (res.message) errorMsg = res.message
  } else {
    const res = await createAccomplishment(form)
    success = res.success
    if (res.errors) Object.assign(errors, res.errors)
    if (res.message) errorMsg = res.message
  }

  submitting.value = false

  if (success) {
    emit('saved')
    emit('close')
  } else {
    alert(errorMsg)
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
  z-index: 9999;
  padding: 16px;
}

.modal-card {
  background: #ffffff;
  border-radius: 16px;
  width: 100%;
  max-width: 560px;
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

.calendar-banner {
  background: #f0fdf4;
  border-left: 4px solid #16a34a;
  padding: 12px 24px;
}

.banner-tag {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  color: #15803d;
  text-transform: uppercase;
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
</style>
