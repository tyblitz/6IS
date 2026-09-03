// tests/e2e/specs/audit_governance.cy.ts
describe('6IS Core Governance & Audit Trail (Phase 4)', () => {
  beforeEach(() => {
    // Authenticate as Administrator
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

  it('displays audit trail table and can open detail inspection modal', () => {
    cy.visit('/administrator/audit')
    cy.get('.audit-table').should('exist')
    cy.get('.audit-table th').should('contain', 'Timestamp')
    cy.get('.audit-table th').should('contain', 'Actor')
    cy.get('.audit-table th').should('contain', 'Action')
    cy.get('.audit-table th').should('contain', 'Module')

    // Click Details on the first row if rows exist
    cy.get('body').then(($body) => {
      if ($body.find('.btn-inspect').length > 0) {
        cy.get('.btn-inspect').first().click({ force: true })
        cy.get('.audit-detail-modal').should('exist')
        cy.contains('Immutable audit record').should('exist')
        cy.contains('Previous State').should('exist')
        cy.contains('Resulting State').should('exist')
        cy.get('.modal-close-btn').click({ force: true })
        cy.get('.audit-detail-modal').should('not.exist')
      }
    })
  })

  it('strictly prohibits mutation actions on audit records (no delete/edit/purge buttons)', () => {
    cy.visit('/administrator/audit')
    cy.get('button').contains('Delete').should('not.exist')
    cy.get('button').contains('Purge').should('not.exist')
    cy.get('button').contains('Clear').should('not.exist')
    cy.get('button').contains('Edit').should('not.exist')
  })
})
