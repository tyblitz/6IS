import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import DashboardView  from '../views/DashboardView.vue'
import CommunicationsView from '../views/communications/CommunicationsView.vue'
import IncomingCommunicationsView from '../views/communications/IncomingCommunicationsView.vue'
import OutgoingCommunicationsView from '../views/communications/OutgoingCommunicationsView.vue'
import CommunicationReportsView from '../views/communications/CommunicationReportsView.vue'
import InventoryView from '../views/inventory/InventoryView.vue'
import EquipmentView from '../views/inventory/EquipmentView.vue'
import JRRSView from '../views/inventory/JRRS.vue'
import AccomplishmentView from '../views/accomplishments/AccomplishmentView.vue'
import AccomplishmentDailyView from '../views/accomplishments/AccomplishmentDailyView.vue'
import AccomplishmentMonthlyView from '../views/accomplishments/AccomplishmentMonthlyView.vue'
import AccomplishmentQuarterlyView from '../views/accomplishments/AccomplishmentQuarterlyView.vue'
import AccomplishmentAnnualView from '../views/accomplishments/AccomplishmentAnnualView.vue'
import AccomplishmentCustomView from '../views/accomplishments/AccomplishmentCustomView.vue'
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
    path: '/communications/incoming',
    name: 'Incoming Communications',
    component: IncomingCommunicationsView,
    meta: {
      module: ModuleName.Communications,
    },
  },
  {
    path: '/communications/outgoing',
    name: 'Outgoing Communications',
    component: OutgoingCommunicationsView,
    meta: {
      module: ModuleName.Communications,
    },
  },
  {
    path: '/communications/reports',
    name: 'Communication Reports',
    component: CommunicationReportsView,
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
    name: 'Daily Report',
    component: AccomplishmentDailyView,
    meta: {
      module: ModuleName.Accomplishments,
    },
  },
  {
    path: '/accomplishments/monthly',
    name: 'Monthly Report',
    component: AccomplishmentMonthlyView,
    meta: {
      module: ModuleName.Accomplishments,
    },
  },
  {
    path: '/accomplishments/quarterly',
    name: 'Quarterly Report',
    component: AccomplishmentQuarterlyView,
    meta: {
      module: ModuleName.Accomplishments,
    },
  },
  {
    path: '/accomplishments/annual',
    name: 'Annual Report',
    component: AccomplishmentAnnualView,
    meta: {
      module: ModuleName.Accomplishments,
    },
  },
  {
    path: '/accomplishments/custom',
    name: 'Custom Period Report',
    component: AccomplishmentCustomView,
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
