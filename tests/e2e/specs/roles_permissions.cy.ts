// tests/e2e/specs/roles_permissions.cy.ts
describe('6IS Roles & Permissions Management (Phase 2)', () => {
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

  it('navigates from administrator overview to role & permission management', () => {
    cy.visit('/administrator')
    cy.contains('Role & Permission Management').scrollIntoView().click({ force: true })
    cy.url().should('include', '/administrator/roles')
    cy.contains('h2', 'Role & Permission Management').should('exist')
  })

  it('displays registered system roles with system badges', () => {
    cy.visit('/administrator/roles')
    cy.contains('td', 'Administrator').should('exist')
    cy.contains('td', 'User').should('exist')
    cy.get('.badge-system').should('have.length.at.least', 2)
  })

  it('can open permission matrix for Administrator role', () => {
    cy.visit('/administrator/roles')
    cy.contains('tr', 'Administrator').find('button').contains('Permissions').click({ force: true })
    cy.get('.modal-dialog-lg').should('exist')
    cy.contains('Role Permissions: Administrator').should('exist')
    cy.get('.matrix-table').should('exist')
    cy.contains('button', 'Close').click({ force: true })
    cy.get('.modal-dialog-lg').should('not.exist')
  })
})
