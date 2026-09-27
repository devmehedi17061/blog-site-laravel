/**
 * SEO analysis for a post, as pure functions over the draft.
 *
 * Nothing here talks to the server or the editor — it takes plain strings and
 * returns a verdict, which keeps it easy to reason about and to change when
 * the guidance changes.
 */

const TITLE_MIN = 30
const TITLE_MAX = 60
const DESC_MIN = 120
const DESC_MAX = 155
const WORDS_MIN = 300

const GOOD = 'good'
const WARN = 'warn'
const BAD = 'bad'

function stripTags(html) {
  return String(html || '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

function countWords(html) {
  const text = stripTags(html)
  return text ? text.split(/\s+/).length : 0
}

function has(haystack, needle) {
  if (!needle) return false
  return String(haystack || '').toLowerCase().includes(String(needle).toLowerCase())
}

function firstParagraph(html) {
  const match = String(html || '').match(/<p[^>]*>([\s\S]*?)<\/p>/i)
  return match ? stripTags(match[1]) : ''
}

function imagesMissingAlt(html) {
  const images = String(html || '').match(/<img\b[^>]*>/gi) || []
  return images.filter((tag) => !/\balt\s*=\s*["'][^"']+["']/i.test(tag)).length
}

function countMatches(html, pattern) {
  return (String(html || '').match(pattern) || []).length
}

/**
 * Mirrors the server's slug generation so the panel can judge the slug a post
 * will get, rather than the empty field it still has before the first save.
 */
export function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

/**
 * The slug the post will get. The editors ask the server for it (`_slug`),
 * which transliterates Bengali, Arabic and the rest; the local guess is only
 * a fallback until that answer arrives.
 */
export function effectiveSlug(draft) {
  if (draft._slug) return draft._slug
  return draft.slug ? slugify(draft.slug) : slugify(draft.title)
}

/** Is the keyword in the slug? Compared slug to slug, in any language. */
function keywordInSlug(draft, keyword) {
  const slug = effectiveSlug(draft)
  const wanted = draft._keywordSlug || slugify(keyword)
  if (!slug || !wanted) return false
  return `-${slug}-`.includes(`-${wanted}-`)
}

/** The title and description that will actually be published. */
export function effectiveTitle(draft) {
  return (draft.meta_title || draft.title || '').trim()
}

export function effectiveDescription(draft) {
  return (draft.meta_description || draft.excerpt || '').trim()
}

export function analyse(draft) {
  const title = effectiveTitle(draft)
  const description = effectiveDescription(draft)
  const keyword = (draft.focus_keyword || '').trim()
  const html = draft.body || ''
  const words = countWords(html)
  const missingAlt = imagesMissingAlt(html)
  const headings = countMatches(html, /<h[23]\b/gi)
  const links = countMatches(html, /<a\b[^>]*href=/gi)
  const images = countMatches(html, /<img\b/gi)

  const checks = [
    {
      key: 'title',
      label: 'SEO title length',
      ...range(title.length, TITLE_MIN, TITLE_MAX,
        `${title.length} characters`,
        title.length === 0 ? 'Add a title' : title.length < TITLE_MIN ? 'A little short' : 'Google will cut it off'),
    },
    {
      key: 'description',
      label: 'Meta description length',
      ...range(description.length, DESC_MIN, DESC_MAX,
        `${description.length} characters`,
        description.length === 0 ? 'Add a meta description or excerpt' : description.length < DESC_MIN ? 'Could say more' : 'Google will cut it off'),
    },
    keywordCheck('kw-title', 'Keyword in title', keyword, has(title, keyword)),
    keywordCheck('kw-slug', 'Keyword in slug', keyword, keywordInSlug(draft, keyword)),
    keywordCheck('kw-intro', 'Keyword in first paragraph', keyword, has(firstParagraph(html), keyword)),
    keywordCheck('kw-desc', 'Keyword in meta description', keyword, has(description, keyword)),
    {
      key: 'words',
      label: 'Word count',
      status: words >= WORDS_MIN ? GOOD : words > 0 ? WARN : BAD,
      detail: words === 0 ? 'The post is empty' : `${words} words`,
    },
    {
      key: 'headings',
      label: 'Subheadings',
      status: headings > 0 ? GOOD : WARN,
      detail: headings > 0 ? `${headings} used` : 'Break the post up with H2s',
    },
    {
      key: 'alt',
      label: 'Image alt text',
      status: images === 0 ? WARN : missingAlt === 0 ? GOOD : BAD,
      detail: images === 0 ? 'No images yet' : missingAlt === 0 ? `All ${images} described` : `${missingAlt} missing alt text`,
    },
    {
      key: 'links',
      label: 'Links',
      status: links > 0 ? GOOD : WARN,
      detail: links > 0 ? `${links} in the post` : 'Link out to related reading',
    },
  ]

  const score = Math.round((checks.filter((c) => c.status === GOOD).length / checks.length) * 100)

  return {
    checks,
    score,
    words,
    readingTime: Math.max(1, Math.round(words / 200)),
    verdict: score >= 80 ? GOOD : score >= 50 ? WARN : BAD,
  }
}

function range(length, min, max, goodDetail, badDetail) {
  if (length >= min && length <= max) return { status: GOOD, detail: goodDetail }
  if (length === 0) return { status: BAD, detail: badDetail }
  return { status: WARN, detail: `${goodDetail} — ${badDetail}` }
}

function keywordCheck(key, label, keyword, found) {
  if (!keyword) return { key, label, status: WARN, detail: 'Set a focus keyword' }
  return { key, label, status: found ? GOOD : BAD, detail: found ? 'Found' : 'Not found' }
}

/** Trims a description the way a search result does. */
export function snippet(text, limit = DESC_MAX) {
  const clean = stripTags(text)
  return clean.length > limit ? `${clean.slice(0, limit - 1)}…` : clean
}
