import { api } from '../api.js'
import { uploadInChunks } from '../lib/chunkUpload.js'

function blankCourse() {
  return {
    id: null,
    title: '',
    slug: '',
    subtitle: '',
    description: '',
    outcomes: [],
    requirements: [],
    level: 'Beginner',
    language: 'English',
    price: 0,
    sale_price: '',
    intro_video: '',
    status: 'Draft',
    published_at: '',
    is_featured: false,
    category_id: '',
    user_id: '',
    media_id: null,
    thumbnail: null,
    meta_title: '',
    meta_description: '',
    public_url: '',
  }
}

function blankLesson() {
  return {
    id: null,
    title: '',
    type: 'video',
    video_url: '',
    // An uploaded video (private, streamed to enrolled students only).
    video_path: null,
    video_name: '',
    video_size: null,
    body: '',
    media_id: null,
    attachment: null,
    duration_minutes: 0,
    is_preview: false,
  }
}

function humanSize(bytes) {
  if (!bytes) return ''
  const mb = bytes / 1024 / 1024
  return mb >= 1024 ? `${(mb / 1024).toFixed(1)} GB` : `${mb.toFixed(mb < 10 ? 1 : 0)} MB`
}

/** The tree as saved, without builder-only keys such as `_open`. */
function treeKey(tree) {
  return JSON.stringify(tree, (key, value) => (key.startsWith('_') ? undefined : value))
}

/** Fields the server sends back but does not take. */
const READ_ONLY = ['thumbnail', 'public_url', 'can', 'date', 'category', 'instructor', 'price_label', 'lessons_count', 'students_count']

/**
 * The full-page course editor. The course's own fields and its curriculum
 * are two requests (the curriculum is saved as one tree, like a page's
 * sections), but one Save button: details first, so a new course has an id
 * for its curriculum to hang from.
 */
export default function courseEditor() {
  // Cancels a running video upload. Kept out of the component: Alpine
  // would wrap it in a Proxy, and an AbortController must stay itself.
  let uploadAbort = null

  return {
    draft: blankCourse(),
    curriculum: [],
    options: { statuses: [], levels: [], categories: [], instructors: [], currency: '' },

    loading: true,
    saving: false,
    error: '',
    errors: {},
    curriculumErrors: {},
    tab: 'details',

    // What was last saved, to know whether there is anything to save.
    savedDraft: '',
    savedCurriculum: '[]',

    // The lesson being edited in the modal: a copy, written back on "Done".
    lesson: null,

    async init() {
      const param = this.$store.ui.param

      // Back / Forward between two courses keeps this component mounted.
      this.$watch('$store.ui.param', async (next) => {
        if (!next || next === 'new' || Number(next) === this.draft.id) return
        this.loading = true
        try {
          await this.fetchCourse(next)
        } catch (err) {
          this.error = err.message
        } finally {
          this.loading = false
        }
      })

      try {
        const [options] = await Promise.all([
          api.get('/courses/options'),
          param && param !== 'new' ? this.fetchCourse(param) : Promise.resolve(),
        ])
        this.options = options
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
        this.markSaved()
      }
    },

    async fetchCourse(id) {
      const [course, tree] = await Promise.all([
        api.get(`/courses/${id}`),
        api.get(`/courses/${id}/curriculum`),
      ])
      this.draft = { ...blankCourse(), ...course.data }
      this.curriculum = tree.data
      this.markSaved()
    },

    markSaved() {
      this.savedDraft = JSON.stringify(this.draft)
      this.savedCurriculum = treeKey(this.curriculum)
    },

    get isNew() {
      return !this.draft.id
    },

    get detailsDirty() {
      return JSON.stringify(this.draft) !== this.savedDraft
    },

    get curriculumDirty() {
      return treeKey(this.curriculum) !== this.savedCurriculum
    },

    get dirty() {
      return this.detailsDirty || this.curriculumDirty
    },

    get currency() {
      return this.options.currency || ''
    },

    fieldError(field) {
      return this.errors?.[field]?.[0] || ''
    },

    // ── lists: outcomes and requirements ───────────────────────────────
    addLine(list) {
      this.draft[list].push('')
      this.$nextTick(() => {
        const inputs = this.$root.querySelectorAll(`[data-list="${list}"] input`)
        inputs[inputs.length - 1]?.focus()
      })
    },

    removeLine(list, index) {
      this.draft[list].splice(index, 1)
    },

    // ── thumbnail ──────────────────────────────────────────────────────
    async chooseThumbnail() {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose a course image' })
      if (!media) return
      this.draft.media_id = media.id
      this.draft.thumbnail = media
    },

    clearThumbnail() {
      this.draft.media_id = null
      this.draft.thumbnail = null
    },

    // ── curriculum: sections ───────────────────────────────────────────
    get lessonCount() {
      return this.curriculum.reduce((n, section) => n + section.lessons.length, 0)
    },

    get totalMinutes() {
      return this.curriculum.reduce((n, s) => n + s.lessons.reduce((m, l) => m + (Number(l.duration_minutes) || 0), 0), 0)
    },

    get totalLength() {
      const m = this.totalMinutes
      return m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m} min`
    },

    addSection() {
      this.curriculum.push({ id: null, title: `Section ${this.curriculum.length + 1}`, lessons: [], _open: true })
    },

    async removeSection(index) {
      const section = this.curriculum[index]
      if (section.lessons.length) {
        const ok = await this.$store.dialog.confirm({
          title: 'Remove section',
          message: `“${section.title}” and its ${section.lessons.length} lesson(s) will be removed when you save. Students lose the progress they made on those lessons.`,
          confirmLabel: 'Remove section',
          danger: true,
        })
        if (!ok) return
      }
      this.curriculum.splice(index, 1)
    },

    move(list, index, step) {
      const to = index + step
      if (to < 0 || to >= list.length) return
      list.splice(to, 0, list.splice(index, 1)[0])
    },

    // ── curriculum: lessons ────────────────────────────────────────────
    addLesson(sectionIndex) {
      this.lesson = { ...blankLesson(), _section: sectionIndex, _index: null, _source: 'link', _upload: null }
    },

    editLesson(sectionIndex, lessonIndex) {
      const source = this.curriculum[sectionIndex].lessons[lessonIndex]
      this.lesson = {
        ...blankLesson(),
        ...JSON.parse(JSON.stringify(source)),
        _section: sectionIndex,
        _index: lessonIndex,
        _source: source.video_path ? 'upload' : 'link',
        _upload: null,
      }
    },

    async removeLesson(sectionIndex, lessonIndex) {
      const lesson = this.curriculum[sectionIndex].lessons[lessonIndex]
      const ok = await this.$store.dialog.confirm({
        title: 'Remove lesson',
        message: `Remove “${lesson.title}”? It goes when you save the course.`,
        confirmLabel: 'Remove',
        danger: true,
      })
      if (ok) this.curriculum[sectionIndex].lessons.splice(lessonIndex, 1)
    },

    closeLesson() {
      uploadAbort?.abort()
      this.lesson = null
    },

    /** Writes the modal's copy back into the tree — into another section if it was moved. */
    commitLesson() {
      if (!this.lesson) return
      if (!this.lesson.title.trim()) {
        this.lesson._error = 'Give the lesson a title.'
        return
      }
      if (this.lesson._upload?.running) {
        this.lesson._error = 'Wait for the video to finish uploading.'
        return
      }

      // One source per video lesson: the link or the upload, never both.
      if (this.lesson._source === 'upload') this.lesson.video_url = ''
      else Object.assign(this.lesson, { video_path: null, video_name: '', video_size: null })

      const { _section, _index, _error, _target, _source, _upload, ...data } = this.lesson
      const target = _target ?? _section

      if (_index !== null && target === _section) {
        this.curriculum[_section].lessons.splice(_index, 1, data)
      } else {
        if (_index !== null) this.curriculum[_section].lessons.splice(_index, 1)
        this.curriculum[target].lessons.push(data)
      }

      this.lesson = null
    },

    async chooseAttachment() {
      const media = await this.$store.picker.pick({ kind: 'all', title: 'Choose a file for students to download' })
      if (!media || !this.lesson) return
      this.lesson.media_id = media.id
      this.lesson.attachment = { id: media.id, name: media.name, url: media.url }
    },

    // ── uploaded videos ────────────────────────────────────────────────
    humanSize,

    /**
     * Uploads the chosen video in chunks, straight away, so the lesson only
     * has to remember where it went. The course must exist first: its id
     * is where the file is kept.
     */
    async uploadVideo(event) {
      const file = event.target.files?.[0]
      event.target.value = ''
      if (!file || !this.lesson) return

      if (this.isNew) {
        this.lesson._error = 'Save the course once (top right) before uploading videos.'
        return
      }

      const lesson = this.lesson
      lesson._error = ''
      lesson._upload = { running: true, percent: 0, name: file.name, size: file.size }
      uploadAbort = new AbortController()

      try {
        const result = await uploadInChunks(`/admin/api/courses/${this.draft.id}/lesson-video`, file, {
          signal: uploadAbort.signal,
          onProgress: (fraction) => (lesson._upload.percent = Math.round(fraction * 100)),
        })

        Object.assign(lesson, {
          video_path: result.video.path,
          video_name: result.video.name,
          video_size: result.video.size,
        })
        lesson._upload = null
        if (!lesson.title.trim()) lesson.title = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ')
        // The length, read from the file itself, if none was typed.
        if (!lesson.duration_minutes) this.readDuration(file).then((m) => m && (lesson.duration_minutes = m))
      } catch (err) {
        lesson._upload = null
        lesson._error = err.message
      } finally {
        uploadAbort = null
      }
    },

    cancelUpload() {
      uploadAbort?.abort()
    },

    removeUpload() {
      Object.assign(this.lesson, { video_path: null, video_name: '', video_size: null })
    },

    /** Minutes of video, read by the browser from the file before it is sent. */
    readDuration(file) {
      return new Promise((resolve) => {
        const video = document.createElement('video')
        const url = URL.createObjectURL(file)
        video.preload = 'metadata'
        video.onloadedmetadata = () => {
          URL.revokeObjectURL(url)
          resolve(Number.isFinite(video.duration) ? Math.max(1, Math.round(video.duration / 60)) : 0)
        }
        video.onerror = () => {
          URL.revokeObjectURL(url)
          resolve(0)
        }
        video.src = url
      })
    },

    /** "Is this a link the player can embed?" — shown under the video box. */
    videoKind(url) {
      if (!url) return ''
      if (/youtu\.?be/.test(url)) return 'YouTube — will be embedded'
      if (/vimeo\.com/.test(url)) return 'Vimeo — will be embedded'
      if (/\.(mp4|webm|ogg)(\?|$)/i.test(url)) return 'Video file — played in the page'
      return 'Not YouTube, Vimeo or a video file: check the link'
    },

    lessonError(s, l, field) {
      return this.curriculumErrors?.[`sections.${s}.lessons.${l}.${field}`]?.[0] || ''
    },

    // ── saving ─────────────────────────────────────────────────────────
    async save(status = null) {
      if (this.saving) return

      this.saving = true
      this.errors = {}
      this.curriculumErrors = {}
      this.error = ''

      if (status) this.draft.status = status

      const payload = { ...this.draft }
      for (const key of READ_ONLY) delete payload[key]

      const wasNew = this.isNew

      try {
        if (wasNew || this.detailsDirty) {
          const result = wasNew ? await api.post('/courses', payload) : await api.put(`/courses/${this.draft.id}`, payload)
          this.draft = { ...this.draft, ...result.data }
        }
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message
        this.tab = Object.keys(this.errors).some((f) => f.startsWith('meta_')) ? 'seo' : 'details'
        this.saving = false
        return
      }

      try {
        if (this.curriculumDirty || (wasNew && this.curriculum.length)) {
          const sections = this.curriculum.map(({ _open, ...section }) => ({
            ...section,
            lessons: section.lessons.map(({ attachment, ...lesson }) => lesson),
          }))
          const result = await api.put(`/courses/${this.draft.id}/curriculum`, { sections })
          // Keep which sections were open in the builder.
          const open = this.curriculum.map((s) => s._open)
          this.curriculum = result.data.map((s, i) => ({ ...s, _open: open[i] }))
        }

        this.markSaved()
        this.$store.ui.notify(wasNew ? 'Course created' : 'Course saved')
        if (wasNew) this.$store.ui.go('courses', this.draft.id)
      } catch (err) {
        this.curriculumErrors = err.errors || {}
        this.error = Object.keys(this.curriculumErrors).length
          ? 'The curriculum needs attention — see the highlighted lessons.'
          : err.message
        this.tab = 'curriculum'
        // The details were saved; only the curriculum is outstanding.
        this.savedDraft = JSON.stringify(this.draft)
        if (wasNew) this.$store.ui.go('courses', this.draft.id)
      } finally {
        this.saving = false
      }
    },

    async backToList() {
      if (this.dirty) {
        const ok = await this.$store.dialog.confirm({
          title: 'Leave without saving',
          message: 'This course has changes that are not saved yet.',
          confirmLabel: 'Leave',
          cancelLabel: 'Keep editing',
          danger: true,
        })
        if (!ok) return
      }
      this.$store.ui.go('courses')
    },
  }
}
