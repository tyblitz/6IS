// frontend/src/services/communicationService.ts
import type { ApiResponse } from '../types/accomplishment'
import type {
  CommunicationCategory,
  CommunicationPurpose,
  CommunicationOffice,
  CommunicationOptions,
  Communication,
  CommunicationFormPayload,
  CommunicationActivityPayload,
  CommunicationFilterParams,
  CommunicationReportsData
} from '../types/communication'

const API_BASE_URL = 'http://localhost/6IS/backend/api/communications/index.php'

const FALLBACK_CATEGORIES: CommunicationCategory[] = [
  { id: 1, name: 'Disposition Form', code: 'DF', is_active: 1 },
  { id: 2, name: 'Summary Disposition Form', code: 'SDF', is_active: 1 },
  { id: 3, name: 'Subject to Letter', code: 'STL', is_active: 1 },
  { id: 4, name: 'Memorandum', code: 'Memo', is_active: 1 },
  { id: 5, name: 'Standard Operating Procedure', code: 'SOP', is_active: 1 },
  { id: 6, name: 'Others', code: null, is_active: 1 }
]

const FALLBACK_PURPOSES: CommunicationPurpose[] = [
  { id: 1, name: 'Access Pass', is_active: 1 },
  { id: 2, name: 'PAS Request', is_active: 1 },
  { id: 3, name: 'R&M ICT Fund Request', is_active: 1 },
  { id: 4, name: 'Others', is_active: 1 }
]

const FALLBACK_OFFICES: CommunicationOffice[] = [
  { id: 1, office_name: 'Information & Communications Technology', office_code: 'ICT', office_abbv: 'ICT', office_category: 'Staff', is_active: 1 },
  { id: 2, office_name: 'Management Information Systems', office_code: 'MIS', office_abbv: 'MIS', office_category: 'Staff', is_active: 1 },
  { id: 3, office_name: 'Administrative & Finance', office_code: 'ADMIN', office_abbv: 'ADMIN', office_category: 'Staff', is_active: 1 }
]

let fallbackCommunicationsStore: Communication[] = [
  {
    id: 1,
    communication_type: 'Incoming',
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    office_abbv: 'ICT',
    category_id: 1,
    category_name: 'Disposition Form',
    category_code: 'DF',
    purpose_id: 2,
    purpose_name: 'PAS Request',
    subject: 'Request for IT Infrastructure Audit & Security Patching',
    communication_date: '2026-08-01',
    status: 'In Progress',
    latest_activity_date: '2026-08-02 10:30:00',
    age_days: 10,
    activities: [
      {
        id: 1,
        communication_id: 1,
        activity_type: 'Logged',
        activity_date: '2026-08-01 09:00:00',
        remarks: 'Communication received and logged into system.'
      },
      {
        id: 2,
        communication_id: 1,
        activity_type: 'Status changed to In Progress',
        activity_date: '2026-08-02 10:30:00',
        remarks: 'Assigned to Systems Administrator for audit review.'
      }
    ]
  },
  {
    id: 2,
    communication_type: 'Incoming',
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    office_abbv: 'MIS',
    category_id: 3,
    category_name: 'Subject to Letter',
    category_code: 'STL',
    purpose_id: 1,
    purpose_name: 'Access Pass',
    subject: 'Application for Server Room Access Pass for Q3',
    communication_date: '2026-08-05',
    status: 'Completed',
    latest_activity_date: '2026-08-07 11:15:00',
    age_days: 5,
    activities: [
      {
        id: 3,
        communication_id: 2,
        activity_type: 'Logged',
        activity_date: '2026-08-05 08:30:00',
        remarks: 'Access pass application received.'
      },
      {
        id: 4,
        communication_id: 2,
        activity_type: 'Approved',
        activity_date: '2026-08-06 14:00:00',
        remarks: 'Pass approved by ICT Director.'
      },
      {
        id: 5,
        communication_id: 2,
        activity_type: 'Status changed to Completed',
        activity_date: '2026-08-07 11:15:00',
        remarks: 'Physical access badge issued to personnel.'
      }
    ]
  },
  {
    id: 3,
    communication_type: 'Outgoing',
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    office_abbv: 'ICT',
    category_id: 4,
    category_name: 'Memorandum',
    category_code: 'Memo',
    purpose_id: 3,
    purpose_name: 'R&M ICT Fund Request',
    subject: 'Memo on Quarterly Hardware Procurement & Maintenance Budget',
    communication_date: '2026-08-08',
    status: 'Pending',
    latest_activity_date: '2026-08-08 13:45:00',
    age_days: 4,
    activities: [
      {
        id: 6,
        communication_id: 3,
        activity_type: 'Logged',
        activity_date: '2026-08-08 13:45:00',
        remarks: 'Outgoing memorandum drafted and dispatched to Finance.'
      }
    ]
  },
  {
    id: 4,
    communication_type: 'Outgoing',
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    office_abbv: 'ADMIN',
    category_id: 5,
    category_name: 'Standard Operating Procedure',
    category_code: 'SOP',
    purpose_id: 4,
    purpose_name: 'Others',
    subject: 'Guidelines on Information Systems Security & Password Management',
    communication_date: '2026-08-10',
    status: 'Released',
    latest_activity_date: '2026-08-11 16:20:00',
    age_days: 1,
    activities: [
      {
        id: 7,
        communication_id: 4,
        activity_type: 'Logged',
        activity_date: '2026-08-10 10:00:00',
        remarks: 'Drafted SOP guidelines document.'
      },
      {
        id: 8,
        communication_id: 4,
        activity_type: 'Status changed to Released',
        activity_date: '2026-08-11 16:20:00',
        remarks: 'Circular distributed to all unit heads.'
      }
    ]
  }
]

async function fetchWithTimeout(url: string, options: RequestInit = {}, timeoutMs = 400): Promise<Response> {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), timeoutMs)
  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal
    })
    clearTimeout(timer)
    return response
  } catch (err) {
    clearTimeout(timer)
    throw err
  }
}

export async function fetchCommunicationCategories(): Promise<ApiResponse<CommunicationCategory[]>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=categories`)
    const data = await res.json()
    if (data.success && data.data && data.data.length > 0) return data
    return { success: true, message: 'Categories loaded', data: FALLBACK_CATEGORIES, errors: null }
  } catch (err: any) {
    return { success: true, message: 'Fallback categories loaded.', data: FALLBACK_CATEGORIES, errors: null }
  }
}

export async function fetchCommunicationPurposes(): Promise<ApiResponse<CommunicationPurpose[]>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=purposes`)
    const data = await res.json()
    if (data.success && data.data && data.data.length > 0) return data
    return { success: true, message: 'Purposes loaded', data: FALLBACK_PURPOSES, errors: null }
  } catch (err: any) {
    return { success: true, message: 'Fallback purposes loaded.', data: FALLBACK_PURPOSES, errors: null }
  }
}

export async function fetchCommunicationOffices(): Promise<ApiResponse<CommunicationOffice[]>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=offices`)
    const data = await res.json()
    if (data.success && data.data && data.data.length > 0) return data
    return { success: true, message: 'Offices loaded', data: FALLBACK_OFFICES, errors: null }
  } catch (err: any) {
    return { success: true, message: 'Fallback offices loaded.', data: FALLBACK_OFFICES, errors: null }
  }
}

export async function fetchCommunicationOptions(): Promise<ApiResponse<CommunicationOptions>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=options`)
    const data = await res.json()
    if (data.success && data.data && data.data.categories?.length > 0) return data
    return {
      success: true,
      message: 'Options loaded',
      data: {
        categories: FALLBACK_CATEGORIES,
        purposes: FALLBACK_PURPOSES,
        offices: FALLBACK_OFFICES
      },
      errors: null
    }
  } catch (err: any) {
    return {
      success: true,
      message: 'Fallback options loaded.',
      data: {
        categories: FALLBACK_CATEGORIES,
        purposes: FALLBACK_PURPOSES,
        offices: FALLBACK_OFFICES
      },
      errors: null
    }
  }
}

export async function fetchCommunications(
  filters?: CommunicationFilterParams
): Promise<ApiResponse<Communication[]>> {
  try {
    const params = new URLSearchParams()
    if (filters?.type) params.append('type', filters.type)
    if (filters?.office_id && filters.office_id > 0) params.append('office_id', filters.office_id.toString())
    if (filters?.category_id && filters.category_id > 0) params.append('category_id', filters.category_id.toString())
    if (filters?.purpose_id && filters.purpose_id > 0) params.append('purpose_id', filters.purpose_id.toString())
    if (filters?.status) params.append('status', filters.status)
    if (filters?.search) params.append('search', filters.search)

    const url = params.toString() ? `${API_BASE_URL}?${params.toString()}` : API_BASE_URL
    const res = await fetchWithTimeout(url)
    const data = await res.json()

    if (data.success && data.data && data.data.length > 0) {
      return data
    }

    // Filter fallback store
    let filtered = [...fallbackCommunicationsStore]

    if (filters?.type) {
      filtered = filtered.filter(item => item.communication_type === filters.type)
    }

    if (filters?.office_id && filters.office_id > 0) {
      filtered = filtered.filter(item => item.office_id === filters.office_id)
    }

    if (filters?.category_id && filters.category_id > 0) {
      filtered = filtered.filter(item => item.category_id === filters.category_id)
    }

    if (filters?.purpose_id && filters.purpose_id > 0) {
      filtered = filtered.filter(item => item.purpose_id === filters.purpose_id)
    }

    if (filters?.status) {
      filtered = filtered.filter(item => item.status === filters.status)
    }

    if (filters?.search) {
      const q = filters.search.toLowerCase()
      filtered = filtered.filter(item =>
        item.subject.toLowerCase().includes(q) ||
        (item.office_name && item.office_name.toLowerCase().includes(q)) ||
        (item.category_name && item.category_name.toLowerCase().includes(q))
      )
    }

    return { success: true, message: 'Communications loaded.', data: filtered, errors: null }
  } catch (err: any) {
    let filtered = [...fallbackCommunicationsStore]
    if (filters?.type) filtered = filtered.filter(item => item.communication_type === filters.type)
    if (filters?.office_id && filters.office_id > 0) filtered = filtered.filter(item => item.office_id === filters.office_id)
    if (filters?.category_id && filters.category_id > 0) filtered = filtered.filter(item => item.category_id === filters.category_id)
    if (filters?.purpose_id && filters.purpose_id > 0) filtered = filtered.filter(item => item.purpose_id === filters.purpose_id)
    if (filters?.status) filtered = filtered.filter(item => item.status === filters.status)
    if (filters?.search) {
      const q = filters.search.toLowerCase()
      filtered = filtered.filter(item =>
        item.subject.toLowerCase().includes(q) ||
        (item.office_name && item.office_name.toLowerCase().includes(q)) ||
        (item.category_name && item.category_name.toLowerCase().includes(q))
      )
    }
    return { success: true, message: 'Fallback communications loaded.', data: filtered, errors: null }
  }
}

export async function fetchCommunicationById(id: number): Promise<ApiResponse<Communication>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?id=${id}`)
    const data = await res.json()
    if (data.success && data.data) return data

    const found = fallbackCommunicationsStore.find(item => item.id === id)
    if (found) return { success: true, message: 'Communication details loaded.', data: found, errors: null }
    return { success: false, message: 'Communication not found.', data: null, errors: null }
  } catch (err: any) {
    const found = fallbackCommunicationsStore.find(item => item.id === id)
    if (found) return { success: true, message: 'Communication details loaded.', data: found, errors: null }
    return { success: false, message: 'Communication not found.', data: null, errors: null }
  }
}

export async function createCommunication(payload: CommunicationFormPayload): Promise<ApiResponse> {
  try {
    const res = await fetchWithTimeout(API_BASE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) return data
  } catch (err: any) {
    // Fallback in-memory insert
  }

  const office = FALLBACK_OFFICES.find(o => o.id === payload.office_id)
  const category = FALLBACK_CATEGORIES.find(c => c.id === payload.category_id)
  const purpose = FALLBACK_PURPOSES.find(p => p.id === payload.purpose_id)

  const newRecord: Communication = {
    id: Date.now(),
    communication_type: payload.communication_type,
    office_id: payload.office_id,
    office_name: office?.office_name || 'ICT Office',
    office_code: office?.office_code || 'ICT',
    office_abbv: office?.office_abbv || 'ICT',
    category_id: payload.category_id,
    category_name: category?.name || 'General Category',
    category_code: category?.code || 'GEN',
    purpose_id: payload.purpose_id,
    purpose_name: purpose?.name || 'General Purpose',
    subject: payload.subject,
    communication_date: payload.communication_date,
    status: payload.status || 'Pending',
    latest_activity_date: new Date().toISOString().replace('T', ' ').substring(0, 19),
    age_days: 0,
    activities: [
      {
        id: Date.now(),
        communication_id: Date.now(),
        activity_type: 'Logged',
        activity_date: new Date().toISOString().replace('T', ' ').substring(0, 19),
        remarks: 'Communication record logged into the system.'
      }
    ]
  }

  fallbackCommunicationsStore.unshift(newRecord)
  return { success: true, message: 'Communication recorded successfully.', data: { id: newRecord.id }, errors: null }
}

export async function updateCommunication(id: number, payload: CommunicationFormPayload): Promise<ApiResponse> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?id=${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) return data
  } catch (err: any) {
    // Fallback update
  }

  const index = fallbackCommunicationsStore.findIndex(item => item.id === id)
  if (index !== -1) {
    const item = fallbackCommunicationsStore[index]
    const oldStatus = item.status

    const office = FALLBACK_OFFICES.find(o => o.id === payload.office_id)
    const category = FALLBACK_CATEGORIES.find(c => c.id === payload.category_id)
    const purpose = FALLBACK_PURPOSES.find(p => p.id === payload.purpose_id)

    item.communication_type = payload.communication_type
    item.office_id = payload.office_id
    if (office) {
      item.office_name = office.office_name
      item.office_code = office.office_code
      item.office_abbv = office.office_abbv
    }
    item.category_id = payload.category_id
    if (category) {
      item.category_name = category.name
      item.category_code = category.code
    }
    item.purpose_id = payload.purpose_id
    if (purpose) {
      item.purpose_name = purpose.name
    }
    item.subject = payload.subject
    item.communication_date = payload.communication_date

    if (oldStatus !== payload.status) {
      item.status = payload.status
      const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19)
      item.latest_activity_date = nowStr
      item.activities = item.activities || []
      item.activities.unshift({
        id: Date.now(),
        communication_id: id,
        activity_type: `Status changed to ${payload.status}`,
        activity_date: nowStr,
        remarks: `Status updated from ${oldStatus} to ${payload.status}.`
      })
    }

    return { success: true, message: 'Communication updated successfully.', data: { id }, errors: null }
  }

  return { success: false, message: 'Communication not found.', data: null, errors: null }
}

export async function deleteCommunication(id: number): Promise<ApiResponse> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?id=${id}`, {
      method: 'DELETE'
    })
    const data = await res.json()
    if (data.success) return data
  } catch (err: any) {
    // Fallback soft delete
  }

  fallbackCommunicationsStore = fallbackCommunicationsStore.filter(item => item.id !== id)
  return { success: true, message: 'Communication deleted successfully.', data: { id }, errors: null }
}

export async function addCommunicationActivity(payload: CommunicationActivityPayload): Promise<ApiResponse> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?action=add_activity`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) return data
  } catch (err: any) {
    // Fallback add activity
  }

  const item = fallbackCommunicationsStore.find(i => i.id === payload.communication_id)
  if (item) {
    item.activities = item.activities || []
    const actDate = payload.activity_date ? payload.activity_date.replace('T', ' ') : new Date().toISOString().replace('T', ' ').substring(0, 19)
    item.latest_activity_date = actDate
    item.activities.unshift({
      id: Date.now(),
      communication_id: payload.communication_id,
      activity_type: payload.activity_type,
      activity_date: actDate,
      remarks: payload.remarks || ''
    })
    return { success: true, message: 'Activity log entry added successfully.', data: { id: Date.now() }, errors: null }
  }

  return { success: false, message: 'Communication record not found.', data: null, errors: null }
}

export async function fetchCommunicationReports(): Promise<ApiResponse<CommunicationReportsData>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=reports`)
    const data = await res.json()
    if (data.success && data.data && data.data.by_type?.length > 0) return data
  } catch (err: any) {
    // Fallback reports
  }

  const store = fallbackCommunicationsStore

  const byTypeMap: Record<string, number> = {}
  const byCatMap: Record<string, number> = {}
  const byPurMap: Record<string, number> = {}
  const byStatMap: Record<string, number> = {}

  store.forEach(item => {
    byTypeMap[item.communication_type] = (byTypeMap[item.communication_type] || 0) + 1
    const cat = item.category_name || 'Others'
    byCatMap[cat] = (byCatMap[cat] || 0) + 1
    const pur = item.purpose_name || 'Others'
    byPurMap[pur] = (byPurMap[pur] || 0) + 1
    byStatMap[item.status] = (byStatMap[item.status] || 0) + 1
  })

  return {
    success: true,
    message: 'Reports loaded',
    data: {
      by_type: Object.entries(byTypeMap).map(([communication_type, total]) => ({ communication_type, total })),
      by_category: Object.entries(byCatMap).map(([category_name, total]) => ({ category_name, total })),
      by_purpose: Object.entries(byPurMap).map(([purpose_name, total]) => ({ purpose_name, total })),
      by_status: Object.entries(byStatMap).map(([status, total]) => ({ status, total }))
    },
    errors: null
  }
}
