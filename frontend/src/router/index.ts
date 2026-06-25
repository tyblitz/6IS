import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import DashboardView  from '../views/DashboardView.vue'
import CommunicationsView from '../views/communications/CommunicationsView.vue'
import InventoryView from '../views/inventory/InventoryView.vue'

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
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})



export default router
