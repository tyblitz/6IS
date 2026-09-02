// tests/e2e/specs/roles_permissions.cy.ts
describe('6IS Roles & Permissions Management (Phase 2)', () => {
  beforeEach(() => {
    // Authenticate as Administrator
    cy.session('admin-session', () => {
      cy.visit('/login')
      cy.get('#username').type('Admin01')
      cy.get('#password').type('Admin123!')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', '/home')
    })
  })

  it('navigates from administrator overview to role & permission management', () => {
    cy.visit('/administrator')
    cy.contains('Role & Permission Management').should('be.visible').click()
    cy.url().should('include', '/administrator/roles')
    cy.contains('h2', 'Role & Permission Management').should('be.visible')
  })

  it('displays registered system roles with system badges', () => {
    cy.visit('/administrator/roles')
    cy.contains('td', 'Administrator').should('be.visible')
    cy.contains('td', 'User').should('be.visible')
    cy.get('.badge-system').should('have.length.at.least', 2)
  })

  it('can open permission matrix for Administrator role', () => {
    cy.visit('/administrator/roles')
    cy.contains('tr', 'Administrator').find('button').contains('Permissions').click()
    cy.get('.modal-dialog-lg').should('be.visible')
    cy.contains('Role Permissions: Administrator').should('be.visible')
    cy.contains('System Module').should('be.visible')
    cy.get('.matrix-table').should('be.visible')
    cy.contains('button', 'Close').click()
    cy.get('.modal-dialog-lg').should('not.exist')
  })
})
