// frontend/src/services/calendarService.ts

import type {
  CalendarActivity,
  CalendarEventFormPayload,
  CalendarSummaryMetrics,
  MonthEventsResponse,
  WeekEventsResponse,
  CalendarStatus,
  ReschedulePayload,
  CreateAccomplishmentFromEventPayload,
  CalendarEventTypeOption
} from '../types/calendar';
import { apiFetch } from '../utils/api';

function resolveApiUrl(): string {
  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost';
    const protocol = window.location.protocol || 'http:';
    return `${protocol}//${host}/6IS/backend/api/calendar/index.php`;
  }
  return 'http://localhost/6IS/backend/api/calendar/index.php';
}

const API_BASE = resolveApiUrl();

/**
 * Fetch calendar event types reference options (PAS, CONF, VTC)
 */
export async function fetchCalendarEventTypes(): Promise<CalendarEventTypeOption[]> {
  try {
    const res = await fetch(`${API_BASE}?action=types`, { credentials: 'include' });
    const json = await res.json();
    return json.success ? json.data : [];
  } catch (err) {
    console.error('Error fetching calendar event types:', err);
    return [];
  }
}

/**
 * Fetch authoritative calendar activities within a given date range
 */
export async function fetchCalendarActivities(
  startDate: string,
  endDate: string,
  typeId: number = 0,
  status: string = 'all',
  search: string = ''
): Promise<CalendarActivity[]> {
  try {
    const params = new URLSearchParams({
      start: startDate,
      end: endDate,
      type_id: String(typeId),
      status: status,
      search: search
    });
    const res = await fetch(`${API_BASE}?${params.toString()}`, { credentials: 'include' });
    const json: MonthEventsResponse = await res.json();
    return json.success ? json.data : [];
  } catch (err) {
    console.error('Error fetching calendar activities:', err);
    return [];
  }
}

/**
 * Fetch detailed standalone event with full reschedule and status history
 */
export async function fetchCalendarActivityDetail(id: number): Promise<CalendarActivity | null> {
  try {
    const res = await fetch(`${API_BASE}?id=${id}`, { credentials: 'include' });
    const json = await res.json();
    return json.success ? json.data : null;
  } catch (err) {
    console.error('Error fetching event details:', err);
    return null;
  }
}

/**
 * Update event status (Scheduled, In Progress, Accomplished, Canceled, Postponed)
 */
export async function updateEventStatus(
  id: number,
  status: CalendarStatus,
  reason: string = ''
): Promise<{ success: boolean; message?: string; data?: any }> {
  try {
    const res = await fetch(`${API_BASE}?action=status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id, status, reason })
    });
    return await res.json();
  } catch (err) {
    console.error('Error updating event status:', err);
    return { success: false, message: 'Network error updating event status.' };
  }
}

/**
 * Reschedule event to new start/end datetime
 */
export async function rescheduleEvent(
  payload: ReschedulePayload
): Promise<{ success: boolean; message?: string; data?: any }> {
  try {
    const res = await fetch(`${API_BASE}?action=reschedule`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err) {
    console.error('Error rescheduling event:', err);
    return { success: false, message: 'Network error rescheduling event.' };
  }
}

/**
 * Restore a canceled event back to Scheduled status
 */
export async function restoreCanceledEvent(
  id: number,
  reason: string = 'Restored by user'
): Promise<{ success: boolean; message?: string }> {
  try {
    const res = await fetch(`${API_BASE}?action=restore`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id, reason })
    });
    return await res.json();
  } catch (err) {
    console.error('Error restoring event:', err);
    return { success: false, message: 'Network error restoring event.' };
  }
}

/**
 * Create a Daily Accomplishment linked to a Calendar Event
 */
export async function createAccomplishmentFromEvent(
  payload: CreateAccomplishmentFromEventPayload
): Promise<{ success: boolean; message?: string; accomplishment_id?: number }> {
  try {
    const res = await apiFetch(`${API_BASE}?action=create_accomplishment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err) {
    console.error('Error creating accomplishment from event:', err);
    return { success: false, message: 'Network error creating accomplishment.' };
  }
}

/**
 * Fetch aggregated events for the week containing the given date
 */
export async function fetchWeekEvents(date: string): Promise<{ events: CalendarActivity[]; weekStart: string; weekEnd: string }> {
  try {
    const res = await fetch(`${API_BASE}?view=week&date=${date}`, { credentials: 'include' });
    const json: WeekEventsResponse = await res.json();
    if (json.success) {
      const wStart = json.week_start || json.start_date || '';
      const wEnd = json.week_end || json.end_date || '';
      return { events: json.data || [], weekStart: wStart, weekEnd: wEnd };
    }
    return { events: [], weekStart: '', weekEnd: '' };
  } catch (err) {
    console.error('Error fetching week events:', err);
    return { events: [], weekStart: '', weekEnd: '' };
  }
}

/**
 * Fetch aggregated events for a month
 */
export async function fetchMonthEvents(year: number, month: number, typeId: number = 0): Promise<CalendarActivity[]> {
  try {
    const res = await fetch(`${API_BASE}?view=month&year=${year}&month=${month}&type_id=${typeId}`, { credentials: 'include' });
    const json: MonthEventsResponse = await res.json();
    return json.success ? json.data : [];
  } catch (err) {
    console.error('Error fetching month events:', err);
    return [];
  }
}

/**
 * Fetch summary metrics for today
 */
export async function fetchCalendarSummary(date?: string): Promise<CalendarSummaryMetrics | null> {
  try {
    const targetDate = date || new Date().toISOString().slice(0, 10);
    const res = await fetch(`${API_BASE}?summary=1&date=${targetDate}`, { credentials: 'include' });
    const json = await res.json();
    return json.success ? json.data : null;
  } catch (err) {
    console.error('Error fetching calendar summary:', err);
    return null;
  }
}

/**
 * Create a new standalone calendar event
 */
export async function createCalendarEvent(payload: CalendarEventFormPayload): Promise<{ success: boolean; message?: string; data?: CalendarActivity }> {
  try {
    const res = await fetch(API_BASE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err) {
    console.error('Error creating calendar event:', err);
    return { success: false, message: 'Network error creating calendar event.' };
  }
}

/**
 * Update an existing standalone calendar event
 */
export async function updateCalendarEvent(payload: CalendarEventFormPayload): Promise<{ success: boolean; message?: string; data?: CalendarActivity }> {
  try {
    const res = await fetch(API_BASE, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err) {
    console.error('Error updating calendar event:', err);
    return { success: false, message: 'Network error updating calendar event.' };
  }
}

/**
 * Delete a standalone calendar event
 */
export async function deleteCalendarEvent(id: number): Promise<{ success: boolean; message?: string }> {
  try {
    const res = await fetch(`${API_BASE}?id=${id}`, {
      method: 'DELETE',
      credentials: 'include'
    });
    return await res.json();
  } catch (err) {
    console.error('Error deleting calendar event:', err);
    return { success: false, message: 'Network error deleting calendar event.' };
  }
}
