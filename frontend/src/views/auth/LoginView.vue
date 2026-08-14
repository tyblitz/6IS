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

      <!-- Sample / Demo Accounts Box -->
      <div class="sample-accounts-box">
        <div class="sample-title-row">
          <ion-icon :icon="keyOutline" class="sample-title-icon" />
          <span>Sample Development Accounts (Click to Fill):</span>
        </div>

        <div class="sample-badges">
          <button
            type="button"
            class="account-chip admin-chip"
            @click="fillAccount('Admin01', 'adminpassword01')"
            title="Click to fill Admin01 credentials"
          >
            <span class="role-tag admin-tag">Admin</span>
            <div class="chip-text">
              <span class="cred-user">Admin01</span>
              <span class="cred-sep">/</span>
              <span class="cred-pass">adminpassword01</span>
            </div>
          </button>

          <button
            type="button"
            class="account-chip user-chip"
            @click="fillAccount('User01', 'userpassword01')"
            title="Click to fill User01 credentials"
          >
            <span class="role-tag user-tag">User</span>
            <div class="chip-text">
              <span class="cred-user">User01</span>
              <span class="cred-sep">/</span>
              <span class="cred-pass">userpassword01</span>
            </div>
          </button>
        </div>
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
import { personOutline, lockClosedOutline, alertCircleOutline, keyOutline } from 'ionicons/icons'
import { login } from '../../services/authService'

const router = useRouter()

const form = ref({
  username: '',
  password: ''
})

const loading = ref(false)
const errorMessage = ref('')

function fillAccount(username: string, pass: string) {
  form.value.username = username
  form.value.password = pass
  errorMessage.value = ''
}

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
  max-width: 440px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  padding: 36px 32px 32px 32px;
  box-sizing: border-box;
}

.brand-header {
  text-align: center;
  margin-bottom: 24px;
}

.brand-logo {
  height: 60px;
  width: auto;
  margin-bottom: 10px;
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

/* Sample / Demo Accounts Styles */
.sample-accounts-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 20px;
}

.sample-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  margin-bottom: 10px;
}

.sample-title-icon {
  font-size: 15px;
  color: #2563eb;
}

.sample-badges {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.account-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 12px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
  width: 100%;
}

.account-chip:hover {
  border-color: #2563eb;
  background: #eff6ff;
  transform: translateY(-1px);
}

.role-tag {
  font-size: 11px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

.admin-tag {
  background: #f3e8ff;
  color: #7e22ce;
}

.user-tag {
  background: #e0f2fe;
  color: #0369a1;
}

.chip-text {
  font-size: 12px;
  font-family: monospace;
  color: #334155;
  display: flex;
  align-items: center;
  gap: 6px;

}

.cred-user {
  font-weight: 700;
  color: #0f172a;
}

.cred-sep {
  color: #94a3b8;
}

.cred-pass {
  color: #64748b;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
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
  margin-top: 4px;
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
  margin-top: 24px;
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
