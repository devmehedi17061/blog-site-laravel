const BASE_URL = '/admin/api'

/**
 * Thrown for any non-2xx response so callers can branch on status and read
 * Laravel's field-level validation errors without re-parsing the body.
 */
export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function request(method, path, body = null) {
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken(),
  }

  if (body !== null) headers['Content-Type'] = 'application/json'

  let response
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      credentials: 'same-origin',
      body: body === null ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError('Cannot reach the server. Check that it is still running.', 0)
  }

  // The session expired behind our back — send the user back to the form.
  if (response.status === 401 || response.status === 419) {
    window.location.href = '/admin/login'
    throw new ApiError('Your session expired. Please sign in again.', response.status)
  }

  if (response.status === 204) return null

  const payload = await response.json().catch(() => ({}))

  // The account owes two-factor setup or a new password: the panel sends
  // its owner to My account, where both get done.
  if (response.status === 403 && ['two_factor_required', 'password_expired'].includes(payload.code)) {
    window.Alpine?.store('ui')?.owe(payload.code)
  }

  if (!response.ok) {
    throw new ApiError(
      payload.message || `Request failed with status ${response.status}`,
      response.status,
      payload.errors || {}
    )
  }

  return payload
}

/**
 * Multipart uploads go through their own path: the browser has to set the
 * Content-Type itself so it can add the multipart boundary.
 */
async function upload(path, formData) {
  let response
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      },
      credentials: 'same-origin',
      body: formData,
    })
  } catch {
    throw new ApiError('Upload failed — the server did not respond.', 0)
  }

  if (response.status === 401 || response.status === 419) {
    window.location.href = '/admin/login'
    throw new ApiError('Your session expired. Please sign in again.', response.status)
  }

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(
      payload.message || `Upload failed with status ${response.status}`,
      response.status,
      payload.errors || {}
    )
  }

  return payload
}

export const api = {
  upload,
  get: (path) => request('GET', path),
  post: (path, body = {}) => request('POST', path, body),
  put: (path, body = {}) => request('PUT', path, body),
  patch: (path, body = {}) => request('PATCH', path, body),
  // A body is optional, and used where a delete needs a decision with it
  // — "delete this category, and move its posts here".
  delete: (path, body = null) => request('DELETE', path, body),
}
