<template>
  <MainLayout title="Administrator Overview">
    <div class="admin-view-container">
      <div class="header-bar">
        <div>
          <h2>System Administration Portal</h2>
          <p class="subtitle">Central management hub for master data, application registries, and user access control.</p>
        </div>
      </div>

      <!-- Quick Management Launchers -->
      <div class="admin-grid">
        <div class="admin-card" @click="goTo('/administrator/inventory')">
          <div class="card-icon blue-bg"><ion-icon :icon="cubeOutline" /></div>
          <div class="card-info">
            <h3>Inventory Management</h3>
            <p>Maintain ICT equipment registry, JRRS target quantities, and office assignments.</p>
          </div>
        </div>

        <div class="admin-card" @click="goTo('/administrator/communications')">
          <div class="card-icon green-bg"><ion-icon :icon="chatbubbleEllipsesOutline" /></div>
          <div class="card-info">
            <h3>Communications Management</h3>
            <p>Maintain incoming/outgoing communications records, categories, and purposes.</p>
          </div>
        </div>

        <div class="admin-card" @click="goTo('/administrator/accomplishments')">
          <div class="card-icon orange-bg"><ion-icon :icon="clipboardOutline" /></div>
          <div class="card-info">
            <h3>Accomplishments Management</h3>
            <p>Maintain accomplishment records, categories, and reporting options.</p>
          </div>
        </div>

        <div class="admin-card" @click="goTo('/administrator/users')">
          <div class="card-icon purple-bg"><ion-icon :icon="peopleOutline" /></div>
          <div class="card-info">
            <h3>User Management</h3>
            <p>Manage user accounts, roles (Admin/User), active states, and passwords.</p>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  cubeOutline,
  chatbubbleEllipsesOutline,
  clipboardOutline,
  peopleOutline
} from 'ionicons/icons'
import MainLayout from '../../layouts/MainLayout.vue'

const router = useRouter()

function goTo(path: string) {
  router.push(path)
}
</script>

<style scoped>
.admin-view-container {
  padding: 32px 40px;
  max-width: 1280px;
  margin: 0 auto;
}

.header-bar {
  margin-bottom: 28px;
}

.header-bar h2 {
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

.admin-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

@media (max-width: 768px) {
  .admin-grid {
    grid-template-columns: 1fr;
  }
}

.admin-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  padding: 24px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.admin-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
  border-color: #cbd5e1;
}

.card-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  flex-shrink: 0;
}

.blue-bg { background: #eff6ff; color: #2563eb; }
.green-bg { background: #f0fdf4; color: #16a34a; }
.orange-bg { background: #fff7ed; color: #ea580c; }
.purple-bg { background: #faf5ff; color: #9333ea; }

.card-info h3 {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 6px 0;
}

.card-info p {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}
</style>
