import { cssValues, fillTokens, renderTemplate, typeMap } from './template.js'
import { contentStyleFrom, inlineStyleFrom, scopeCss, visibilityCss } from './style.js'

/** The preview's band stands in for #section-{id} on the published page. */
const ROOT = '#preview-root'

/**
 * The site's own stylesheets: the panel is built from the same bundle as the
 * public site (app.css) and loads the same web font, so borrowing its <link>s
 * gives the preview the site's fonts, headings and base rules — the preview
 * then looks like the page, not like a bare browser default.
 */
function siteStylesheets() {
  const design = window.__SITE_DESIGN__ ?? {}
  const links = [...document.querySelectorAll('link[rel="stylesheet"]')].map((link) => link.href)
  if (design.fonts) links.push(design.fonts)

  // Settings → Design last, so the site's colours, fonts and widths win.
  return links.map((href) => `<link rel="stylesheet" href="${href.replace(/"/g, '&quot;')}">`).join('')
    + (design.css ? `<style>${design.css}</style>` : '')
}

/**
 * Builds the srcdoc for a preview frame. The frame is used rather than a div
 * so the section's CSS is sealed off from the admin panel's own styles, the
 * same way it is sealed off from the rest of the page when it publishes.
 *
 * `frame` is the band's style to fall back on when the section has none —
 * the library previews a section with a typical page's spacing, since the
 * real spacing is set on each page.
 *
 * @param {{ mode: string, html: string, css: string, fields: Array, values: Object, style: Object, globals: Object, frame?: Object }} section
 */
export function previewDoc({ mode, html, css, fields, values, style, globals, frame }) {
  const library = mode !== 'Blank'
  const types = typeMap(fields ?? [])
  const look = { ...(frame ?? {}), ...Object.fromEntries(Object.entries(style ?? {}).filter(([, v]) => v !== '' && v !== null && v !== undefined)) }

  const body = library
    ? renderTemplate(html, values ?? {}, types, '', globals ?? {})
    : String(html ?? '')

  // Tokens work in CSS too, so a colour field can drive a rule.
  const sheet = library
    ? fillTokens(String(css ?? ''), cssValues(values ?? {}))
    : String(css ?? '')

  const scoped = scopeCss(sheet, ROOT) + visibilityCss(look, ROOT)

  // The same two boxes, with the same classes, the published page uses: a
  // full-width band, and the column inside it.
  const band = attribute(inlineStyleFrom(look))
  const column = attribute(contentStyleFrom(look))

  return '<!DOCTYPE html><html><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1">'
    + siteStylesheets()
    + '<style>html,body{margin:0}#preview-root{width:100%}</style>'
    + `<style>${scoped}</style></head>`
    + `<body class="site-body bg-bg text-ink"><main class="w-full"><section id="preview-root" class="site-section"${band}>`
    + `<div class="site-section-inner preview-content"${column}>${body}</div></section></main>`
    // The site's own script, so sliders and the like move in the preview too.
    + (window.__SITE_SCRIPT__ ? `<script type="module" src="${String(window.__SITE_SCRIPT__).replace(/"/g, '&quot;')}"></script>` : '')
    + '</body></html>'
}

function attribute(declarations) {
  return declarations ? ` style="${declarations.replace(/"/g, '&quot;')}"` : ''
}
