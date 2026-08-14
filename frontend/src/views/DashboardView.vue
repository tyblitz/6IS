<template>
  <MainLayout title="Home">
    <div class="dashboard-page">
      
      <!-- HERO GREETING SECTION -->

      <!-- Administrator Dashboard Hero -->
      <div v-if="activeUser?.role === 'Administrator'" class="dashboard-hero admin-hero">
        <div class="greeting-box">
          <div class="role-badge-pill admin-pill">
            <ion-icon :icon="shieldCheckmarkOutline" />
            <span>Administrator Control Center</span>
          </div>
          <h1 class="greeting-title">Welcome back, {{ activeUser?.username }}! 👋</h1>
          <p class="greeting-subtitle">System administration, master data maintenance, and user access management.</p>
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

      <!-- User Operational Dashboard Hero -->
      <div v-else class="dashboard-hero">
        <div class="greeting-box">
          <h1 class="greeting-title">Welcome back, {{ activeUser?.username || 'User' }}! 👋</h1>
          <p class="greeting-subtitle">Welcome back to 6IS. Please select an operational module to get started.</p>
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

      <!-- MODULE LAUNCHER CARDS GRID -->

      <!-- Administrator Management Cards Grid -->
      <div v-if="activeUser?.role === 'Administrator'" class="module-cards-grid admin-cards-grid">
        
        <!-- Inventory Management Card -->
        <ion-card class="module-card inventory-card" @click="goToAdminInventory">
          <ion-card-header>
            <div class="module-icon-box inventory-icon">
              <ion-icon :icon="cubeOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Inventory Management</ion-card-title>
            <p class="module-desc">Manage equipment records, JRRS targets, and master inventory data.</p>
          </ion-card-header>
        </ion-card>

        <!-- Communications Management Card -->
        <ion-card class="module-card communications-card" @click="goToAdminCommunications">
          <ion-card-header>
            <div class="module-icon-box communications-icon">
              <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Communications Management</ion-card-title>
            <p class="module-desc">Maintain communications records, categories, purposes, and master data.</p>
          </ion-card-header>
        </ion-card>

        <!-- Accomplishments Management Card -->
        <ion-card class="module-card accomplishments-card" @click="goToAdminAccomplishments">
          <ion-card-header>
            <div class="module-icon-box accomplishments-icon">
              <ion-icon :icon="clipboardOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Accomplishments Management</ion-card-title>
            <p class="module-desc">Maintain accomplishment entries, category options, and reporting master data.</p>
          </ion-card-header>
        </ion-card>

        <!-- User Management Card -->
        <ion-card class="module-card admin-card" @click="goToAdminUsers">
          <ion-card-header>
            <div class="module-icon-box admin-icon">
              <ion-icon :icon="peopleOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">User Management</ion-card-title>
            <p class="module-desc">Manage user accounts, system roles, access permissions, and activation states.</p>
          </ion-card-header>
        </ion-card>

      </div>

      <!-- Standard User Operational Cards Grid -->
      <div v-else class="module-cards-grid">

        <!-- Inventory Operational Card -->
        <ion-card class="module-card inventory-card" @click="goToInventory">
          <ion-card-header>
            <div class="module-icon-box inventory-icon">
              <ion-icon :icon="cubeOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Inventory</ion-card-title>
            <p class="module-desc">View equipment readiness, maintenance condition, and inventory reports.</p>
          </ion-card-header>
        </ion-card>

        <!-- Communications Operational Card -->
        <ion-card class="module-card communications-card" @click="goToCommunications">
          <ion-card-header>
            <div class="module-icon-box communications-icon">
              <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Communications</ion-card-title>
            <p class="module-desc">View messages, announcements, and incoming/outgoing communications.</p>
          </ion-card-header>
        </ion-card>

        <!-- Accomplishments Operational Card -->
        <ion-card class="module-card accomplishments-card" @click="goToAccomplishments">
          <ion-card-header>
            <div class="module-icon-box accomplishments-icon">
              <ion-icon :icon="clipboardOutline"></ion-icon>
            </div>
            <ion-card-title class="module-title">Accomplishments</ion-card-title>
            <p class="module-desc">Record daily accomplishments and generate consolidated reports.</p>
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
  shieldCheckmarkOutline,
  peopleOutline
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

// Operational Routes (Normal User)
function goToInventory() {
  router.push('/inventory')
}

function goToCommunications() {
  router.push('/communications')
}

function goToAccomplishments() {
  router.push('/accomplishments')
}

// Administrative Management Routes (Administrator Only)
function goToAdminInventory() {
  router.push('/administrator/inventory')
}

function goToAdminCommunications() {
  router.push('/administrator/communications')
}

function goToAdminAccomplishments() {
  router.push('/administrator/accomplishments')
}

function goToAdminUsers() {
  router.push('/administrator/users')
}
</script>

<style scoped>
.role-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 8px;
}

.admin-pill {
  background: #f3e8ff;
  color: #7e22ce;
  border: 1px solid #e9d5ff;
}

.admin-cards-grid {
  grid-template-columns: repeat(2, 1fr);
}

@media (max-width: 768px) {
  .admin-cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>