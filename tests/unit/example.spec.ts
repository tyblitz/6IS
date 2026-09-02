import { describe, expect, test } from 'vitest'
import { ModuleName } from '@/types/module'

describe('6IS System Platform Baseline', () => {
  test('ModuleName enum contains official platform modules', () => {
    expect(ModuleName.Dashboard).toBe('dashboard')
    expect(ModuleName.Inventory).toBe('inventory')
    expect(ModuleName.Communications).toBe('communications')
    expect(ModuleName.Calendar).toBe('calendar')
    expect(ModuleName.Accomplishments).toBe('accomplishments')
    expect(ModuleName.Administrator).toBe('administrator')
  })
})
