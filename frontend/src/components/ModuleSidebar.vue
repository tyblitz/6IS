<!--
ModuleSidebar
Reusable sidebar navigation for application modules.
-->

<template>
        <div class="app-sidebar">

            <router-link
              v-for="item in menuItems"
              :key="item.route"
              :to="item.route"
              class="sidebar-item"
            >
            <ion-icon
                v-if="item.icon"
                :icon="item.icon"
                class="sidebar-icon"
            />

            <span>{{ item.label }}</span>
            </router-link>

        </div>
</template>

<script setup lang="ts">

import { computed } from 'vue'
import { useRoute } from 'vue-router'

import {
  IonList,
  IonIcon
} from '@ionic/vue'

import '../assets/styles/components/sidebar.css'

import { moduleMenus } from '../menus'
import { ModuleName } from '../types/module'

const route = useRoute()

const menuItems = computed(() => {
  const module = route.meta.module as ModuleName
  return moduleMenus[module] ?? []
})

</script>