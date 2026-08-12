<template>
  <div class="login-page">
    <div class="login-card">
      <!-- 6IS Branding Header -->
      <div class="brand-header">
        <img src="../../assets/logo.png" alt="6IS Logo" class="brand-logo" />
        <h1 class="brand-title">6IS</h1>
        <p class="brand-subtitle">Integrated Information System</p>
      </div>

      <!-- Authentication Error Alert -->
      <div v-if="errorMessage" class="error-alert">
        <ion-icon :icon="alertCircleOutline" class="error-icon" />
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-wrapper">
            <ion-icon :icon="personOutline" class="input-icon" />
            <input
              id="username"
              v-model="form.username"
              type="text"
              placeholder="Enter your username"
              required
              autocomplete="username"
              :disabled="loading"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <ion-icon :icon="lockClosedOutline" class="input-icon" />
            <input
              id="password"
              v-model="form.password"
              type="password"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
              :disabled="loading"
            />
          </div>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading || !form.username || !form.password">
          <span v-if="loading" class="spinner"></span>
          <span>{{ loading ? 'Authenticating...' : 'Login' }}</span>
        </button>
      </form>

      <!-- Security Notice Footer -->
      <div class="card-footer">
        <p>Protected System — Unauthorized Access Prohibited</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import { personOutline, lockClosedOutline, alertCircleOutline } from 'ionicons/icons'
import { login } from '../../services/authService'

const router = useRouter()

const form = ref({
  username: '',
  password: ''
})

const loading = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  if (!form.value.username || !form.value.password || loading.value) return

  loading.value = true
  errorMessage.value = ''

  const res = await login({
    username: form.value.username,
    password: form.value.password
  })

  loading.value = false

  if (res.success && res.user) {
    router.replace('/home')
  } else {
    errorMessage.value = res.message || 'Invalid username or password.'
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #061e47 0%, #082f6d 50%, #0f172a 100%);
  padding: 24px;
  box-sizing: border-box;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  padding: 40px 32px 32px 32px;
  box-sizing: border-box;
}

.brand-header {
  text-align: center;
  margin-bottom: 28px;
}

.brand-logo {
  height: 64px;
  width: auto;
  margin-bottom: 12px;
}

.brand-title {
  font-size: 28px;
  font-weight: 800;
  color: #082f6d;
  margin: 0 0 4px 0;
  letter-spacing: -0.02em;
}

.brand-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
  font-weight: 500;
}

.error-alert {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
}

.error-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  font-size: 18px;
  color: #94a3b8;
  pointer-events: none;
}

.input-wrapper input {
  width: 100%;
  padding: 12px 14px 12px 42px;
  font-size: 14px;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  transition: all 0.15s ease;
  background: #f8fafc;
  color: #0f172a;
}

.input-wrapper input:focus {
  border-color: #2563eb;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.submit-btn {
  margin-top: 8px;
  width: 100%;
  padding: 13px;
  background: #082f6d;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.15s ease, transform 0.1s ease;
}

.submit-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #ffffff;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.card-footer {
  margin-top: 28px;
  text-align: center;
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
}

.card-footer p {
  font-size: 11px;
  color: #94a3b8;
  margin: 0;
}
</style>
