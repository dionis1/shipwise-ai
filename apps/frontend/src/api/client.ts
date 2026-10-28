import { i18n } from '../i18n'
import type { components } from './generated'
export type Profile = components['schemas']['Profile']
export type Staff = components['schemas']['Staff']
export type Role = components['schemas']['Role']
export type AuditEvent = components['schemas']['AuditEvent']
export type Dashboard = components['schemas']['Dashboard']
export type Department = components['schemas']['Department']
export type StaffPage = components['schemas']['StaffPage']
export type AuditPage = components['schemas']['AuditPage']
export type CustomerPage = components['schemas']['CustomerPage']
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code = 'server_error',
  ) {
    super(message)
  }
}
export async function api<T>(path: string, method = 'GET', data?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (method !== 'GET') {
    const response = await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' })
    if (!response.ok)
      throw new ApiError(
        'Unable to initialize a secure request.',
        response.status,
        response.status === 403 ? 'csrf_invalid' : 'server_error',
      )
    const csrf: components['schemas']['Csrf'] = await response.json()
    headers['X-CSRF-Token'] = csrf.token
    headers['Content-Type'] = 'application/json'
  }
  const response = await fetch('/api/v1' + path, {
    method,
    credentials: 'same-origin',
    headers,
    body: data === undefined ? undefined : JSON.stringify(data),
  })
  const body = await response.json().catch(() => ({}))
  if (!response.ok)
    throw new ApiError(
      body.detail || 'The request could not be completed.',
      response.status,
      body.code ||
        (
          { 401: 'authentication_required', 403: 'forbidden', 429: 'rate_limited' } as Record<
            number,
            string
          >
        )[response.status] ||
        'server_error',
    )
  return body as T
}

export function errorMessage(error: unknown): string {
  const code = error instanceof ApiError ? error.code : 'network_error'
  const key = 'errors.' + code
  return i18n.global.t(i18n.global.te(key) ? key : 'errors.server_error')
}
