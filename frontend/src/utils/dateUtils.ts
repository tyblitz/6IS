// frontend/src/utils/dateUtils.ts
// Centralized Date & Time Formatting Utilities for 6IS Application
// Strictly adheres to Workspace Customization Rules in .agents/AGENTS.md

const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const DAYS_OF_WEEK = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

function parseDateObject(dateInput?: string | Date | null): Date | null {
  if (!dateInput) return null
  if (dateInput instanceof Date) {
    return isNaN(dateInput.getTime()) ? null : dateInput
  }
  const clean = dateInput.replace(' ', 'T')
  const d = new Date(clean)
  return isNaN(d.getTime()) ? null : d
}

/**
 * 1. Date Only Format: "DD MMM YYYY" (e.g., "27 Aug 2026")
 */
export function formatDate(dateInput?: string | Date | null): string {
  if (!dateInput) return 'N/A'

  // Fast-path parsing for 'YYYY-MM-DD' strings to avoid local timezone off-by-one shifts
  if (typeof dateInput === 'string') {
    const cleanDateStr = dateInput.includes('T') ? dateInput.split('T')[0] : dateInput.split(' ')[0]
    const parts = cleanDateStr.split('-')
    if (parts.length === 3) {
      const year = parseInt(parts[0], 10)
      const monthIdx = parseInt(parts[1], 10) - 1
      const day = parseInt(parts[2], 10)
      if (!isNaN(year) && !isNaN(monthIdx) && !isNaN(day) && monthIdx >= 0 && monthIdx < 12) {
        const formattedDay = String(day).padStart(2, '0')
        return `${formattedDay} ${MONTHS_SHORT[monthIdx]} ${year}`
      }
    }
  }

  const d = parseDateObject(dateInput)
  if (!d) return typeof dateInput === 'string' ? dateInput : 'N/A'
  const day = String(d.getDate()).padStart(2, '0')
  const month = MONTHS_SHORT[d.getMonth()]
  const year = d.getFullYear()
  return `${day} ${month} ${year}`
}

/**
 * 2. Time Only Format: Military time with trailing 'H' ("HHmmH", e.g., "1400H", "0830H")
 */
export function formatTime(timeInput?: string | Date | null): string {
  if (!timeInput) return ''

  if (typeof timeInput === 'string') {
    // Check if format is "HH:MM" or "HH:MM:SS"
    const timeOnlyMatch = timeInput.match(/^(\d{1,2}):(\d{2})/)
    if (timeOnlyMatch) {
      const hh = timeOnlyMatch[1].padStart(2, '0')
      const mm = timeOnlyMatch[2]
      return `${hh}${mm}H`
    }
  }

  const d = parseDateObject(timeInput)
  if (!d) return ''
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${hh}${mm}H`
}

/**
 * 3. Date and Time Combined Format: "DD HHmmH MMM YYYY" (e.g., "27 1400H Aug 2026")
 */
export function formatDateTime(dateTimeInput?: string | Date | null): string {
  if (!dateTimeInput) return 'N/A'
  const d = parseDateObject(dateTimeInput)
  if (!d) return typeof dateTimeInput === 'string' ? dateTimeInput : 'N/A'

  const day = String(d.getDate()).padStart(2, '0')
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  const month = MONTHS_SHORT[d.getMonth()]
  const year = d.getFullYear()

  return `${day} ${hh}${mm}H ${month} ${year}`
}

export const formatDateTimeCombined = formatDateTime

/**
 * 4. Date, Time, and Day of Week Format: "DD HHmmH MMM YYYY dddd" (e.g., "27 1400H Aug 2026 Friday")
 */
export function formatDateTimeWithDay(dateTimeInput?: string | Date | null): string {
  if (!dateTimeInput) return 'N/A'
  const d = parseDateObject(dateTimeInput)
  if (!d) return typeof dateTimeInput === 'string' ? dateTimeInput : 'N/A'

  const day = String(d.getDate()).padStart(2, '0')
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  const month = MONTHS_SHORT[d.getMonth()]
  const year = d.getFullYear()
  const weekday = DAYS_OF_WEEK[d.getDay()]

  return `${day} ${hh}${mm}H ${month} ${year} ${weekday}`
}
