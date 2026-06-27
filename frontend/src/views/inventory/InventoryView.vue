<template>

  <MainLayout
    title="Inventory"
    username="Admin"
  >

    <div class="dashboard-container">

      <div class="dashboard-cards">

        <ion-card
          class="dashboard-card"
          @click="goToEquipment"
        >
          <ion-card-header>
            <ion-card-title>
              ICT Equipment
            </ion-card-title>
          </ion-card-header>
        </ion-card>

        <ion-card
          class="dashboard-card"
          @click="goToJRRS"
        >
          <ion-card-header>
            <ion-card-title>
              JRRS
            </ion-card-title>
          </ion-card-header>
        </ion-card>

      </div>

    </div>

  </MainLayout>

</template>

<script setup lang="ts">

import { useRouter } from 'vue-router'

import MainLayout from '../../layouts/MainLayout.vue'

import {
  IonCard,
  IonCardHeader,
  IonCardTitle
} from '@ionic/vue'

const router = useRouter()

function goToEquipment() {
  router.push('/inventory/equipment')
}

function goToJRRS() {
  router.push('/inventory/jrrs')
}

</script>