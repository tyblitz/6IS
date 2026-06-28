<template>
  <AppLayout
    title="Inventory"
    username="Admin"
    :sidebar-items="inventoryMenu"
>
    <h2>Inventory Module</h2>

    <p>
      This is where the Inventory module content will go.
    </p>
  </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue'
import { inventoryMenu } from '../../menus/inventoryMenu'
</script>