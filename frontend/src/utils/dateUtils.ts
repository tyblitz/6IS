// frontend/src/utils/dateUtils.ts
// Centralized Date Formatting Utility for 6IS Application

/**
 * Formats a date string into "dd MMM yyyy" format (e.g., "14 Aug 2026")
 */
export function formatDate(dateStr?: string | null): string {
  if (!dateStr) return 'N/A'
  // Handle ISO string or YYYY-MM-DD
  const cleanDateStr = dateStr.includes('T') ? dateStr.split('T')[0] : dateStr
  const parts = cleanDateStr.split('-')
  if (parts.length === 3) {
    const year = parseInt(parts[0], 10)
    const monthIdx = parseInt(parts[1], 10) - 1
    const day = parseInt(parts[2], 10)
    if (!isNaN(year) && !isNaN(monthIdx) && !isNaN(day) && monthIdx >= 0 && monthIdx < 12) {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
      const formattedDay = String(day).padStart(2, '0')
      return `${formattedDay} ${months[monthIdx]} ${year}`
    }
  }

  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  const day = String(d.getDate()).padStart(2, '0')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[d.getMonth()]
  const year = d.getFullYear()
  return `${day} ${month} ${year}`
}
