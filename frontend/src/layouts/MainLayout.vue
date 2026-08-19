<template>
  <div class="main-layout">
    <AppHeader />

    <AppBreadcrumb />

    <div class="app-body">
      <ModuleSidebar />

      <main class="app-content">
        <transition name="page-fade" mode="out-in">
          <div :key="route.path">
            <slot />
          </div>
        </transition>
      </main>
    </div>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router'
import AppHeader from './AppHeader.vue'
import AppBreadcrumb from './AppBreadcrumb.vue'
import AppFooter from './AppFooter.vue'

import ModuleSidebar from '../components/ModuleSidebar.vue'

const route = useRoute()
</script>