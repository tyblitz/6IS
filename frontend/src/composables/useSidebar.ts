import { ref } from 'vue'

const isSidebarCollapsed = ref(false)

export function useSidebar() {
  function toggleSidebar() {
    isSidebarCollapsed.value = !isSidebarCollapsed.value
  }

  return {
    isSidebarCollapsed,
    toggleSidebar
  }
}
