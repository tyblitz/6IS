// tests/unit/auditService.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { fetchAuditLogs } from '@/services/auditService'
import { setStoredCsrfToken } from '@/services/authService'
import type { AuditApiResponse } from '@/types/audit'

const mockAuditResponse: AuditApiResponse = {
  success: true,
  message: 'Audit logs retrieved.',
  data: [
    {
      id: 1,
      user_id: 1,
      username: 'Admin01',
      full_name: 'Administrator Account',
      action: 'LOGIN',
      module_key: 'auth',
      entity_type: 'user',
      entity_id: '1',
      description: 'User Admin01 logged in successfully.',
      old_values: null,
      new_values: { username: 'Admin01' },
      ip_address: '127.0.0.1',
      user_agent: 'TestAgent/1.0',
      created_at: '2026-09-03 10:00:00'
    }
  ],
  pagination: {
    page: 1,
    limit: 25,
    total: 1,
    total_pages: 1
  }
}

describe('auditService Frontend API Client', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    setStoredCsrfToken(null)
  })

  it('fetchAuditLogs() calls audit endpoint with default params and credentials: include', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockAuditResponse
    })
    global.fetch = fetchMock

    const res = await fetchAuditLogs()
    expect(fetchMock).toHaveBeenCalled()
    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toContain('/backend/api/core/audit/index.php')
    expect(options.method).toBe('GET')
    expect(options.credentials).toBe('include')
    expect(res.success).toBe(true)
    expect(res.data.length).toBe(1)
    expect(res.data[0].action).toBe('LOGIN')
  })

  it('fetchAuditLogs() correctly constructs query parameters for filters and pagination', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockAuditResponse
    })
    global.fetch = fetchMock

    await fetchAuditLogs({
      page: 2,
      limit: 10,
      module_key: 'users',
      action: 'CREATE',
      search: 'Admin01',
      date_from: '2026-09-01',
      date_to: '2026-09-03'
    })

    const [url] = fetchMock.mock.calls[0]
    expect(url).toContain('page=2')
    expect(url).toContain('limit=10')
    expect(url).toContain('module_key=users')
    expect(url).toContain('action=CREATE')
    expect(url).toContain('search=Admin01')
    expect(url).toContain('date_from=2026-09-01')
    expect(url).toContain('date_to=2026-09-03')
  })

  it('fetchAuditLogs() attaches X-CSRF-Token header when CSRF token is available', async () => {
    setStoredCsrfToken('test-csrf-token-xyz-123')

    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => mockAuditResponse
    })
    global.fetch = fetchMock

    await fetchAuditLogs()
    const [, options] = fetchMock.mock.calls[0]
    expect(options.headers['X-CSRF-Token']).toBe('test-csrf-token-xyz-123')
  })

  it('fetchAuditLogs() throws error with server message on non-ok HTTP response', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 403,
      json: async () => ({
        success: false,
        message: 'Permission denied for audit.view'
      })
    })
    global.fetch = fetchMock

    await expect(fetchAuditLogs()).rejects.toThrow('Permission denied for audit.view')
  })
})
