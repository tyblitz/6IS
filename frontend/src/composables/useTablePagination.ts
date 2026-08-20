// frontend/src/composables/useTablePagination.ts
// Reusable Composable for Table Search, Filtering, Column Sorting, and 10-Item Limit Pagination

import { ref, computed, watch, type Ref } from 'vue'

export interface SortState {
  key: string
  order: 'asc' | 'desc'
}

export function useTablePagination<T extends Record<string, any>>(
  items: Ref<T[]>,
  options: {
    pageSize?: number
    defaultSortKey?: string
    defaultSortOrder?: 'asc' | 'desc'
    searchFields?: (keyof T | string)[]
  } = {}
) {
  const pageSize = ref(options.pageSize || 10)
  const currentPage = ref(1)
  const searchQuery = ref('')
  const sortKey = ref(options.defaultSortKey || '')
  const sortOrder = ref<'asc' | 'desc'>(options.defaultSortOrder || 'asc')

  // Reset to page 1 whenever search or items change
  watch([searchQuery, items], () => {
    currentPage.value = 1
  })

  // Search Filtered Items
  const searchFilteredItems = computed(() => {
    const list = items.value || []
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return list

    const fields = options.searchFields

    return list.filter((item) => {
      if (fields && fields.length > 0) {
        return fields.some((f) => {
          const val = item[f as string]
          return val !== undefined && val !== null && String(val).toLowerCase().includes(q)
        })
      }
      // Fallback: search all primitive object values
      return Object.values(item).some(
        (val) => val !== undefined && val !== null && String(val).toLowerCase().includes(q)
      )
    })
  })

  // Sorted Items
  const sortedItems = computed(() => {
    const list = [...searchFilteredItems.value]
    if (!sortKey.value) return list

    const k = sortKey.value
    const isAsc = sortOrder.value === 'asc'

    return list.sort((a, b) => {
      let valA = a[k]
      let valB = b[k]

      if (valA === undefined || valA === null) valA = ''
      if (valB === undefined || valB === null) valB = ''

      if (typeof valA === 'number' && typeof valB === 'number') {
        return isAsc ? valA - valB : valB - valA
      }

      const strA = String(valA).toLowerCase()
      const strB = String(valB).toLowerCase()

      if (strA < strB) return isAsc ? -1 : 1
      if (strA > strB) return isAsc ? 1 : -1
      return 0
    })
  })

  // Paginated Items (Strictly 10 items max per page)
  const paginatedItems = computed(() => {
    const size = pageSize.value
    const start = (currentPage.value - 1) * size
    return sortedItems.value.slice(start, start + size)
  })

  const totalItems = computed(() => sortedItems.value.length)
  const totalPages = computed(() => Math.ceil(totalItems.value / pageSize.value) || 1)

  const startIndex = computed(() => {
    if (totalItems.value === 0) return 0
    return (currentPage.value - 1) * pageSize.value + 1
  })

  const endIndex = computed(() => {
    return Math.min(currentPage.value * pageSize.value, totalItems.value)
  })

  function toggleSort(key: string) {
    if (sortKey.value === key) {
      if (sortOrder.value === 'asc') {
        sortOrder.value = 'desc'
      } else {
        sortKey.value = ''
        sortOrder.value = 'asc'
      }
    } else {
      sortKey.value = key
      sortOrder.value = 'asc'
    }
    currentPage.value = 1
  }

  function setPage(page: number) {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
    }
  }

  return {
    searchQuery,
    sortKey,
    sortOrder,
    currentPage,
    pageSize,
    totalItems,
    totalPages,
    startIndex,
    endIndex,
    sortedItems,
    paginatedItems,
    toggleSort,
    setPage
  }
}
