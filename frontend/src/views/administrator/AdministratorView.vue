<template>
  <MainLayout title="System Administration">
    <div class="admin-view-container">
      
      <!-- System Administration Header -->
      <div class="header-bar">
        <div>
          <h2>System Administration</h2>
          <p class="subtitle">
            Welcome back, {{ activeUser?.username || 'Administrator' }}. Here's the current state of 6IS.
          </p>
        </div>
      </div>

      <!-- KPI Summary Cards Section -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon-box purple-icon">
            <ion-icon :icon="peopleOutline" />
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Total Users</span>
            <span class="kpi-value">{{ loadingKpi ? '...' : kpis.totalUsers }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box green-icon">
            <ion-icon :icon="checkmarkCircleOutline" />
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Active Users</span>
            <span class="kpi-value">{{ loadingKpi ? '...' : kpis.activeUsers }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box blue-icon">
            <ion-icon :icon="cubeOutline" />
          </div>
          <div class="kpi-details">
            <span class="kpi-label">ICT Equipment</span>
            <span class="kpi-value">{{ loadingKpi ? '...' : kpis.totalEquipment }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box teal-icon">
            <ion-icon :icon="chatbubbleEllipsesOutline" />
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Communications This Month</span>
            <span class="kpi-value">{{ loadingKpi ? '...' : kpis.commsThisMonth }}</span>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-box orange-icon">
            <ion-icon :icon="clipboardOutline" />
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Accomplishments This Month</span>
            <span class="kpi-value">{{ loadingKpi ? '...' : kpis.accomplishmentsThisMonth }}</span>
          </div>
        </div>
      </div>

      <!-- Management Launcher Cards Section -->
      <div class="section-title">
        <h3>Administrative Management Areas</h3>
      </div>

      <div class="admin-grid">
        <!-- Inventory Management -->
        <div class="admin-card" @click="goTo('/administrator/inventory')">
          <div class="card-icon blue-bg"><ion-icon :icon="cubeOutline" /></div>
          <div class="card-info">
            <h3>Inventory Management</h3>
            <p>Manage ICT equipment, JRRS targets, offices, and inventory records.</p>
          </div>
        </div>

        <!-- Communications Management -->
        <div class="admin-card" @click="goTo('/administrator/communications')">
          <div class="card-icon green-bg"><ion-icon :icon="chatbubbleEllipsesOutline" /></div>
          <div class="card-info">
            <h3>Communications Management</h3>
            <p>Manage communications records, categories, purposes, and related reference data.</p>
          </div>
        </div>

        <!-- Accomplishments Management -->
        <div class="admin-card" @click="goTo('/administrator/accomplishments')">
          <div class="card-icon orange-bg"><ion-icon :icon="clipboardOutline" /></div>
          <div class="card-info">
            <h3>Accomplishments Management</h3>
            <p>Manage accomplishment records and accomplishment categories.</p>
          </div>
        </div>

        <!-- Organization Management -->
        <div v-if="hasPermission('organization', 'view')" class="admin-card" @click="goTo('/administrator/organization')">
          <div class="card-icon teal-bg"><ion-icon :icon="businessOutline" /></div>
          <div class="card-info">
            <h3>Organization Profile</h3>
            <p>Manage enterprise organization identity, headquarters location, and official contact channels.</p>
          </div>
        </div>

        <!-- Offices Management -->
        <div v-if="hasPermission('offices', 'view')" class="admin-card" @click="goTo('/administrator/offices')">
          <div class="card-icon cyan-bg"><ion-icon :icon="businessOutline" /></div>
          <div class="card-info">
            <h3>Offices Management</h3>
            <p>Manage organizational offices, unit identifiers, location addresses, and user associations.</p>
          </div>
        </div>

        <!-- User Management -->
        <div class="admin-card" @click="goTo('/administrator/users')">
          <div class="card-icon purple-bg"><ion-icon :icon="peopleOutline" /></div>
          <div class="card-info">
            <h3>User Management</h3>
            <p>Manage user accounts, roles, active status, and passwords.</p>
          </div>
        </div>

        <!-- Module Management -->
        <div class="admin-card" @click="goTo('/administrator/modules')">
          <div class="card-icon indigo-bg"><ion-icon :icon="gridOutline" /></div>
          <div class="card-info">
            <h3>Module Management</h3>
            <p>Manage system modules, activations, core platform protections, and feature availability.</p>
          </div>
        </div>

        <!-- Role & Permission Management -->
        <div class="admin-card" @click="goTo('/administrator/roles')">
          <div class="card-icon navy-bg"><ion-icon :icon="shieldCheckmarkOutline" /></div>
          <div class="card-info">
            <h3>Role & Permission Management</h3>
            <p>Manage system and custom roles, granular permissions, and module authorization matrices.</p>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import {
  cubeOutline,
  chatbubbleEllipsesOutline,
  clipboardOutline,
  peopleOutline,
  checkmarkCircleOutline,
  gridOutline,
  shieldCheckmarkOutline,
  businessOutline
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import { activeUser } from '../../services/authService'
import { fetchUsers } from '../../services/userService'
import { usePermissions } from '../../composables/usePermissions'

const router = useRouter()
const { hasPermission } = usePermissions()

const loadingKpi = ref(true)
const kpis = ref({
  totalUsers: 0,
  activeUsers: 0,
  totalEquipment: 0,
  commsThisMonth: 0,
  accomplishmentsThisMonth: 0
})

function resolveHostUrl(path: string): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost'
    const protocol = window.location.protocol || 'http:'
    return `${protocol}//${host}/6IS/backend/api/${path}`
  }
  return `http://localhost/6IS/backend/api/${path}`
}

async function loadKpis() {
  loadingKpi.value = true
  try {
    // 1. Fetch Users Metrics
    const userRes = await fetchUsers()
    if (userRes.success && userRes.data) {
      kpis.value.totalUsers = userRes.data.length
      kpis.value.activeUsers = userRes.data.filter(u => u.is_active === 1).length
    }

    // 2. Fetch Equipment Metrics
    const currentYm = new Date().toISOString().slice(0, 7)
    const eqRes = await fetch(resolveHostUrl(`inventory/index.php?view=overview&period=${currentYm}`), { credentials: 'include' })
    const eqData = await eqRes.json()
    if (eqData.success && eqData.data) {
      kpis.value.totalEquipment = eqData.data.total_equipment || 0
    }

    // 3. Fetch Communications Metrics
    const commRes = await fetch(resolveHostUrl('communications/index.php?view=communications'), { credentials: 'include' })
    const commData = await commRes.json()
    if (commData.success && Array.isArray(commData.data)) {
      const currentMonthStr = new Date().toISOString().slice(0, 7)
      kpis.value.commsThisMonth = commData.data.filter((c: any) =>
        c.communication_date && c.communication_date.startsWith(currentMonthStr)
      ).length
    }

    // 4. Fetch Accomplishments Metrics
    const accRes = await fetch(resolveHostUrl('accomplishments/index.php?view=overview'), { credentials: 'include' })
    const accData = await accRes.json()
    if (accData.success && accData.data?.counts) {
      kpis.value.accomplishmentsThisMonth = accData.data.counts.monthly || 0
    }
  } catch (err) {
    console.debug('[admin] Failed to load accomplishment KPI:', err)
  }
  loadingKpi.value = false
}

function goTo(path: string) {
  router.push(path)
}

onMounted(() => {
  loadKpis()
})
</script>

<style scoped>
.admin-view-container {
  padding: 32px 40px;
  max-width: 1280px;
  margin: 0 auto;
}

.header-bar {
  margin-bottom: 24px;
}

.header-bar h2 {
  font-size: 26px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

/* KPI Summary Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
  margin-bottom: 32px;
}

@media (max-width: 1024px) {
  .kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .kpi-grid {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.kpi-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.purple-icon { background: #faf5ff; color: #9333ea; }
.green-icon { background: #f0fdf4; color: #16a34a; }
.blue-icon { background: #eff6ff; color: #2563eb; }
.teal-icon { background: #f0fdfa; color: #0d9488; }
.orange-icon { background: #fff7ed; color: #ea580c; }

.kpi-details {
  display: flex;
  flex-direction: column;
}

.kpi-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.kpi-value {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.2;
}

/* Management Cards Grid */
.section-title {
  margin-bottom: 16px;
}

.section-title h3 {
  font-size: 16px;
  font-weight: 700;
  color: #334155;
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
.indigo-bg { background: #eef2ff; color: #4f46e5; }
.navy-bg { background: #eff6ff; color: #1e3a8a; }
.teal-bg { background: #f0fdfa; color: #0d9488; }
.cyan-bg { background: #ecfeff; color: #0891b2; }

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
