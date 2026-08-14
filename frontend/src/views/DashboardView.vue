<template>
  <MainLayout title="Home">
    <div class="dashboard-page">
      
      <!-- Hero Greeting & Date Section -->
      <div class="dashboard-hero">
        <div class="greeting-box">
          <h1 class="greeting-title">Welcome back, {{ activeUser?.username || 'User' }}! 👋</h1>
          <p class="greeting-subtitle">Welcome back to 6IS. Please select a module to get started.</p>
        </div>

        <div class="date-widget-card">
          <div class="date-icon-box">
            <ion-icon :icon="calendarOutline"></ion-icon>
          </div>
          <div class="date-info">
            <span class="date-string">{{ currentDateFormatted }}</span>
            <span class="day-string">{{ currentDayOfWeek }}</span>
          </div>
        </div>
      </div>

      <!-- Module Launcher Cards -->
      <div class="module-cards-grid">

        <!-- Inventory Module Card -->
        <ion-card class="module-card inventory-card" @click="goToInventory">
          <ion-card-header>
            <div class="module-icon-box inventory-icon">
              <ion-icon :icon="cubeOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Inventory</ion-card-title>
            <p class="module-desc">Manage equipment, assets and inventory records.</p>
          </ion-card-header>
        </ion-card>

        <!-- Communications Module Card -->
        <ion-card class="module-card communications-card" @click="goToCommunications">
          <ion-card-header>
            <div class="module-icon-box communications-icon">
              <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Communications</ion-card-title>
            <p class="module-desc">Messages, announcements and system communications.</p>
          </ion-card-header>
        </ion-card>

        <!-- Accomplishments Module Card -->
        <ion-card class="module-card accomplishments-card" @click="goToAccomplishments">
          <ion-card-header>
            <div class="module-icon-box accomplishments-icon">
              <ion-icon :icon="clipboardOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Accomplishments</ion-card-title>
            <p class="module-desc">Record daily accomplishments and generate reports.</p>
          </ion-card-header>
        </ion-card>

        <!-- Administrator Module Card (Displayed only for Administrator role) -->
        <ion-card
          v-if="activeUser?.role === 'Administrator'"
          class="module-card admin-card"
          @click="goToAdministrator"
        >
          <ion-card-header>
            <div class="module-icon-box admin-icon">
              <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Administrator</ion-card-title>
            <p class="module-desc">System administration and configuration tools.</p>
          </ion-card-header>
        </ion-card>

      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import {
  IonCard,
  IonCardHeader,
  IonCardTitle,
  IonIcon
} from '@ionic/vue'
import {
  calendarOutline,
  cubeOutline,
  chatbubbleEllipsesOutline,
  clipboardOutline,
  shieldCheckmarkOutline
} from 'ionicons/icons'

import MainLayout from '../layouts/MainLayout.vue'
import { activeUser } from '../services/authService'
import '../assets/styles/pages/dashboard.css'

const router = useRouter()

const currentDateFormatted = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
})

const currentDayOfWeek = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long'
  })
})

function goToInventory() {
  router.push('/inventory')
}

function goToCommunications() {
  router.push('/communications')
}

function goToAccomplishments() {
  router.push('/accomplishments')
}

function goToAdministrator() {
  router.push('/administrator')
}
</script>