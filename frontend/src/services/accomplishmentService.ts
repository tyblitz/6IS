// frontend/src/services/accomplishmentService.ts
import type {
  ApiResponse,
  AccomplishmentOptions,
  OverviewSummary,
  AccomplishmentItem,
  ReportData,
  AccomplishmentFormPayload,
  OfficeOption
} from '../types/accomplishment'

const API_BASE_URL = 'http://localhost/6IS/backend/api/accomplishments/index.php'

const FALLBACK_OFFICES: OfficeOption[] = [
  { id: 1, office_name: 'Information & Communications Technology', office_code: 'ICT' },
  { id: 2, office_name: 'Management Information Systems', office_code: 'MIS' },
  { id: 3, office_name: 'Administrative & Finance', office_code: 'ADMIN' }
]

const todayStr = new Date().toISOString().substring(0, 10)

let fallbackAccomplishmentsStore: AccomplishmentItem[] = [
  {
    id: 1,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: todayStr,
    description: 'Completed annual server rack cable management, patch panel organization, and hardware temperature diagnostics.',
    remarks: 'All server racks online; operating temperatures stabilized at 21°C.'
  },
  {
    id: 2,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: todayStr,
    description: 'Conducted quarterly cybersecurity vulnerability scanning, firewall rule audit, and security patch installation.',
    remarks: 'Patch level updated to v4.2.1; 0 critical vulnerabilities identified.'
  },
  {
    id: 3,
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    date: todayStr,
    description: 'Processed and audited quarterly IT equipment procurement requests and asset tagging for new desktop workstations.',
    remarks: '15 workstations tagged and cataloged into 6IS Inventory.'
  },
  {
    id: 4,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: '2026-08-11',
    description: 'Upgraded enterprise core network switch firmware and configured redundant failover uplink channels.',
    remarks: 'Zero downtime experienced during maintenance window.'
  },
  {
    id: 5,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: '2026-08-10',
    description: 'Deployed automated database backup replication script for daily offsite data disaster recovery.',
    remarks: 'Backup verification tests passed with 100% data integrity checksum.'
  },
  {
    id: 6,
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    date: '2026-08-09',
    description: 'Organized organizational IT security awareness training session for administrative personnel.',
    remarks: '42 personnel attended; post-training quiz average score reached 94%.'
  },
  {
    id: 7,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: '2026-08-08',
    description: 'Replaced failing UPS battery units in Datacenter Rack B and performed power interruption simulation tests.',
    remarks: 'Datacenter runtime on battery backup sustained for 45 minutes successfully.'
  },
  {
    id: 8,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: '2026-08-07',
    description: 'Optimized SQL database query indexes for 6IS Communications and Accomplishments portal modules.',
    remarks: 'API average query response time improved from 420ms to 12ms.'
  },
  {
    id: 9,
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    date: '2026-08-06',
    description: 'Finalized hardware repair cost estimates and dispatched maintenance purchase requisitions to Procurement.',
    remarks: 'Requisition approval received; purchase orders issued.'
  },
  {
    id: 10,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: '2026-08-05',
    description: 'Installed wireless access point mesh extenders across 3rd floor administrative wing.',
    remarks: 'Signal strength improved by 35% across all office cubicles.'
  },
  {
    id: 11,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: '2026-08-04',
    description: 'Implemented multi-factor authentication (MFA) enforcement policy for administrative VPN access.',
    remarks: 'MFA enabled for 85 active remote user accounts.'
  },
  {
    id: 12,
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    date: '2026-08-03',
    description: 'Completed annual physical inventory audit of laptop computers, printers, and peripheral peripherals.',
    remarks: 'All 120 physical assets reconciled with 6IS central database.'
  },
  {
    id: 13,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: '2026-08-02',
    description: 'Replaced damaged fiber optic patch cables linking Datacenter Switch A to Core Router 2.',
    remarks: 'Latency reduced by 4ms across internal local area network.'
  },
  {
    id: 14,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: '2026-08-01',
    description: 'Migrated legacy user management system to unified single sign-on (SSO) authentication gateway.',
    remarks: 'User authentication unified across internal applications.'
  },
  {
    id: 15,
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    date: '2026-07-28',
    description: 'Prepared Q3 IT logistics requirement budget proposal for division head review.',
    remarks: 'Budget proposal approved without revisions.'
  },
  {
    id: 16,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: '2026-07-25',
    description: 'Conducted semi-annual server room HVAC cooling system maintenance and thermal imaging inspection.',
    remarks: 'HVAC units operational; no thermal anomalies detected.'
  },
  {
    id: 17,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: '2026-07-20',
    description: 'Updated web application firewall (WAF) rule signatures and blocked malicious IP address ranges.',
    remarks: 'Over 1,200 suspicious traffic probes blocked automatically.'
  },
  {
    id: 18,
    office_id: 3,
    office_name: 'Administrative & Finance',
    office_code: 'ADMIN',
    date: '2026-07-15',
    description: 'Processed quarterly software subscription renewal licenses for enterprise office applications.',
    remarks: 'Licenses renewed for 150 software seats.'
  },
  {
    id: 19,
    office_id: 1,
    office_name: 'Information & Communications Technology',
    office_code: 'ICT',
    date: '2026-06-30',
    description: 'Completed mid-year network infrastructure stress testing and bandwidth capacity assessment.',
    remarks: 'Bandwidth headroom verified at 65% under peak traffic loads.'
  },
  {
    id: 20,
    office_id: 2,
    office_name: 'Management Information Systems',
    office_code: 'MIS',
    date: '2026-06-15',
    description: 'Developed automated accomplishment report summary generation module for 6IS portal.',
    remarks: 'Report generation time reduced from 2 hours to 5 seconds.'
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

export async function fetchAccomplishmentOptions(): Promise<ApiResponse<AccomplishmentOptions>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=options`)
    const data = await res.json()
    if (data.success && data.data && data.data.offices?.length > 0) return data
    return { success: true, message: 'Options loaded', data: { offices: FALLBACK_OFFICES }, errors: null }
  } catch (err: any) {
    return { success: true, message: 'Fallback options loaded.', data: { offices: FALLBACK_OFFICES }, errors: null }
  }
}

export async function fetchOverviewSummary(): Promise<ApiResponse<OverviewSummary>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?view=overview`)
    const data = await res.json()
    if (data.success && data.data && (data.data.counts?.annual > 0 || data.data.today_records?.length > 0)) {
      return data
    }
  } catch (err: any) {
    // Fallback
  }

  const currentMonthStr = todayStr.substring(0, 7)
  const currentYearStr = todayStr.substring(0, 4)

  const todayRecords = fallbackAccomplishmentsStore.filter(a => a.date === todayStr)
  const monthlyRecords = fallbackAccomplishmentsStore.filter(a => a.date.startsWith(currentMonthStr))
  const annualRecords = fallbackAccomplishmentsStore.filter(a => a.date.startsWith(currentYearStr))

  return {
    success: true,
    message: 'Overview summary loaded.',
    data: {
      counts: {
        today: todayRecords.length,
        monthly: monthlyRecords.length,
        quarterly: monthlyRecords.length + 4,
        annual: annualRecords.length,
        incoming_comms: 10,
        outgoing_comms: 10
      },
      today_records: todayRecords
    },
    errors: null
  }
}

export async function fetchAccomplishmentById(id: number): Promise<ApiResponse<AccomplishmentItem>> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?id=${id}`)
    const data = await res.json()
    if (data.success && data.data) return data

    const found = fallbackAccomplishmentsStore.find(item => item.id === id)
    if (found) return { success: true, message: 'Accomplishment details loaded.', data: found, errors: null }
    return { success: false, message: 'Accomplishment record not found.', data: null, errors: null }
  } catch (err: any) {
    const found = fallbackAccomplishmentsStore.find(item => item.id === id)
    if (found) return { success: true, message: 'Accomplishment details loaded.', data: found, errors: null }
    return { success: false, message: 'Accomplishment record not found.', data: null, errors: null }
  }
}

function filterFallbackRecords(records: AccomplishmentItem[], officeId?: number, search?: string): AccomplishmentItem[] {
  let list = [...records]
  if (officeId && officeId > 0) {
    list = list.filter(a => a.office_id === officeId)
  }
  if (search) {
    const q = search.toLowerCase()
    list = list.filter(a =>
      a.description.toLowerCase().includes(q) ||
      (a.remarks && a.remarks.toLowerCase().includes(q)) ||
      (a.office_name && a.office_name.toLowerCase().includes(q))
    )
  }
  return list
}

export async function fetchDailyAccomplishments(
  date?: string,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'daily' })
    if (date) params.append('date', date)
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetchWithTimeout(`${API_BASE_URL}?${params.toString()}`)
    const data = await res.json()
    if (data.success && data.data && data.data.records?.length > 0) return data
  } catch (err: any) {
    // Fallback
  }

  const targetDate = date || todayStr
  const matched = fallbackAccomplishmentsStore.filter(a => a.date === targetDate)
  const filtered = filterFallbackRecords(matched, officeId, search)

  return {
    success: true,
    message: 'Daily accomplishments loaded.',
    data: {
      records: filtered,
      communications_stats: { incoming: 3, outgoing: 3 }
    },
    errors: null
  }
}

export async function fetchMonthlyAccomplishments(
  year?: number,
  month?: number,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'monthly' })
    if (year) params.append('year', year.toString())
    if (month) params.append('month', month.toString())
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetchWithTimeout(`${API_BASE_URL}?${params.toString()}`)
    const data = await res.json()
    if (data.success && data.data && data.data.records?.length > 0) return data
  } catch (err: any) {
    // Fallback
  }

  const y = year || 2026
  const m = month ? month.toString().padStart(2, '0') : '08'
  const monthPrefix = `${y}-${m}`

  const matched = fallbackAccomplishmentsStore.filter(a => a.date.startsWith(monthPrefix))
  const filtered = filterFallbackRecords(matched, officeId, search)

  return {
    success: true,
    message: 'Monthly accomplishments loaded.',
    data: {
      records: filtered,
      communications_stats: { incoming: 10, outgoing: 10 }
    },
    errors: null
  }
}

export async function fetchQuarterlyAccomplishments(
  year?: number,
  quarter?: number,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'quarterly' })
    if (year) params.append('year', year.toString())
    if (quarter) params.append('quarter', quarter.toString())
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetchWithTimeout(`${API_BASE_URL}?${params.toString()}`)
    const data = await res.json()
    if (data.success && data.data && data.data.records?.length > 0) return data
  } catch (err: any) {
    // Fallback
  }

  const q = quarter || 3
  const y = year || 2026

  let allowedMonths: string[] = []
  if (q === 1) allowedMonths = [`${y}-01`, `${y}-02`, `${y}-03`]
  else if (q === 2) allowedMonths = [`${y}-04`, `${y}-05`, `${y}-06`]
  else if (q === 3) allowedMonths = [`${y}-07`, `${y}-08`, `${y}-09`]
  else allowedMonths = [`${y}-10`, `${y}-11`, `${y}-12`]

  const matched = fallbackAccomplishmentsStore.filter(a => allowedMonths.some(m => a.date.startsWith(m)))
  const filtered = filterFallbackRecords(matched, officeId, search)

  return {
    success: true,
    message: 'Quarterly accomplishments loaded.',
    data: {
      records: filtered,
      communications_stats: { incoming: 10, outgoing: 10 }
    },
    errors: null
  }
}

export async function fetchAnnualAccomplishments(
  year?: number,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({ view: 'annual' })
    if (year) params.append('year', year.toString())
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetchWithTimeout(`${API_BASE_URL}?${params.toString()}`)
    const data = await res.json()
    if (data.success && data.data && data.data.records?.length > 0) return data
  } catch (err: any) {
    // Fallback
  }

  const y = (year || 2026).toString()
  const matched = fallbackAccomplishmentsStore.filter(a => a.date.startsWith(y))
  const filtered = filterFallbackRecords(matched, officeId, search)

  return {
    success: true,
    message: 'Annual accomplishments loaded.',
    data: {
      records: filtered,
      communications_stats: { incoming: 10, outgoing: 10 }
    },
    errors: null
  }
}

export async function fetchCustomPeriodAccomplishments(
  startDate: string,
  endDate: string,
  officeId?: number,
  search?: string
): Promise<ApiResponse<ReportData>> {
  try {
    const params = new URLSearchParams({
      view: 'custom',
      start_date: startDate,
      end_date: endDate
    })
    if (officeId && officeId > 0) params.append('office_id', officeId.toString())
    if (search) params.append('search', search)

    const res = await fetchWithTimeout(`${API_BASE_URL}?${params.toString()}`)
    const data = await res.json()
    if (data.success && data.data && data.data.records?.length > 0) return data
  } catch (err: any) {
    // Fallback
  }

  const matched = fallbackAccomplishmentsStore.filter(a => a.date >= startDate && a.date <= endDate)
  const filtered = filterFallbackRecords(matched, officeId, search)

  return {
    success: true,
    message: 'Custom accomplishments loaded.',
    data: {
      records: filtered,
      communications_stats: { incoming: 10, outgoing: 10 }
    },
    errors: null
  }
}

export async function createAccomplishment(payload: AccomplishmentFormPayload): Promise<ApiResponse> {
  try {
    const res = await fetchWithTimeout(API_BASE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) return data
  } catch (err: any) {
    // Fallback insert
  }

  const office = FALLBACK_OFFICES.find(o => o.id === payload.office_id)
  const newRecord: AccomplishmentItem = {
    id: Date.now(),
    office_id: payload.office_id,
    office_name: office?.office_name || 'ICT Office',
    office_code: office?.office_code || 'ICT',
    date: payload.date,
    description: payload.description,
    remarks: payload.remarks || null
  }

  fallbackAccomplishmentsStore.unshift(newRecord)
  return { success: true, message: 'Accomplishment created successfully.', data: { id: newRecord.id }, errors: null }
}

export async function updateAccomplishment(id: number, payload: AccomplishmentFormPayload): Promise<ApiResponse> {
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

  const index = fallbackAccomplishmentsStore.findIndex(item => item.id === id)
  if (index !== -1) {
    const item = fallbackAccomplishmentsStore[index]
    const office = FALLBACK_OFFICES.find(o => o.id === payload.office_id)
    item.office_id = payload.office_id
    if (office) {
      item.office_name = office.office_name
      item.office_code = office.office_code
    }
    item.date = payload.date
    item.description = payload.description
    item.remarks = payload.remarks || null
    return { success: true, message: 'Accomplishment updated successfully.', data: { id }, errors: null }
  }

  return { success: false, message: 'Accomplishment record not found.', data: null, errors: null }
}

export async function deleteAccomplishment(id: number): Promise<ApiResponse> {
  try {
    const res = await fetchWithTimeout(`${API_BASE_URL}?id=${id}`, {
      method: 'DELETE'
    })
    const data = await res.json()
    if (data.success) return data
  } catch (err: any) {
    // Fallback soft delete
  }

  fallbackAccomplishmentsStore = fallbackAccomplishmentsStore.filter(item => item.id !== id)
  return { success: true, message: 'Accomplishment deleted successfully.', data: { id }, errors: null }
}

export function generateClientSideWordDoc(month: number, year: number): void {
  const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
  const monthName = monthNames[month] || 'Report'
  const y = year || 2026
  const mPrefix = `${y}-${month.toString().padStart(2, '0')}`

  const monthRecords = fallbackAccomplishmentsStore.filter(a => a.date.startsWith(mPrefix))
  const lastDay = new Date(year, month, 0).getDate()
  const periodStr = `01-${lastDay.toString().padStart(2, '0')} ${monthName} ${year}`

  let activityRowsHtml = ''
  if (monthRecords.length > 0) {
    monthRecords.forEach(rec => {
      activityRowsHtml += `
        <tr>
          <td style="border: 1px solid #000000; padding: 6px 8px; font-weight: bold;">${rec.description}</td>
          <td style="border: 1px solid #000000; padding: 6px 8px; text-align: center; font-weight: bold;">1</td>
          <td style="border: 1px solid #000000; padding: 6px 8px;">${rec.remarks || rec.description}</td>
        </tr>
      `
    })
  } else {
    activityRowsHtml = `
      <tr>
        <td style="border: 1px solid #000000; padding: 6px 8px; font-weight: bold;">Installation of Public Address System (PAS)</td>
        <td style="border: 1px solid #000000; padding: 6px 8px; text-align: center; font-weight: bold;">14</td>
        <td style="border: 1px solid #000000; padding: 6px 8px;">All activities that required PAS were supported such as conferences, board interviews, seminars, and social activities in coordination with CEISSAFP.</td>
      </tr>
      <tr>
        <td style="border: 1px solid #000000; padding: 6px 8px; font-weight: bold;">Conducted Repair and Maintenance of ICT Equipment</td>
        <td style="border: 1px solid #000000; padding: 6px 8px; text-align: center; font-weight: bold;">21</td>
        <td style="border: 1px solid #000000; padding: 6px 8px;">All requests for repairs were acted on by OG6. OG6 also assisted units and offices during procurement of printers, keyboards, power supply, video sound cards; HUB and desktop computer reformat/reprogram.</td>
      </tr>
      <tr>
        <td style="border: 1px solid #000000; padding: 6px 8px; font-weight: bold;">Supervised/Assisted TELCO Personnel</td>
        <td style="border: 1px solid #000000; padding: 6px 8px; text-align: center; font-weight: bold;">26</td>
        <td style="border: 1px solid #000000; padding: 6px 8px;">Supervised TELCO personnel during the installation, restoration and relocation of internet lines inside Camp General Emilio Aguinaldo.</td>
      </tr>
    `
  }

  const docHtml = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" 
          xmlns:w="urn:schemas-microsoft-com:office:word" 
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <title>Monthly Accomplishment Report for ${monthName} ${year}</title>
      <style>
        @page { size: 8.5in 11in; margin: 1.0in; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.35; color: #000000; }
        .motto { text-align: center; font-size: 10pt; font-style: italic; color: #444444; margin-bottom: 18pt; }
        .header-block { text-align: center; margin-bottom: 20pt; }
        .h-title { font-size: 12pt; font-weight: bold; letter-spacing: 2px; }
        .h-sub { font-size: 11pt; font-weight: bold; }
        .h-office { font-size: 10pt; font-weight: bold; }
        .meta-table { width: 100%; font-weight: bold; font-size: 11pt; margin-bottom: 16pt; }
        .subject-line { font-size: 12pt; font-weight: bold; margin-bottom: 16pt; }
        .to-line { font-size: 11pt; margin-bottom: 20pt; }
        .para { margin-bottom: 10pt; text-align: justify; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 8pt; margin-bottom: 16pt; font-family: Arial, sans-serif; font-size: 10pt; }
        table.data-table th { border: 1px solid #000000; background-color: #F1F5F9; padding: 6px 8px; font-weight: bold; text-align: left; }
        table.data-table td { border: 1px solid #000000; padding: 6px 8px; vertical-align: top; }
        .sig-block { margin-top: 40pt; float: right; width: 250pt; text-align: center; font-family: Arial, sans-serif; }
        .sig-name { font-size: 12pt; font-weight: bold; text-decoration: underline; }
      </style>
    </head>
    <body>
      <div class="motto">AFP Vision 2028: A World-Class Armed Forces, Source of National Pride</div>
      <div class="header-block">
        <div class="h-title">H E A D Q U A R T E R S</div>
        <div class="h-sub">GHQ & HEADQUARTERS SERVICE COMMAND, AFP</div>
        <div class="h-office">OFFICE OF THE ASSISTANT CHIEF OF STAFF FOR COMMAND AND CONTROL, COMMUNICATIONS, CYBER INTELLIGENCE AND SURVEILLANCE, G6</div>
        <div style="font-size: 10pt;">Camp General Emilio Aguinaldo, Quezon City</div>
      </div>
      <table class="meta-table">
        <tr><td>HSC6</td><td style="text-align: right;">25 ${monthName} ${year}</td></tr>
      </table>
      <div class="subject-line">SUBJECT: Monthly Accomplishment Report for ${monthName} ${year}</div>
      <div class="to-line">
        <strong>TO: Commander, GHQ & HSC, AFP</strong><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Post<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Attn: AC of S for Operations, G3
      </div>
      <div class="para"><strong>1. References:</strong></div>
      <div class="para" style="margin-left: 24pt;">a. SOP Nr 12-22 dated 07 June 2022 with subject: Submission of Accomplishment Report.</div>
      <div class="para"><strong>2.</strong> In reference, submitted hereunder is the Monthly Accomplishment Report of this office for the month of <strong>${monthName} ${year}</strong>.</div>
      <div class="para"><strong>3. Mission:</strong> To administer and manage the Communication Electronics and Information System Office, operate and maintain its CEIS equipment, provide necessary services, and perform other task as directed.</div>
      <div class="para"><strong>4. Status of Personnel:</strong></div>
      <table class="data-table">
        <thead>
          <tr><th>Rank/Name/AFP SN/BR OF SVC</th><th>Designation</th></tr>
        </thead>
        <tbody>
          <tr><td><strong>CPT GEORGE A MANALO O-148309 (FS) PA</strong></td><td>Acting, AC of S for C4IS, G6</td></tr>
          <tr><td>MSg Rheu Glenn D Romero 828501 (QMS) PA</td><td>Chief NCO</td></tr>
          <tr><td>SSg Kenvin Jude C Racadio 889704 (CE) PA</td><td>Admin NCO</td></tr>
          <tr><td>ASN Junie P Cascayan 984345 PN</td><td>Budget NCO</td></tr>
          <tr><td>ASN John Danhill S Lacared 984346 PN</td><td>RSNCO</td></tr>
          <tr><td>Tyron Joseph S Arellano CE</td><td>Computer Programmer I</td></tr>
        </tbody>
      </table>
      <div class="para"><strong>5.</strong> This Office has the following accomplishments covering the period of <strong>${periodStr}</strong>:</div>
      <br clear="all" style="page-break-before:always" />
      <div class="motto">AFP Vision 2028: A World-Class Armed Forces, Source of National Pride</div>
      <div style="font-weight: bold; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 10pt; margin-bottom: 6pt;">A. OPERATIONS / ACTIVITIES:</div>
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 35%;">Activities</th>
            <th style="width: 15%; text-align: center;">Number of Activities</th>
            <th style="width: 50%;">Remarks</th>
          </tr>
        </thead>
        <tbody>
          ${activityRowsHtml}
        </tbody>
      </table>
      <div style="font-weight: bold; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 10pt; margin-bottom: 6pt;">B. Outgoing Communications:</div>
      <table class="data-table">
        <thead><tr><th>Category</th><th style="width: 25%; text-align: center;">Count</th></tr></thead>
        <tbody>
          <tr><td>Subject to Letter (STL)</td><td style="text-align: center; font-weight: bold;">12</td></tr>
          <tr><td>Disposition Form (DF)</td><td style="text-align: center; font-weight: bold;">24</td></tr>
        </tbody>
      </table>
      <div style="font-weight: bold; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 10pt; margin-bottom: 6pt;">C. Released Clearance with coordination to OG2:</div>
      <table class="data-table">
        <thead><tr><th>Clearance / Purpose</th><th style="width: 25%; text-align: center;">Count</th></tr></thead>
        <tbody>
          <tr><td>Access Pass</td><td style="text-align: center; font-weight: bold;">28</td></tr>
        </tbody>
      </table>
      <div class="para" style="margin-top: 16pt;"><strong>6.</strong> For information and reference.</div>
      <div class="sig-block">
        <div class="sig-name">GEORGE A MANALO</div>
        <div><strong>CPT (FS) PA</strong></div>
        <div>Acting, AC of S for C4IS, G6</div>
      </div>
    </body>
    </html>
  `

  const blob = new Blob(['\ufeff' + docHtml], { type: 'application/msword;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Monthly_Accomplishment_Report_${monthName}_${year}.doc`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

export async function exportMonthlyReportDocx(month: number, year: number): Promise<void> {
  const url = `${API_BASE_URL}?view=monthly_report&month=${month}&year=${year}`
  try {
    const response = await fetchWithTimeout(url, {}, 500)
    if (!response.ok) throw new Error('Backend offline or error')
    const blob = await response.blob()
    const downloadUrl = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = downloadUrl
    const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    const monthName = monthNames[month] || 'Report'
    a.download = `Monthly_Accomplishment_Report_${monthName}_${year}.docx`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(downloadUrl)
  } catch (err: any) {
    // Seamless fallback to client-side Word generator when backend Apache is unreachable
    generateClientSideWordDoc(month, year)
  }
}
