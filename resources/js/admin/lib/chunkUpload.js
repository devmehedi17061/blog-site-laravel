import { ApiError } from '../api.js'

/** Matches App\Support\LessonVideos::CHUNK_BYTES. */
export const CHUNK_BYTES = 5 * 1024 * 1024

const TRIES = 3

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

function uploadId() {
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  return Array.from(bytes, (b) => b.toString(36).padStart(2, '0')).join('').replace(/[^a-z0-9]/gi, '').slice(0, 32)
}

/**
 * Sends a big file in pieces, one request each, so PHP's upload limits never
 * see more than a few megabytes. A piece that fails is tried again before
 * giving up; `signal` cancels the whole upload.
 *
 *   const result = await uploadInChunks('/admin/api/courses/3/lesson-video', file, {
 *     onProgress: (fraction) => …, signal,
 *   })
 *
 * Resolves with the server's answer to the last piece.
 */
export async function uploadInChunks(url, file, { onProgress = () => {}, signal } = {}) {
  const id = uploadId()
  const total = Math.max(1, Math.ceil(file.size / CHUNK_BYTES))
  let last = null

  for (let index = 0; index < total; index++) {
    const piece = file.slice(index * CHUNK_BYTES, (index + 1) * CHUNK_BYTES)
    const form = new FormData()
    form.append('upload_id', id)
    form.append('index', String(index))
    form.append('total', String(total))
    form.append('name', file.name)
    form.append('chunk', piece, `${index}.part`)

    for (let attempt = 1; ; attempt++) {
      let response
      try {
        response = await fetch(url, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
          credentials: 'same-origin',
          body: form,
          signal,
        })
      } catch (err) {
        if (err.name === 'AbortError') throw new ApiError('Upload cancelled.', 0)
        if (attempt < TRIES) continue
        throw new ApiError('The connection dropped during the upload. Try again.', 0)
      }

      if (response.status === 401 || response.status === 419) {
        window.location.href = '/admin/login'
        throw new ApiError('Your session expired. Please sign in again.', response.status)
      }

      const payload = await response.json().catch(() => ({}))

      if (response.ok) {
        last = payload
        break
      }

      // A refusal (wrong type, too big) will not change on a retry.
      if (response.status < 500 || attempt >= TRIES) {
        throw new ApiError(payload.message || `Upload failed with status ${response.status}`, response.status, payload.errors || {})
      }
    }

    onProgress((index + 1) / total)
  }

  return last
}
