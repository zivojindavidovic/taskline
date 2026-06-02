// Robust clipboard copy.
//
// navigator.clipboard.writeText only works in a secure context (https or
// localhost) and may be blocked or rejected in some embedded/iframe contexts.
// When it is unavailable or fails we fall back to a hidden <textarea> +
// document.execCommand('copy'), which works in plain http and older browsers.
//
// Returns a Promise<boolean> — true when the text was copied, false otherwise,
// so callers can show honest feedback.
export async function copyText(text) {
  const value = String(text ?? '')

  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(value)
      return true
    } catch (e) {
      // fall through to execCommand fallback
    }
  }

  try {
    const ta = document.createElement('textarea')
    ta.value = value
    ta.setAttribute('readonly', '')
    ta.style.position = 'fixed'
    ta.style.top = '-9999px'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.focus()
    ta.select()
    ta.setSelectionRange(0, value.length)
    const ok = document.execCommand('copy')
    document.body.removeChild(ta)
    return ok
  } catch (e) {
    return false
  }
}
