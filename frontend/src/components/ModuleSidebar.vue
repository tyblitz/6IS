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
import type { SidebarItem } from "../types/SidebarItem"

defineProps<{
    items: SidebarItem[]
}>()

const router = useRouter()

function goTo(route: string) {
    router.push(route)
}
</script>