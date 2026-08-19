<template>
  <header class="app-header">

    <!-- Left Section -->
    <div class="header-left">
      <button class="menu-button" type="button" aria-label="Toggle Sidebar Menu" @click="toggleSidebar">
        <ion-icon :icon="menuOutline"></ion-icon>
      </button>

      <router-link to="/home" class="brand-link">
        <img
          src="../assets/logo.png"
          alt="6IS Logo"
          class="header-logo"
        />

        <div class="header-title">
          <h1>6IS</h1>
          <p>Integrated Information System</p>
        </div>
      </router-link>
    </div>

    <!-- Right Section -->
    <div class="header-right">

      <button class="header-icon-button notification-btn" type="button" aria-label="Notifications">
        <ion-icon :icon="notificationsOutline"></ion-icon>
        <span class="notification-badge">3</span>
      </button>

      <button class="user-button" type="button">
        <ion-icon :icon="personCircleOutline" class="user-avatar-icon"></ion-icon>
        <span>{{ activeUser?.username || 'User' }}</span>
        <ion-icon :icon="chevronDownOutline" class="user-chevron"></ion-icon>
      </button>

      <button class="logout-button" type="button" aria-label="Logout" title="Logout" @click="handleLogout">
        <ion-icon :icon="logOutOutline"></ion-icon>
      </button>

    </div>

  </header>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  menuOutline,
  notificationsOutline,
  personCircleOutline,
  logOutOutline,
  chevronDownOutline
} from 'ionicons/icons'
import { activeUser, logout } from '../services/authService'
import { useSidebar } from '../composables/useSidebar'

import '../assets/styles/layouts/header.css'

const router = useRouter()
const { toggleSidebar } = useSidebar()

async function handleLogout() {
  await logout()
  router.replace('/login')
}
</script>

<style scoped>
.brand-link {
  display: flex;
  align-items: center;
  gap: 16px;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
}

.brand-link:hover {
  opacity: 0.95;
}

.header-logo {
  height: 38px;
  width: auto;
}

.notification-btn {
  position: relative;
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -6px;
  background: #ef4444;
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  border-radius: 10px;
  padding: 1px 5px;
  border: 1.5px solid #082F6D;
}

.user-avatar-icon {
  font-size: 22px;
}

.user-chevron {
  font-size: 14px;
  opacity: 0.8;
}

.logout-button {
  cursor: pointer;
  background: transparent;
  border: none;
  color: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logout-button:hover {
  opacity: 0.8;
}
</style>