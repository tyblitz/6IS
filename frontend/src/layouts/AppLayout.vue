<!--
AppLayout
Shared layout used by all application modules.
Provides a consistent header, breadcrumb area, sidebar, content area, and footer.
-->

<template>
  <ion-page>
    <!-- Header -->
    <ion-header>
      <ion-toolbar>
        <div class="app-header">
          <ion-title>{{ title }}</ion-title>

          <div class="user-area">
            <span>{{ username }}</span>

            <ion-button fill="clear" size="small">
              Logout
            </ion-button>
          </div>
        </div>
      </ion-toolbar>
    </ion-header>

    <!-- Main Content -->
    <ion-content>
      <div class="app-layout">

        <!-- Breadcrumb Placeholder -->
        <div class="breadcrumb-placeholder">
          Dashboard > {{ title }}
        </div>

        <div class="app-body">

          <!-- Sidebar  -->
        <aside class="app-sidebar">
            <ModuleSidebar :items="sidebarItems" />
        </aside>

          <!-- Main Content -->
          <main class="app-content">
            <slot />
          </main>

        </div>

      </div>
    </ion-content>

    <!-- Footer -->
    <ion-footer>
      <ion-toolbar>
        <ion-title size="small">
          6IS v0.2.0
        </ion-title>
      </ion-toolbar>
    </ion-footer>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonFooter,
  IonButton
} from '@ionic/vue'

import ModuleSidebar from "../components/ModuleSidebar.vue"
import type { SidebarItem } from "../types/SidebarItem.js"

const props = defineProps<{
    title: string
    username?: string
    sidebarItems: SidebarItem[]
}>()
</script>