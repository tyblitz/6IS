<template>
  <nav class="app-breadcrumb" aria-label="Breadcrumb">
    <template v-for="(item, index) in breadcrumbs" :key="index">
      <router-link
        v-if="item.path && index < breadcrumbs.length - 1"
        :to="item.path"
        class="breadcrumb-link"
      >
        {{ item.label }}
      </router-link>
      
      <span v-else class="breadcrumb-current">
        {{ item.label }}
      </span>

      <span v-if="index < breadcrumbs.length - 1" class="separator">/</span>
    </template>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

interface BreadcrumbItem {
  label: string;
  path?: string;
}

const route = useRoute()

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
  const items: BreadcrumbItem[] = [
    { label: 'Dashboard', path: '/home' }
  ]

  const path = route.path

  if (path === '/home') {
    items.push({ label: 'Home' })
  } else if (path.startsWith('/accomplishments')) {
    items.push({
      label: 'Accomplishments',
      path: path === '/accomplishments' ? undefined : '/accomplishments'
    })

    if (path === '/accomplishments/daily') {
      items.push({ label: 'Daily Report' })
    } else if (path === '/accomplishments/monthly') {
      items.push({ label: 'Monthly Report' })
    } else if (path === '/accomplishments/quarterly') {
      items.push({ label: 'Quarterly Report' })
    } else if (path === '/accomplishments/annual') {
      items.push({ label: 'Annual Report' })
    } else if (path === '/accomplishments/custom') {
      items.push({ label: 'Custom Period Report' })
    }
  } else if (path.startsWith('/inventory')) {
    items.push({
      label: 'Inventory',
      path: path === '/inventory' ? undefined : '/inventory'
    })

    if (path === '/inventory/equipment') {
      items.push({ label: 'ICT Equipment' })
    } else if (path === '/inventory/jrrs') {
      items.push({ label: 'JRRS' })
    }
  } else if (path === '/communications') {
    items.push({ label: 'Communications' })
  } else {
    items.push({ label: route.name?.toString() || 'Page' })
  }

  return items
})
</script>

<style scoped>
.app-breadcrumb {
  height: 44px;
  min-height: 44px;
  max-height: 44px;
  box-sizing: border-box;
  flex-shrink: 0;
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 28px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  font-size: 13px;
  white-space: nowrap;
  overflow: hidden;
}

.breadcrumb-link {
  color: #2563eb;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.15s ease;
}

.breadcrumb-link:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.breadcrumb-current {
  font-weight: 600;
  color: #475569;
}

.separator {
  color: #94a3b8;
  font-size: 12px;
}
</style>