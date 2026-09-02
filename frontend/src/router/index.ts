import { createRouter, createWebHistory } from '@ionic/vue-router'
import { RouteRecordRaw } from 'vue-router'

import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/auth/LoginView.vue'

// Administrator Management Views
import AdministratorView from '../views/administrator/AdministratorView.vue'
import AdminInventoryView from '../views/administrator/AdminInventoryView.vue'
import AdminEquipmentDetailView from '../views/administrator/AdminEquipmentDetailView.vue'
import AdminEquipmentTypesView from '../views/administrator/AdminEquipmentTypesView.vue'
import AdminEquipmentSubtypesView from '../views/administrator/AdminEquipmentSubtypesView.vue'
import AdminEquipmentStatusesView from '../views/administrator/AdminEquipmentStatusesView.vue'
import AdminEquipmentAttributesView from '../views/administrator/AdminEquipmentAttributesView.vue'
import AdminCommunicationsView from '../views/administrator/AdminCommunicationsView.vue'
import AdminCommunicationCategoriesView from '../views/administrator/AdminCommunicationCategoriesView.vue'
import AdminCommunicationPurposesView from '../views/administrator/AdminCommunicationPurposesView.vue'
import AdminCommunicationStatusesView from '../views/administrator/AdminCommunicationStatusesView.vue'
import AdminAccomplishmentsView from '../views/administrator/AdminAccomplishmentsView.vue'
import AdminAccomplishmentCategoriesView from '../views/administrator/AdminAccomplishmentCategoriesView.vue'
import AdminUsersView from '../views/administrator/AdminUsersView.vue'
import AdminModulesView from '../views/administrator/AdminModulesView.vue'

// Operational Views
import CommunicationsView from '../views/communications/CommunicationsView.vue'
import IncomingCommunicationsView from '../views/communications/IncomingCommunicationsView.vue'
import OutgoingCommunicationsView from '../views/communications/OutgoingCommunicationsView.vue'
import CommunicationReportsView from '../views/communications/CommunicationReportsView.vue'
import CommunicationDetailView from '../views/communications/CommunicationDetailView.vue'
import CommunicationEditView from '../views/communications/CommunicationEditView.vue'
import InventoryView from '../views/inventory/InventoryView.vue'
import EquipmentView from '../views/inventory/EquipmentView.vue'
import EquipmentDetailView from '../views/inventory/EquipmentDetailView.vue'
import JRRSView from '../views/inventory/JRRS.vue'
import AccomplishmentView from '../views/accomplishments/AccomplishmentView.vue'
import AccomplishmentDailyView from '../views/accomplishments/AccomplishmentDailyView.vue'
import AccomplishmentDetailView from '../views/accomplishments/AccomplishmentDetailView.vue'
import AccomplishmentMonthlyView from '../views/accomplishments/AccomplishmentMonthlyView.vue'
import AccomplishmentQuarterlyView from '../views/accomplishments/AccomplishmentQuarterlyView.vue'
import AccomplishmentAnnualView from '../views/accomplishments/AccomplishmentAnnualView.vue'
import AccomplishmentCustomView from '../views/accomplishments/AccomplishmentCustomView.vue'

// Calendar Module Views
import CalendarView from '../views/calendar/CalendarView.vue'

import { ModuleName } from '../types/module'
import { fetchCurrentUser } from '../services/authService'
import { useModules } from '../composables/useModules'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/home'
  },
  {
    path: '/login',
    name: 'Login',
    component: LoginView,
    meta: {
      requiresAuth: false
    }
  },
  {
    path: '/home',
    name: 'Home',
    component: DashboardView,
    meta: {
      module: ModuleName.Dashboard,
      requiresAuth: true
    }
  },

  // ADMINISTRATOR MANAGEMENT ROUTES (Requires Administrator Role)
  {
    path: '/administrator',
    name: 'Administrator Overview',
    component: AdministratorView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/inventory',
    name: 'Inventory Management',
    component: AdminInventoryView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/inventory/equipment/:id',
    name: 'Equipment Detail',
    component: AdminEquipmentDetailView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/inventory/types',
    name: 'Equipment Types',
    component: AdminEquipmentTypesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/inventory/subtypes',
    name: 'Equipment Subtypes',
    component: AdminEquipmentSubtypesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/inventory/statuses',
    name: 'Equipment Statuses',
    component: AdminEquipmentStatusesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/inventory/attributes',
    name: 'Equipment Attributes',
    component: AdminEquipmentAttributesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/communications',
    name: 'Communications Management',
    component: AdminCommunicationsView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/communications/categories',
    name: 'Communication Categories',
    component: AdminCommunicationCategoriesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/communications/purposes',
    name: 'Communication Purposes',
    component: AdminCommunicationPurposesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/communications/statuses',
    name: 'Communication Statuses',
    component: AdminCommunicationStatusesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/accomplishments',
    name: 'Accomplishments Management',
    component: AdminAccomplishmentsView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/accomplishments/categories',
    name: 'Accomplishment Categories',
    component: AdminAccomplishmentCategoriesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/users',
    name: 'User Management',
    component: AdminUsersView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },
  {
    path: '/administrator/modules',
    name: 'Module Management',
    component: AdminModulesView,
    meta: {
      module: ModuleName.Administrator,
      requiresAuth: true,
      requiresRole: 'Administrator'
    }
  },

  // OPERATIONAL MODULE ROUTES
  {
    path: '/communications',
    name: 'Communications',
    component: CommunicationsView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    }
  },
  {
    path: '/communications/incoming',
    name: 'Incoming Communications',
    component: IncomingCommunicationsView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    },
  },
  {
    path: '/communications/outgoing',
    name: 'Outgoing Communications',
    component: OutgoingCommunicationsView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    },
  },
  {
    path: '/communications/reports',
    name: 'Communication Reports',
    component: CommunicationReportsView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    },
  },
  {
    path: '/communications/detail/:id',
    name: 'Communication Detail',
    component: CommunicationDetailView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    }
  },
  {
    path: '/communications/detail/:id/edit',
    name: 'Edit Communication',
    component: CommunicationEditView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    }
  },
  {
    path: '/inventory',
    name: 'Inventory',
    component: InventoryView,
    meta: {
      module: ModuleName.Inventory,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/equipment',
    redirect: '/inventory/equipment/ict'
  },
  {
    path: '/inventory/equipment/detail/:id',
    name: 'User Equipment Detail',
    component: EquipmentDetailView,
    meta: {
      module: ModuleName.Equipment,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/equipment/ict',
    name: 'ICT Equipment',
    component: EquipmentView,
    meta: {
      module: ModuleName.Equipment,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/equipment/communications',
    name: 'Communications Equipment',
    component: EquipmentView,
    meta: {
      module: ModuleName.Equipment,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/jrrs',
    redirect: '/inventory/jrrs/ict'
  },
  {
    path: '/inventory/jrrs/ict',
    name: 'ICT JRRS Readiness',
    component: JRRSView,
    meta: {
      module: ModuleName.Equipment,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/jrrs/communications',
    name: 'Communications JRRS Readiness',
    component: JRRSView,
    meta: {
      module: ModuleName.Equipment,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments',
    name: 'Accomplishments',
    component: AccomplishmentView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/daily',
    name: 'Daily Report',
    component: AccomplishmentDailyView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/detail/:id',
    name: 'Accomplishment Detail',
    component: AccomplishmentDetailView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/monthly',
    name: 'Monthly Report',
    component: AccomplishmentMonthlyView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/quarterly',
    name: 'Quarterly Report',
    component: AccomplishmentQuarterlyView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/annual',
    name: 'Annual Report',
    component: AccomplishmentAnnualView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/custom',
    name: 'Custom Period Report',
    component: AccomplishmentCustomView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },

  // CALENDAR MODULE ROUTES
  {
    path: '/calendar',
    name: 'Calendar',
    component: CalendarView,
    meta: {
      module: ModuleName.Calendar,
      requiresAuth: true
    }
  },
  {
    path: '/calendar/day/:date',
    redirect: to => ({
      path: '/calendar',
      query: { view: 'day', date: to.params.date }
    })
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// Centralized Authentication, Authorization & Module Activation Navigation Guard
router.beforeEach(async (to, _from, next) => {
  const isPublicRoute = to.meta.requiresAuth === false
  const requiredRole = to.meta.requiresRole as string | undefined
  const user = await fetchCurrentUser()

  if (!isPublicRoute && !user) {
    // Unauthenticated user attempting to access protected route -> redirect to /login
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }
  
  if (to.path === '/login' && user) {
    // Authenticated user attempting to access login page -> redirect to /home
    next('/home')
    return
  }
  
  if (requiredRole && user && user.role !== requiredRole) {
    // Authenticated user attempting role-restricted route without permission -> redirect to /home
    next('/home')
    return
  }

  // Check module activation if route specifies a module key
  if (user) {
    const { loadModules, isEnabled } = useModules()
    await loadModules()

    const targetModule = to.meta.module as string | undefined
    if (targetModule && !isEnabled(targetModule)) {
      console.warn(`[router] Direct route blocked: Module '${targetModule}' is disabled on this system. Redirecting to /home.`)
      next('/home')
      return
    }
  }

  next()
})

export default router
