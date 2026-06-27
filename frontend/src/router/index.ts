import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import DashboardView  from '../views/DashboardView.vue'
import CommunicationsView from '../views/communications/CommunicationsView.vue'
import InventoryView from '../views/inventory/InventoryView.vue'
import EquipmentView from '../views/inventory/EquipmentView.vue'
import JRRSView from '../views/inventory/JRRS.vue'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/home'
  },
  {
    path: '/home',
    name: 'Home',
    component: DashboardView
  }
  ,
  {
    path: '/communications',
    name: 'Communications',
    component: CommunicationsView
  }
  ,
  {
    path: '/inventory',
    name: 'Inventory',
    component: InventoryView
  }
  ,
  {
    path: '/inventory/equipment',
    name: 'Equipment',
    component: EquipmentView
  }
  ,
  {
    path: '/inventory/jrrs',
    name: 'JRRS',
    component: JRRSView
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})



export default router
