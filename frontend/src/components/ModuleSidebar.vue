<!--
ModuleSidebar
Reusable sidebar navigation for application modules.
-->

<template>
        <ion-list>

            <ion-item
                v-for="item in items"
                :key="item.route"
                button
                @click="goTo(item.route)"
            >
                {{ item.label }}
            </ion-item>

        </ion-list>
</template>

<script setup lang="ts">
import { useRouter } from "vue-router"

import {
    IonList,
    IonItem
} from "@ionic/vue"

import '../assets/styles/components/sidebar.css'

import { computed } from 'vue'
import { useRoute } from 'vue-router'

//import { moduleMenus } from '@/menus'
import { ModuleName } from '@/types/module'

const route = useRoute()

const items = computed(() => {
  const module = route.meta.module as ModuleName

  return moduleMenus[module] ?? []
})

const router = useRouter()

function goTo(route: string) {
    router.push(route)
}
</script>