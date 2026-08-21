// frontend/src/types/calendar.ts

export type CalendarEventSource = 'accomplishment' | 'incoming_comm' | 'outgoing_comm' | 'calendar_event';
export type CalendarEventType = 'meeting' | 'deadline' | 'reminder' | 'other' | 'accomplishment' | 'communication';
export type CalendarEventPriority = 'low' | 'normal' | 'high';

export interface CalendarEvent {
  id: string;
  title: string;
  description?: string | null;
  date: string;
  time?: string | null;
  source: CalendarEventSource;
  source_id: number;
  event_type: CalendarEventType;
  priority: CalendarEventPriority;
  status?: string;
  category_name?: string;
  category_code?: string;
}

export interface CalendarDayData {
  date: string;
  dayNumber: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  events: CalendarEvent[];
}

export interface CalendarEventFormPayload {
  id?: number;
  title: string;
  description?: string | null;
  event_date: string;
  event_time?: string | null;
  event_type: CalendarEventType;
  priority: CalendarEventPriority;
}

export interface WeekEventsResponse {
  success: boolean;
  data: CalendarEvent[];
  week_start: string;
  week_end: string;
}

export interface MonthEventsResponse {
  success: boolean;
  data: CalendarEvent[];
}

export const SOURCE_COLORS: Record<CalendarEventSource, string> = {
  accomplishment: '#22c55e',    // green
  incoming_comm: '#3b82f6',     // blue
  outgoing_comm: '#f97316',     // orange
  calendar_event: '#a855f7'     // purple
};

export const SOURCE_LABELS: Record<CalendarEventSource, string> = {
  accomplishment: 'Accomplishment',
  incoming_comm: 'Incoming Communication',
  outgoing_comm: 'Outgoing Communication',
  calendar_event: 'Calendar Event'
};

export const EVENT_TYPE_LABELS: Record<string, string> = {
  meeting: 'Meeting',
  deadline: 'Deadline',
  reminder: 'Reminder',
  other: 'Other',
  accomplishment: 'Accomplishment',
  communication: 'Communication'
};

export const PRIORITY_COLORS: Record<CalendarEventPriority, string> = {
  low: '#94a3b8',
  normal: '#3b82f6',
  high: '#ef4444'
};
