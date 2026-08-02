import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import DashboardView  from '../views/DashboardView.vue'
import CommunicationsView from '../views/communications/CommunicationsView.vue'
import InventoryView from '../views/inventory/InventoryView.vue'
import EquipmentView from '../views/inventory/EquipmentView.vue'
import JRRSView from '../views/inventory/JRRS.vue'
import AccomplishmentView from '../views/accomplishments/AccomplishmentView.vue'
import AccomplishmentDailyView from '../views/accomplishments/AccomplishmentDailyView.vue'
import { ModuleName } from '../types/module'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/home'
  },
  {
    path: '/home',
    name: 'Home',
    component: DashboardView,
    meta: {
      module: ModuleName.Dashboard,
    },
  },
  {
    path: '/communications',
    name: 'Communications',
    component: CommunicationsView,
    meta: {
      module: ModuleName.Communications,
    },
  },
  {
    path: '/inventory',
    name: 'Inventory',
    component: InventoryView,
    meta: {
      module: ModuleName.Inventory,
    },
  },
  {
    path: '/inventory/equipment',
    name: 'Equipment',
    component: EquipmentView,
    meta: {
      module: ModuleName.Equipment,
    },
  },
  {
    path: '/inventory/jrrs',
    name: 'JRRS',
    component: JRRSView,
    meta: {
      module: ModuleName.JRRS,
    },
  },
  {
    path: '/accomplishments',
    name: 'Accomplishments',
    component: AccomplishmentView,
    meta: {
      module: ModuleName.Accomplishments,
    },
  },
  {
    path: '/accomplishments/daily',
    name: 'Daily Accomplishments',
    component: AccomplishmentDailyView,
    meta: {
      module: ModuleName.Accomplishments,
    },
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

export default router
