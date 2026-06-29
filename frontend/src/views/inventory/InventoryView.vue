<template>
  <MainLayout 
    title="Inventory"
    username="Admin"
    :sidebar-items="inventoryMenu"
>
    <h2>Inventory Module</h2>

    <p>
      This is where the Inventory module content will go.
    </p>
  </MainLayout >
</template>

<script setup lang="ts">
import MainLayout  from '../../layouts/MainLayout.vue'
import { inventoryMenu } from '../../menus/inventoryMenu'
</script>