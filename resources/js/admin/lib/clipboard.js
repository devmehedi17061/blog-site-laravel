/**
 * Copies text, falling back to a hidden textarea where the async clipboard
 * API is unavailable — it needs a secure context, and the panel is often
 * served over plain http in development.
 */
export async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    const box = document.createElement('textarea')
    box.value = text
    box.style.position = 'fixed'
    box.style.opacity = '0'
    document.body.appendChild(box)
    box.select()

    const copied = document.execCommand('copy')
    box.remove()

    return copied
  }
}
