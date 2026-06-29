<template>
  <ion-page class="main-layout">
    <AppHeader />

    <AppBreadcrumb />

    <div class="app-body">
      <ModuleSidebar />

      <main class="app-content">
        <slot />
      </main>
    </div>

    <AppFooter />
  </ion-page>
</template>

<script setup lang="ts">
import AppHeader from './AppHeader.vue'
import AppBreadcrumb from './AppBreadcrumb.vue'
import AppFooter from './AppFooter.vue'

import ModuleSidebar from '../components/ModuleSidebar.vue'
</script>