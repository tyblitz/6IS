<template>
  <div class="accomplishment-summary-grid">
    
    <!-- Today's Accomplishments Card -->
    <div class="summary-card today-card" @click="navigateTo('/accomplishments/daily')">
      <div class="card-top">
        <div class="icon-box today-icon">
          <ion-icon :icon="todayOutline"></ion-icon>
        </div>
        <span class="count-badge">{{ counts?.today ?? 0 }}</span>
      </div>
      <div class="card-bottom">
        <h4>Today's Accomplishments</h4>
        <span class="nav-hint">View Daily Report &rarr;</span>
      </div>
    </div>

    <!-- Monthly Accomplishments Card -->
    <div class="summary-card monthly-card" @click="navigateTo('/accomplishments/monthly')">
      <div class="card-top">
        <div class="icon-box monthly-icon">
          <ion-icon :icon="calendarOutline"></ion-icon>
        </div>
        <span class="count-badge">{{ counts?.monthly ?? 0 }}</span>
      </div>
      <div class="card-bottom">
        <h4>Monthly Accomplishments</h4>
        <span class="nav-hint">View Monthly Report &rarr;</span>
      </div>
    </div>

    <!-- Quarterly Accomplishments Card -->
    <div class="summary-card quarterly-card" @click="navigateTo('/accomplishments/quarterly')">
      <div class="card-top">
        <div class="icon-box quarterly-icon">
          <ion-icon :icon="pieChartOutline"></ion-icon>
        </div>
        <span class="count-badge">{{ counts?.quarterly ?? 0 }}</span>
      </div>
      <div class="card-bottom">
        <h4>Quarterly Accomplishments</h4>
        <span class="nav-hint">View Quarterly Report &rarr;</span>
      </div>
    </div>

    <!-- Annual Accomplishments Card -->
    <div class="summary-card annual-card" @click="navigateTo('/accomplishments/annual')">
      <div class="card-top">
        <div class="icon-box annual-icon">
          <ion-icon :icon="ribbonOutline"></ion-icon>
        </div>
        <span class="count-badge">{{ counts?.annual ?? 0 }}</span>
      </div>
      <div class="card-bottom">
        <h4>Annual Accomplishments</h4>
        <span class="nav-hint">View Annual Report &rarr;</span>
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
  pieChartOutline,
  ribbonOutline
} from 'ionicons/icons'
import type { OverviewCounts } from '../../types/accomplishment'

defineProps<{
  counts?: OverviewCounts;
}>()

const router = useRouter()

function navigateTo(route: string) {
  router.push(route)
}
</script>

<style scoped>
.accomplishment-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 28px;
}

@media (max-width: 1024px) {
  .accomplishment-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .accomplishment-summary-grid {
    grid-template-columns: 1fr;
  }
}

.summary-card {
  background: #ffffff;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 16px;
  position: relative;
  overflow: hidden;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
}

.summary-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.today-card::before { background: #2563eb; }
.monthly-card::before { background: #10b981; }
.quarterly-card::before { background: #8b5cf6; }
.annual-card::before { background: #f59e0b; }

.summary-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08);
  border-color: #cbd5e1;
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.today-icon { background: #eff6ff; color: #2563eb; }
.monthly-icon { background: #ecfdf5; color: #10b981; }
.quarterly-icon { background: #f5f3ff; color: #8b5cf6; }
.annual-icon { background: #fffbeb; color: #f59e0b; }

.count-badge {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
}

.card-bottom h4 {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px 0;
}

.nav-hint {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  transition: color 0.2s ease;
}

.summary-card:hover .nav-hint {
  color: #2563eb;
}
</style>
