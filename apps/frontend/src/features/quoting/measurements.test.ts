import { describe, expect, it } from 'vitest'
import { canonicalMeasurement } from './measurements'
describe('canonical measurement conversion', () => {
  it('converts decimals to exact integer grams and millimeters', () => {
    expect(canonicalMeasurement('1.001', 3, 70000)).toBe(1001)
    expect(canonicalMeasurement('0,1', 3, 70000)).toBe(100)
    expect(canonicalMeasurement('30.1', 1, 2000)).toBe(301)
    expect(canonicalMeasurement('70', 3, 70000)).toBe(70000)
  })
  it.each(['0', '-1', 'NaN', '1e2', '1.0001', '70.001', '', '1,2,3'])(
    'rejects invalid input %s without rounding it silently',
    (value) => {
      expect(canonicalMeasurement(value, 3, 70000)).toBeNull()
    },
  )
})
