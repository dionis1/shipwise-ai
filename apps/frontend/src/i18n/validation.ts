export function validateFields(
  values: Record<string, string>,
  rules: Record<string, 'email' | 'name' | 'password' | 'currentPassword' | 'token'>,
): Record<string, string> {
  const errors: Record<string, string> = {}
  for (const [field, rule] of Object.entries(rules)) {
    const value = values[field] || '',
      bytes = new TextEncoder().encode(value).length
    if (rule === 'email' && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || value.length > 180))
      errors[field] = 'email_invalid'
    if (rule === 'name' && (Array.from(value.trim()).length < 2 || Array.from(value).length > 100))
      errors[field] = 'name_invalid'
    if (rule === 'password' && (bytes < 12 || bytes > 128)) errors[field] = 'password_invalid'
    if (rule === 'currentPassword' && (!value || bytes > 128))
      errors[field] = 'current_password_invalid'
    if (rule === 'token' && !/^[a-f0-9]{64}$/.test(value)) errors[field] = 'token_invalid'
  }
  return errors
}
