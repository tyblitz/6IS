<template>
  <header class="app-header">

    <!-- Left Section -->
    <div class="header-left">

      <button class="menu-button" type="button">
        <ion-icon :icon="menuOutline"></ion-icon>
      </button>

    
      <img
        src="../assets/logo.png"
        alt="6IS Logo"
        class="header-logo"
      />
    

      <div class="header-title">
        <h1>6IS</h1>
        <p>Integrated Information System</p>
      </div>

    </div>

    <!-- Right Section -->
    <div class="header-right">

      <button class="header-icon-button" type="button">
        <ion-icon :icon="notificationsOutline"></ion-icon>
      </button>

      <button class="user-button" type="button">
        <ion-icon :icon="personCircleOutline"></ion-icon>
        <span>Administrator</span>
      </button>

      <button class="logout-button" type="button">
        <ion-icon :icon="logOutOutline"></ion-icon>
      </button>

    </div>

  </header>
</template>

<script setup lang="ts">
import {
  menuOutline,
  notificationsOutline,
  personCircleOutline,
  logOutOutline
} from 'ionicons/icons';

import '../assets/styles/layouts/header.css'

</script>