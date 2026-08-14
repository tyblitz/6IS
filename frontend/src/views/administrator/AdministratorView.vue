<template>
  <MainLayout title="Administrator Module">
    <div class="view-container">
      <div class="module-header-bar">
        <div>
          <h2>Administrator Module</h2>
          <p class="subtitle">System administration and configuration tools.</p>
        </div>
      </div>

      <div class="placeholder-card">
        <div class="card-icon-box">
          <ion-icon :icon="shieldCheckmarkOutline" />
        </div>
        <h3>Administrator Module</h3>
        <p>Administrative tools under development.</p>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { IonIcon } from '@ionic/vue'
import { shieldCheckmarkOutline } from 'ionicons/icons'
import MainLayout from '../../layouts/MainLayout.vue'
</script>

<style scoped>
.view-container {
  padding: 24px;
  max-width: 1280px;
  margin: 0 auto;
}

.module-header-bar {
  margin-bottom: 24px;
}

.module-header-bar h2 {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.placeholder-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 64px 32px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.card-icon-box {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  margin: 0 auto 16px auto;
}

.placeholder-card h3 {
  font-size: 20px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px 0;
}

.placeholder-card p {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}
</style>
