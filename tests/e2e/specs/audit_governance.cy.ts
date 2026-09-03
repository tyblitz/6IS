// tests/e2e/specs/audit_governance.cy.ts
describe('6IS Core Governance & Audit Trail (Phase 4)', () => {
  beforeEach(() => {
    // Authenticate as Administrator (creates/asserts authentic audit session)
    cy.session('admin-session', () => {
      cy.visit('/login')
      cy.get('#username').type('Admin01')
      cy.get('#password').type('adminpassword01')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', '/home')
    })
  })

  it('navigates from administrator overview to audit logs view', () => {
    cy.visit('/administrator')
    cy.contains('Audit Logs').scrollIntoView().click({ force: true })
    cy.url().should('include', '/administrator/audit')
    cy.contains('h2', 'Audit Logs & System Activity').should('exist')
  })

  it('displays the filter toolbar with search, module, action, and date filters', () => {
    cy.visit('/administrator/audit')
    cy.get('#audit-search').should('exist')
    cy.get('#audit-module').should('exist')
    cy.get('#audit-action').should('exist')
    cy.get('#audit-date-from').should('exist')
    cy.get('#audit-date-to').should('exist')
    cy.contains('button', 'Apply Filters').should('exist')
    cy.contains('button', 'Reset Filters').should('exist')
  })

  it('renders the audit trail table with real audit records from platform mutations', () => {
    cy.visit('/administrator/audit')
    cy.get('.loading-state').should('not.exist')
    cy.get('.audit-table').should('exist')

    // Verify expected table columns
    cy.get('.audit-table th').should('contain', 'Timestamp')
    cy.get('.audit-table th').should('contain', 'Actor')
    cy.get('.audit-table th').should('contain', 'Action')
    cy.get('.audit-table th').should('contain', 'Module')
    cy.get('.audit-table th').should('contain', 'Target Entity')
    cy.get('.audit-table th').should('contain', 'Description')
    cy.get('.audit-table th').should('contain', 'Inspection')

    // Verify at least one real audit record is rendered
    cy.get('.audit-table tbody tr.audit-row').should('have.length.at.least', 1)
    cy.get('.audit-table tbody tr.audit-row').first().within(() => {
      cy.get('.timestamp-cell').should('not.be.empty')
      cy.get('.actor-cell').should('not.be.empty')
      cy.get('.badge-action').should('not.be.empty')
      cy.get('.module-tag').should('not.be.empty')
      cy.get('.btn-inspect').should('exist')
    })
  })

  it('filters and searches audit records by action and resets cleanly', () => {
    cy.visit('/administrator/audit')
    cy.get('.loading-state').should('not.exist')
    cy.get('.audit-table').should('exist')

    // Filter by action LOGIN
    cy.get('#audit-action').select('LOGIN')
    cy.contains('button', 'Apply Filters').click()
    cy.get('.loading-state').should('not.exist')

    // Table displays matching records with LOGIN badge
    cy.get('.audit-table tbody tr.audit-row').should('have.length.at.least', 1)
    cy.get('.audit-table tbody tr.audit-row').first().find('.badge-action').should('contain', 'LOGIN')

    // Reset filters restores full list
    cy.contains('button', 'Reset Filters').click()
    cy.get('.loading-state').should('not.exist')
    cy.get('#audit-action').should('have.value', '')
    cy.get('.audit-table tbody tr.audit-row').should('have.length.at.least', 1)
  })

  it('opens an audit record detail inspection modal in strictly read-only mode', () => {
    cy.visit('/administrator/audit')
    cy.get('.loading-state').should('not.exist')
    cy.get('.audit-table').should('exist')

    // Open detail inspection modal on first record
    cy.get('.btn-inspect').first().scrollIntoView().click({ force: true })
    cy.get('.audit-detail-modal').should('be.visible')

    // Verify inspection modal contents & read-only guarantee
    cy.contains('Immutable audit record').should('exist')
    cy.contains('Previous State').should('exist')
    cy.contains('Resulting State').should('exist')
    cy.get('.audit-detail-modal').find('button').contains('Delete').should('not.exist')
    cy.get('.audit-detail-modal').find('button').contains('Edit').should('not.exist')

    // Close detail modal
    cy.get('.modal-close-btn').click({ force: true })
    cy.get('.audit-detail-modal').should('not.exist')
  })

  it('displays empty state when filter criteria produce zero results and allows clearing', () => {
    cy.visit('/administrator/audit')
    cy.get('.loading-state').should('not.exist')

    // Search for impossible term
    cy.get('#audit-search').clear().type('__nonexistent_search_query_term_xyz_12345__')
    cy.contains('button', 'Apply Filters').click()
    cy.get('.loading-state').should('not.exist')

    // Empty state should be visible
    cy.get('.empty-state').should('exist')
    cy.contains('No Audit Records Found').should('exist')

    // Clicking Clear Filters restores the table
    cy.get('.empty-state').contains('Clear Filters').click()
    cy.get('.loading-state').should('not.exist')
    cy.get('.audit-table').should('exist')
  })

  it('strictly prohibits mutation actions on audit records (no delete/edit/purge buttons)', () => {
    cy.visit('/administrator/audit')
    cy.get('.loading-state').should('not.exist')
    cy.get('button').contains('Delete').should('not.exist')
    cy.get('button').contains('Purge').should('not.exist')
    cy.get('button').contains('Clear All').should('not.exist')
    cy.get('button').contains('Edit').should('not.exist')
  })
})
