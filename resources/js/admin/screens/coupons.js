import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resource-table.js'

/** LMS → Coupons: the generic list-and-modal screen, plus a course picker. */
export default function coupons() {
  const table = resourceTable({
    endpoint: 'coupons',
    label: 'Coupon',
    blank: () => ({
      id: null,
      code: '',
      description: '',
      type: 'percent',
      value: 10,
      max_uses: '',
      max_uses_per_user: 1,
      min_amount: '',
      starts_at: '',
      expires_at: '',
      is_active: true,
      course_ids: [],
    }),
  })

  return extend(table, {
    courseSearch: '',

    get statusFilters() {
      return ['All', ...(this.options.statuses ?? [])]
    },

    // resourceTable sends "status"; the server reads Active / Expired / Off.
    setStatus(status) {
      this.query.status = status
      this.applyFilters()
    },

    /** A code nobody will guess: SAVE-7KQ2. */
    generateCode() {
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
      const pick = () => chars[Math.floor(Math.random() * chars.length)]
      this.modal.draft.code = 'SAVE-' + Array.from({ length: 5 }, pick).join('')
    },

    get visibleCourses() {
      const term = this.courseSearch.trim().toLowerCase()
      return (this.options.courses ?? []).filter((c) => !term || c.title.toLowerCase().includes(term))
    },

    toggleCourse(id) {
      const ids = this.modal.draft.course_ids
      const i = ids.indexOf(id)
      i >= 0 ? ids.splice(i, 1) : ids.push(id)
    },

    /** What the student will see, as the form is filled in. */
    get preview() {
      const d = this.modal?.draft
      if (!d) return ''
      const off = d.type === 'percent' ? `${Number(d.value) || 0}% off` : `${this.options.currency ?? ''}${Number(d.value) || 0} off`
      const where = d.course_ids.length ? `${d.course_ids.length} course${d.course_ids.length === 1 ? '' : 's'}` : 'every course'
      return `${off} ${where}`
    },

    tagFor(state) {
      return { Active: 'tag-success', Scheduled: 'tag-accent-2', Expired: 'tag-neutral', 'Used up': 'tag-neutral', Off: 'tag-outline' }[state] ?? 'tag-neutral'
    },

    copyLink(row) {
      navigator.clipboard?.writeText(row.share_url)
      this.$store.ui.notify(`Promo link copied: ${row.share_url}`)
    },

    // The server sends dates as "2026-10-08T10:00"; the modal edits them as is.
    openEdit(row) {
      this.modal = {
        mode: 'edit',
        draft: { ...row, course_ids: [...(row.course_ids ?? [])], max_uses: row.max_uses ?? '', starts_at: row.starts_at ?? '', expires_at: row.expires_at ?? '' },
        errors: {},
        message: '',
        saving: false,
      }
      this.courseSearch = ''
    },
  })
}
