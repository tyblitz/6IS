<template>
  <div class="accomplishment-nav-cards">
    
    <!-- Daily Card -->
    <div class="nav-card daily-card" @click="navigateTo('daily')">
      <div class="card-left">
        <div class="card-icon-wrapper daily-icon">
          <ion-icon :icon="todayOutline"></ion-icon>
        </div>
        <div class="card-text">
          <h3>Daily Accomplishments</h3>
          <span class="card-sub">Daily Logs & Activity</span>
        </div>
      </div>
      <div class="card-action">
        <span class="action-tag">View Logs</span>
        <ion-icon :icon="chevronForwardOutline" class="action-arrow"></ion-icon>
      </div>
    </div>

    <!-- Monthly Card -->
    <div class="nav-card monthly-card" @click="navigateTo('monthly')">
      <div class="card-left">
        <div class="card-icon-wrapper monthly-icon">
          <ion-icon :icon="calendarOutline"></ion-icon>
        </div>
        <div class="card-text">
          <h3>Monthly Accomplishments</h3>
          <span class="card-sub">Monthly Summaries</span>
        </div>
      </div>
      <div class="card-action">
        <span class="action-tag">View Report</span>
        <ion-icon :icon="chevronForwardOutline" class="action-arrow"></ion-icon>
      </div>
    </div>

    <!-- Annual Card -->
    <div class="nav-card annual-card" @click="navigateTo('annual')">
      <div class="card-left">
        <div class="card-icon-wrapper annual-icon">
          <ion-icon :icon="ribbonOutline"></ion-icon>
        </div>
        <div class="card-text">
          <h3>Annual Accomplishments</h3>
          <span class="card-sub">Annual Achievements</span>
        </div>
      </div>
      <div class="card-action">
        <span class="action-tag">View Report</span>
        <ion-icon :icon="chevronForwardOutline" class="action-arrow"></ion-icon>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  todayOutline,
  calendarOutline,
  ribbonOutline,
  chevronForwardOutline
} from 'ionicons/icons'

const router = useRouter()

function navigateTo(type: 'daily' | 'monthly' | 'annual') {
  if (type === 'daily') {
    router.push('/accomplishments/daily')
  } else {
    router.push(`/accomplishments/daily?view=${type}`)
  }
}
</script>

<style scoped>
.accomplishment-nav-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 28px;
}

.nav-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
}

/* Left colored accent stripe */
.nav-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  transition: opacity 0.2s ease;
}

.daily-card::before { background: #2563eb; }
.monthly-card::before { background: #10b981; }
.annual-card::before { background: #f59e0b; }

.nav-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08);
  border-color: #cbd5e1;
}

.card-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.card-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  flex-shrink: 0;
}

.daily-icon {
  background: #eff6ff;
  color: #2563eb;
}

.monthly-icon {
  background: #ecfdf5;
  color: #10b981;
}

.annual-icon {
  background: #fffbeb;
  color: #f59e0b;
}

.card-text h3 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 2px 0;
}

.card-sub {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.card-action {
  display: flex;
  align-items: center;
  gap: 6px;
}

.action-tag {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  transition: color 0.2s ease;
}

.action-arrow {
  font-size: 16px;
  color: #94a3b8;
  transition: color 0.2s ease, transform 0.2s ease;
}

.nav-card:hover .action-tag {
  color: #1e293b;
}

.nav-card:hover .action-arrow {
  color: #2563eb;
  transform: translateX(4px);
}
</style>
