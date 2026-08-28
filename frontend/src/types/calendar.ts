// frontend/src/types/calendar.ts

export type CalendarEventSource = 'calendar_event' | 'accomplishment' | 'incoming_comm' | 'outgoing_comm';
export type CalendarFilterSource = 'all' | 'calendar_event' | 'accomplishment' | 'incoming_comm' | 'outgoing_comm';
export type CalendarViewMode = 'month' | 'week' | 'day' | 'agenda';
export type CalendarStatus = 'Scheduled' | 'In Progress' | 'Accomplished' | 'Canceled' | 'Postponed';

export interface CalendarEventTypeOption {
  id: number;
  type_name: string;
  type_code: string;
  color?: string;
  is_active?: number;
}

export interface CalendarRescheduleRecord {
  id: number;
  calendar_event_id: number;
  previous_start_datetime: string;
  previous_end_datetime: string;
  new_start_datetime: string;
  new_end_datetime: string;
  reason?: string | null;
  changed_by: number;
  created_at: string;
}

export interface CalendarStatusHistoryRecord {
  id: number;
  calendar_event_id: number;
  previous_status?: string | null;
  new_status: string;
  reason?: string | null;
  changed_by: number;
  created_at: string;
}

export interface CalendarActivityMetadata {
  created_by?: number;
  created_at?: string;
  [key: string]: any;
}

export interface CalendarActivity {
  id: string; // e.g. 'cal_4'
  source: CalendarEventSource;
  source_id: number;
  title: string;
  description?: string | null;
  date: string; // YYYY-MM-DD
  time?: string | null;
  start_datetime: string; // YYYY-MM-DD HH:MM:SS
  end_datetime?: string | null;
  all_day: boolean;
  location?: string | null;
  office_id: number;
  office_name?: string;
  office_abbv?: string;
  status: string;
  category: string;
  category_name?: string;
  category_code?: string | null;
  priority?: string;
  event_type?: string;
  event_type_id?: number;
  event_type_code?: string;
  accomplishment_id?: number | null;
  has_accomplishment?: boolean;
  reschedule_count?: number;
  reschedules?: CalendarRescheduleRecord[];
  status_history?: CalendarStatusHistoryRecord[];
  metadata?: CalendarActivityMetadata;
}

export interface CalendarEvent extends CalendarActivity {}

export interface CalendarDayData {
  date: string;
  dayNumber: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  isWeekend: boolean;
  events: CalendarActivity[];
}

export interface CalendarEventFormPayload {
  id?: number;
  title: string;
  description?: string | null;
  event_date: string;
  event_time?: string | null;
  start_datetime?: string | null;
  end_datetime?: string | null;
  all_day?: boolean;
  office_id?: number;
  location?: string | null;
  event_type?: string;
  event_type_id?: number;
  priority?: string;
  status?: string;
}

export interface ReschedulePayload {
  id: number;
  new_start_datetime: string;
  new_end_datetime: string;
  reason?: string;
}

export interface CreateAccomplishmentFromEventPayload {
  calendar_event_id: number;
  title?: string;
  description: string;
  office_id: number;
  category_id: number;
  assigned_employee_id?: number;
  date?: string;
  date_started?: string;
  date_completed?: string;
  priority?: string;
  remarks?: string;
}

export interface CalendarSummaryMetrics {
  target_date: string;
  total_today: number;
  accomplishments_count: number;
  incoming_count: number;
  outgoing_count: number;
  events_count: number;
  upcoming: CalendarActivity[];
}

export interface WeekEventsResponse {
  success: boolean;
  data: CalendarActivity[];
  start_date?: string;
  end_date?: string;
  week_start?: string;
  week_end?: string;
}

export interface MonthEventsResponse {
  success: boolean;
  count: number;
  start_date: string;
  end_date: string;
  data: CalendarActivity[];
}

export const SOURCE_COLORS: Record<string, string> = {
  calendar_event: '#9333EA',
  accomplishment: '#16A34A',
  incoming_comm: '#2563EB',
  outgoing_comm: '#EA580C'
};

export const SOURCE_LABELS: Record<string, string> = {
  calendar_event: 'Calendar Activity',
  accomplishment: 'Accomplishment',
  incoming_comm: 'Incoming Comm',
  outgoing_comm: 'Outgoing Comm'
};

export const SOURCE_BADGE_CLASSES: Record<string, string> = {
  calendar_event: 'badge-event',
  accomplishment: 'badge-accomplishment',
  incoming_comm: 'badge-incoming',
  outgoing_comm: 'badge-outgoing'
};

export const EVENT_TYPE_LABELS: Record<string, string> = {
  PAS: 'Public Address System',
  CONF: 'Conference',
  VTC: 'Video Teleconference'
};
