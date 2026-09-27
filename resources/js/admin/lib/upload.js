import { ApiError } from '../api.js'

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

/**
 * Uploads through XMLHttpRequest rather than fetch, because fetch cannot
 * report progress — and a 10 MB upload with no feedback looks like a hang.
 */
export function uploadWithProgress(path, formData, onProgress) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()

    xhr.open('POST', `/admin/api${path}`)
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken())
    xhr.withCredentials = true

    xhr.upload.addEventListener('progress', (event) => {
      if (event.lengthComputable) {
        onProgress?.(Math.round((event.loaded / event.total) * 100))
      }
    })

    xhr.addEventListener('load', () => {
      if (xhr.status === 401 || xhr.status === 419) {
        window.location.href = '/admin/login'
        return reject(new ApiError('Your session expired. Please sign in again.', xhr.status))
      }

      let payload = {}
      try {
        payload = JSON.parse(xhr.responseText)
      } catch {
        /* a non-JSON body means something upstream failed */
      }

      if (xhr.status >= 200 && xhr.status < 300) return resolve(payload)

      reject(new ApiError(
        payload.message || `Upload failed with status ${xhr.status}`,
        xhr.status,
        payload.errors || {}
      ))
    })

    xhr.addEventListener('error', () => reject(new ApiError('Upload failed — the server did not respond.', 0)))
    xhr.addEventListener('abort', () => reject(new ApiError('Upload cancelled.', 0)))

    xhr.send(formData)
  })
}
