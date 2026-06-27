<!-- This is the layout for the dashboard header and footer -->
<template>
  <ion-page>

    <ion-header>
      <ion-toolbar>

        <div class="app-header">

          <ion-title>
            {{ title }}
          </ion-title>

          <div class="user-area">

            <span>{{ username }}</span>

            <ion-button
              fill="clear"
              size="small"
            >
              Logout
            </ion-button>

          </div>

        </div>

      </ion-toolbar>
    </ion-header>

    <ion-content>
      <slot />
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
  IonButton
} from '@ionic/vue'

defineProps<{
  title: string
  username?: string
}>()
</script>