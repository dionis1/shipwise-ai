export function canonicalMeasurement(
  value: string,
  decimals: number,
  maximum: number,
): number | null {
  const normalized = value.trim().replace(',', '.')
  if (!new RegExp('^\\d{1,6}(?:\\.\\d{1,' + decimals + '})?$').test(normalized)) return null
  const [whole, fraction = ''] = normalized.split('.')
  const result = Number(whole) * 10 ** decimals + Number(fraction.padEnd(decimals, '0'))
  return Number.isSafeInteger(result) && result > 0 && result <= maximum ? result : null
}
