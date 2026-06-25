<template>
  <ion-page>

    <ion-header>
      <ion-toolbar>
        <ion-title>Dashboard</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content>

      <div class="dashboard-container">

        <div class="dashboard-cards">

          <ion-card class="dashboard-card">
            <ion-card-header>
              <ion-card-title>
                Communications
              </ion-card-title>
            </ion-card-header>
          </ion-card>

          <ion-card class="dashboard-card">
            <ion-card-header>
              <ion-card-title>
                Inventory
              </ion-card-title>
            </ion-card-header>
          </ion-card>

        </div>

      </div>

    </ion-content>

  </ion-page>
</template>

<script setup lang="ts">
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonCard,
  IonCardHeader,
  IonCardTitle
} from '@ionic/vue'
</script>