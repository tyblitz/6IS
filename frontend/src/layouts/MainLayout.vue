<!-- This is the layout for the dashboard header and footer -->
<template>
  <ion-page class="main-layout">

    <AppHeader />

    <AppBreadcrumb />

    <div class="app-body">

      <AppSidebar />

      <main class="app-content">
        <slot />
      </main>

    </div>

    <AppFooter />

  </ion-page>
</template>

<script setup lang="ts">
import {
  IonPage,
} from '@ionic/vue'

import AppHeader from '../layouts/AppHeader.vue';

defineProps<{
  title: string
  username?: string
}>()
</script>