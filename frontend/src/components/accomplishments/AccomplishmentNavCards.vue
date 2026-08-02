<template>
  <div class="accomplishment-nav-cards">
    
    <ion-card class="nav-card" @click="navigateTo('daily')">
      <ion-card-header>
        <div class="card-icon-wrapper daily-icon">
          <ion-icon :icon="todayOutline"></ion-icon>
        </div>
        <ion-card-title>Daily Accomplishments</ion-card-title>
        <p class="card-description">View, search, and manage daily accomplishment logs.</p>
      </ion-card-header>
    </ion-card>

    <ion-card class="nav-card" @click="navigateTo('monthly')">
      <ion-card-header>
        <div class="card-icon-wrapper monthly-icon">
          <ion-icon :icon="calendarOutline"></ion-icon>
        </div>
        <ion-card-title>Monthly Accomplishments</ion-card-title>
        <p class="card-description">Review monthly task progress and summary performance.</p>
      </ion-card-header>
    </ion-card>

    <ion-card class="nav-card" @click="navigateTo('annual')">
      <ion-card-header>
        <div class="card-icon-wrapper annual-icon">
          <ion-icon :icon="ribbonOutline"></ion-icon>
        </div>
        <ion-card-title>Annual Accomplishments</ion-card-title>
        <p class="card-description">Annual accomplishment reports and office achievements.</p>
      </ion-card-header>
    </ion-card>

  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import {
  IonCard,
  IonCardHeader,
  IonCardTitle,
  IonIcon
} from '@ionic/vue'
import {
  todayOutline,
  calendarOutline,
  ribbonOutline
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
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.nav-card {
  margin: 0;
  cursor: pointer;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  background: var(--ion-color-white, #ffffff);
  border: 1px solid #e5e7eb;
}

.nav-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

.card-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-bottom: 12px;
}

.daily-icon {
  background: #eff6ff;
  color: #2563eb;
}

.monthly-icon {
  background: #f0fdf4;
  color: #16a34a;
}

.annual-icon {
  background: #fff7ed;
  color: #ea580c;
}

ion-card-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
}

.card-description {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
  line-height: 1.4;
}
</style>
