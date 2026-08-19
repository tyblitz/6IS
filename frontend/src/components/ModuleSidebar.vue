<!--
ModuleSidebar
Reusable sidebar navigation for application modules with sequential accordion collapse & expand transitions.
-->

<template>
  <div v-if="menuItems.length > 0" :class="['app-sidebar', isSidebarCollapsed ? 'is-collapsed' : '']">
    <template v-for="item in menuItems" :key="item.route">
      
      <!-- Sub-menu Group (Parent with Children) -->
      <div v-if="item.children && item.children.length > 0" class="sidebar-group">
        <div
          :class="['sidebar-item', 'has-children', isGroupActive(item) ? 'parent-active' : '']"
          @click="handleGroupClick(item)"
        >
          <div class="sidebar-item-content">
            <ion-icon v-if="item.icon" :icon="item.icon" class="sidebar-icon" />
            <span>{{ item.label }}</span>
          </div>
          <ion-icon
            :icon="chevronDownOutline"
            :class="['chevron-icon', isExpanded(item) ? 'chevron-rotated' : '']"
          />
        </div>

        <transition
          name="accordion-anim"
          @before-enter="onBeforeEnter"
          @enter="onEnter"
          @after-enter="onAfterEnter"
          @before-leave="onBeforeLeave"
          @leave="onLeave"
          @after-leave="onAfterLeave"
        >
          <div v-show="isExpanded(item)" class="sidebar-subitems">
            <router-link
              v-for="sub in item.children"
              :key="sub.route"
              :to="sub.route"
              :class="['sidebar-subitem', isSubActive(sub.route) ? 'sub-active' : '']"
            >
              <ion-icon v-if="sub.icon" :icon="sub.icon" class="sub-icon" />
              <span>{{ sub.label }}</span>
            </router-link>
          </div>
        </transition>
      </div>

      <!-- Standard Single Item -->
      <router-link
        v-else
        :to="item.route"
        class="sidebar-item"
      >
        <ion-icon v-if="item.icon" :icon="item.icon" class="sidebar-icon" />
        <span>{{ item.label }}</span>
      </router-link>

    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IonIcon } from '@ionic/vue'
import { chevronDownOutline } from 'ionicons/icons'

import '../assets/styles/components/sidebar.css'

import { moduleMenus } from '../menus'
import { ModuleName } from '../types/module'
import type { SidebarItem } from '../types/SidebarItem'
import { useSidebar } from '../composables/useSidebar'

const route = useRoute()
const router = useRouter()
const { isSidebarCollapsed } = useSidebar()

// Track explicit toggle states for collapsible groups
const collapsedState = ref<Record<string, boolean>>({})
const currentOpenGroupLabel = ref<string | null>(null)

const menuItems = computed(() => {
  const module = route.meta.module as ModuleName
  return moduleMenus[module] ?? []
})

function isGroupActive(group: SidebarItem): boolean {
  if (route.path === group.route) return true
  if (group.children) {
    return group.children.some(child => route.path === child.route || route.path.startsWith(child.route + '/'))
  }
  return false
}

function isSubActive(subRoute: string): boolean {
  if (subRoute === '/administrator/inventory') {
    return route.path === '/administrator/inventory' || route.path.startsWith('/administrator/inventory/equipment/')
  }
  return route.path === subRoute
}

function isExpanded(group: SidebarItem): boolean {
  if (group.label in collapsedState.value) {
    return collapsedState.value[group.label]
  }
  return currentOpenGroupLabel.value === group.label
}

async function handleGroupClick(group: SidebarItem) {
  const isCurrentlyOpen = isExpanded(group)

  if (isCurrentlyOpen) {
    collapsedState.value[group.label] = false
    if (currentOpenGroupLabel.value === group.label) {
      currentOpenGroupLabel.value = null
    }
  } else {
    await openGroupSequentially(group.label)
    if (route.path !== group.route) {
      router.push(group.route)
    }
  }
}

async function openGroupSequentially(targetLabel: string) {
  if (currentOpenGroupLabel.value && currentOpenGroupLabel.value !== targetLabel) {
    const previousLabel = currentOpenGroupLabel.value
    // Step 1: Smoothly collapse the currently open group
    collapsedState.value[previousLabel] = false
    currentOpenGroupLabel.value = null
    
    // Wait 280ms for collapse animation to complete before opening the next group
    await new Promise(resolve => setTimeout(resolve, 280))
  }

  // Step 2: Open the target group
  collapsedState.value[targetLabel] = true
  currentOpenGroupLabel.value = targetLabel
}

// Smooth JS Transition Hooks for Accordion Animation
function onBeforeEnter(el: Element) {
  const target = el as HTMLElement
  target.style.height = '0px'
  target.style.opacity = '0'
  target.style.overflow = 'hidden'
}

function onEnter(el: Element) {
  const target = el as HTMLElement
  target.style.transition = 'height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.25s ease'
  target.style.height = target.scrollHeight + 'px'
  target.style.opacity = '1'
}

function onAfterEnter(el: Element) {
  const target = el as HTMLElement
  target.style.height = 'auto'
  target.style.opacity = ''
  target.style.overflow = ''
}

function onBeforeLeave(el: Element) {
  const target = el as HTMLElement
  target.style.height = target.scrollHeight + 'px'
  target.style.opacity = '1'
  target.style.overflow = 'hidden'
}

function onLeave(el: Element) {
  const target = el as HTMLElement
  // Force browser reflow
  void target.offsetHeight
  target.style.transition = 'height 0.28s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease'
  target.style.height = '0px'
  target.style.opacity = '0'
}

function onAfterLeave(el: Element) {
  const target = el as HTMLElement
  target.style.height = ''
  target.style.opacity = ''
  target.style.overflow = ''
}

// Auto-expand group whenever route changes to a child within it
watch(
  () => route.path,
  async () => {
    const activeGroup = menuItems.value.find(item => item.children && isGroupActive(item))
    if (activeGroup) {
      if (currentOpenGroupLabel.value !== activeGroup.label) {
        await openGroupSequentially(activeGroup.label)
      }
    } else if (currentOpenGroupLabel.value) {
      // If navigating to a single item outside any group, collapse current open group
      collapsedState.value[currentOpenGroupLabel.value] = false
      currentOpenGroupLabel.value = null
    }
  },
  { immediate: true }
)
</script>