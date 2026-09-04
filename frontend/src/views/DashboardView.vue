<!-- frontend/src/views/DashboardView.vue -->
<template>
  <MainLayout title="Home">
    <div class="dashboard-wrapper">

      <!-- WELCOME HERO BANNER -->
      <div class="welcome-hero-card">
        <div class="hero-left">
          <h1 class="welcome-title">Welcome back, {{ activeUser?.username || 'User' }}! 👋</h1>
          <p class="welcome-subtitle">
            Welcome back to 6IS. Select an operational module below to access your workspace.
          </p>
        </div>
        <div class="hero-right">
          <div class="today-date-badge" aria-label="Current Date">
            <div class="today-icon-box" aria-hidden="true">
              <ion-icon :icon="calendarOutline"></ion-icon>
            </div>
            <div class="today-text-group">
              <span class="today-date">{{ currentDateFormatted }}</span>
              <span class="today-day">{{ currentDayOfWeek }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- OPERATIONAL MODULES SECTION -->
      <div class="operational-section">
        <div class="section-header">
          <div class="section-title-group">
            <h2 class="section-title">Operational Modules</h2>
            <p class="section-subtitle">Core information management and operational suites</p>
          </div>
        </div>

        <!-- Operational Cards Grid (Semantic RouterLink Navigation) -->
        <div class="module-cards-grid">

          <!-- Inventory Operational Card -->
          <router-link
            v-if="isEnabled('inventory')"
            to="/inventory"
            class="module-card inventory-card"
            aria-label="Open Inventory module: Manage equipment records, maintenance condition, and operational readiness"
          >
            <div class="module-card-body">
              <div class="module-card-top">
                <div class="module-icon-box inventory-icon" aria-hidden="true">
                  <ion-icon :icon="cubeOutline"></ion-icon>
                </div>
                <span class="module-arrow" aria-hidden="true">
                  <ion-icon :icon="arrowForwardOutline"></ion-icon>
                </span>
              </div>
              <h3 class="module-title">Inventory</h3>
              <p class="module-desc">Manage equipment records, maintenance condition, and operational readiness.</p>
            </div>
          </router-link>

          <!-- Communications Operational Card -->
          <router-link
            v-if="isEnabled('communications')"
            to="/communications"
            class="module-card communications-card"
            aria-label="Open Communications module: Process and track incoming and outgoing official communications and dispatches"
          >
            <div class="module-card-body">
              <div class="module-card-top">
                <div class="module-icon-box communications-icon" aria-hidden="true">
                  <ion-icon :icon="chatbubbleEllipsesOutline"></ion-icon>
                </div>
                <span class="module-arrow" aria-hidden="true">
                  <ion-icon :icon="arrowForwardOutline"></ion-icon>
                </span>
              </div>
              <h3 class="module-title">Communications</h3>
              <p class="module-desc">Process and track incoming and outgoing official communications and dispatches.</p>
            </div>
          </router-link>

          <!-- Accomplishments Operational Card -->
          <router-link
            v-if="isEnabled('accomplishments')"
            to="/accomplishments"
            class="module-card accomplishments-card"
            aria-label="Open Accomplishments module: Log daily activities, mission accomplishments, and generate consolidated reports"
          >
            <div class="module-card-body">
              <div class="module-card-top">
                <div class="module-icon-box accomplishments-icon" aria-hidden="true">
                  <ion-icon :icon="clipboardOutline"></ion-icon>
                </div>
                <span class="module-arrow" aria-hidden="true">
                  <ion-icon :icon="arrowForwardOutline"></ion-icon>
                </span>
              </div>
              <h3 class="module-title">Accomplishments</h3>
              <p class="module-desc">Log daily activities, mission accomplishments, and generate consolidated reports.</p>
            </div>
          </router-link>

          <!-- Calendar Operational Card -->
          <router-link
            v-if="isEnabled('calendar')"
            to="/calendar"
            class="module-card calendar-card"
            aria-label="Open Calendar module: View scheduled activities, manage operational timelines, and track events"
          >
            <div class="module-card-body">
              <div class="module-card-top">
                <div class="module-icon-box calendar-icon" aria-hidden="true">
                  <ion-icon :icon="calendarOutline"></ion-icon>
                </div>
                <span class="module-arrow" aria-hidden="true">
                  <ion-icon :icon="arrowForwardOutline"></ion-icon>
                </span>
              </div>
              <h3 class="module-title">Calendar</h3>
              <p class="module-desc">View scheduled activities, manage operational timelines, and track events.</p>
            </div>
          </router-link>

        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { IonIcon } from '@ionic/vue'
import {
  calendarOutline,
  cubeOutline,
  chatbubbleEllipsesOutline,
  clipboardOutline,
  arrowForwardOutline
} from 'ionicons/icons'

import MainLayout from '../layouts/MainLayout.vue'
import { activeUser } from '../services/authService'
import { useModules } from '../composables/useModules'
import { formatDate } from '../utils/dateUtils'

const { isEnabled, loadModules } = useModules()

const currentDateFormatted = computed(() => {
  return formatDate(new Date().toISOString())
})

const currentDayOfWeek = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long'
  })
})

onMounted(() => {
  loadModules()
})
</script>

<style scoped>
.dashboard-wrapper {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Welcome Hero Banner */
.welcome-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--color-surface, #ffffff);
  padding: 20px 24px;
  border-radius: var(--radius-md, 10px);
  border: 1px solid var(--color-border, #cbd5e1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.hero-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.welcome-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0;
  line-height: 1.3;
}

.welcome-subtitle {
  font-size: 0.875rem;
  color: var(--color-text-secondary, #64748b);
  margin: 0;
  line-height: 1.4;
}

.today-date-badge {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #cbd5e1);
  padding: 10px 16px;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.today-icon-box {
  width: 38px;
  height: 38px;
  border-radius: var(--radius-sm, 6px);
  background: var(--color-info-bg, #eff6ff);
  color: var(--color-primary-light, #2563eb);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.today-text-group {
  display: flex;
  flex-direction: column;
}

.today-date {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text, #0f172a);
  line-height: 1.2;
}

.today-day {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-secondary, #64748b);
  line-height: 1.2;
  margin-top: 2px;
}

/* Operational Section */
.operational-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-title-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.section-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--color-primary-dark, #172554);
  margin: 0;
  letter-spacing: -0.01em;
}

.section-subtitle {
  font-size: 0.8rem;
  color: var(--color-text-secondary, #64748b);
  margin: 0;
}

/* Module Cards Grid */
.module-cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.module-card {
  text-decoration: none;
  color: inherit;
  display: block;
  margin: 0;
  border-radius: var(--radius-md, 10px);
  background: var(--color-surface, #ffffff);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  border: 1px solid var(--color-border, #cbd5e1);
  border-bottom: 3px solid transparent;
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-bottom-color 0.2s ease;
  padding: 0;
  position: relative;
  outline: none;
}

.module-card:focus-visible {
  outline: 2px solid var(--color-primary-light, #2563eb);
  outline-offset: 2px;
}

.module-card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  text-align: left;
  gap: 6px;
}

.module-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  margin-bottom: 8px;
}

.module-icon-box {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md, 10px);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.module-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-surface-hover, #f8fafc);
  color: var(--color-text-secondary, #94a3b8);
  font-size: 14px;
  transition: all 0.2s ease;
}

.inventory-icon {
  background: #eff6ff;
  color: #2563eb;
}

.communications-icon {
  background: #f0fdf4;
  color: #16a34a;
}

.accomplishments-icon {
  background: #fff7ed;
  color: #ea580c;
}

.calendar-icon {
  background: #faf5ff;
  color: #9333ea;
}

.module-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--color-text, #0f172a);
  margin: 0;
}

.module-desc {
  font-size: 0.8125rem;
  color: var(--color-text-secondary, #64748b);
  margin: 0;
  line-height: 1.45;
}

/* Card Hover States */
.module-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}

.module-card:hover .module-arrow {
  color: #ffffff;
  transform: translateX(2px);
}

.inventory-card:hover {
  border-bottom-color: #2563eb;
}
.inventory-card:hover .module-arrow {
  background: #2563eb;
}

.communications-card:hover {
  border-bottom-color: #16a34a;
}
.communications-card:hover .module-arrow {
  background: #16a34a;
}

.accomplishments-card:hover {
  border-bottom-color: #ea580c;
}
.accomplishments-card:hover .module-arrow {
  background: #ea580c;
}

.calendar-card:hover {
  border-bottom-color: #9333ea;
}
.calendar-card:hover .module-arrow {
  background: #9333ea;
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
  .module-cards-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .welcome-hero-card {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .today-date-badge {
    width: 100%;
    box-sizing: border-box;
  }

  .module-cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>