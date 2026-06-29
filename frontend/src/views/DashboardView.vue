<template>

  <MainLayout
    title="Dashboard"
    username="Admin"
  >

    <div class="dashboard-container">

      <div class="dashboard-cards">

        <ion-card class="dashboard-card" @click="goToCommunications">
          <ion-card-header>
            <ion-card-title>
              Communications
            </ion-card-title>
          </ion-card-header>
        </ion-card>

        <ion-card class="dashboard-card" @click="goToInventory">
          <ion-card-header>
            <ion-card-title>
              Inventory
            </ion-card-title>
          </ion-card-header>
        </ion-card>

      </div>

    </div>

  </MainLayout>

</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

import '../assets/styles/pages/dashboard.css'
import MainLayout from '../layouts/MainLayout.vue'

import {
  IonCard,
  IonCardHeader,
  IonCardTitle
} from '@ionic/vue'

const router = useRouter()

function goToCommunications() {
  router.push('/communications')
}

function goToInventory() {
  router.push('/inventory')
}
</script>