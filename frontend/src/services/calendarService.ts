// frontend/src/services/calendarService.ts

import type {
  CalendarEvent,
  CalendarEventFormPayload,
  MonthEventsResponse,
  WeekEventsResponse
} from '../types/calendar';

const API_BASE = '/6IS/backend/api/calendar/index.php';

/**
 * Fetch aggregated events for a given month (accomplishments + communications + standalone events)
 */
export async function fetchMonthEvents(year: number, month: number): Promise<CalendarEvent[]> {
  try {
    const res = await fetch(`${API_BASE}?view=month&year=${year}&month=${month}`);
    const json: MonthEventsResponse = await res.json();
    return json.success ? json.data : [];
  } catch (err) {
    console.error('Error fetching month events:', err);
    return [];
  }
}

/**
 * Fetch aggregated events for the week containing the given date (Dashboard widget)
 */
export async function fetchWeekEvents(date: string): Promise<{ events: CalendarEvent[]; weekStart: string; weekEnd: string }> {
  try {
    const res = await fetch(`${API_BASE}?view=week&date=${date}`);
    const json: WeekEventsResponse = await res.json();
    if (json.success) {
      return { events: json.data, weekStart: json.week_start, weekEnd: json.week_end };
    }
    return { events: [], weekStart: '', weekEnd: '' };
  } catch (err) {
    console.error('Error fetching week events:', err);
    return { events: [], weekStart: '', weekEnd: '' };
  }
}

/**
 * Fetch a single standalone calendar event by ID
 */
export async function fetchCalendarEvent(id: number): Promise<CalendarEvent | null> {
  try {
    const res = await fetch(`${API_BASE}?id=${id}`);
    const json = await res.json();
    return json.success ? json.data : null;
  } catch (err) {
    console.error('Error fetching calendar event:', err);
    return null;
  }
}

/**
 * Create a new standalone calendar event
 */
export async function createCalendarEvent(payload: CalendarEventFormPayload): Promise<{ success: boolean; message: string; data?: any }> {
  try {
    const res = await fetch(API_BASE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err) {
    console.error('Error creating calendar event:', err);
    return { success: false, message: 'Network error' };
  }
}

/**
 * Update an existing standalone calendar event
 */
export async function updateCalendarEvent(payload: CalendarEventFormPayload): Promise<{ success: boolean; message: string; data?: any }> {
  try {
    const res = await fetch(API_BASE, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err) {
    console.error('Error updating calendar event:', err);
    return { success: false, message: 'Network error' };
  }
}

/**
 * Soft-delete a standalone calendar event
 */
export async function deleteCalendarEvent(id: number): Promise<{ success: boolean; message: string }> {
  try {
    const res = await fetch(`${API_BASE}?id=${id}`, { method: 'DELETE' });
    return await res.json();
  } catch (err) {
    console.error('Error deleting calendar event:', err);
    return { success: false, message: 'Network error' };
  }
}
