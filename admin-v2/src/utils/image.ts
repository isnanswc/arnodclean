// admin-v2/src/utils/image.ts
// Utility for resolving image and media URLs across local subdirectories and production domains

export function getImageUrl(path: string | null | undefined): string {
  if (!path) return ''

  // Return as-is if already an absolute HTTP(S) URL, data URL, or blob preview URL
  if (
    path.startsWith('http://') ||
    path.startsWith('https://') ||
    path.startsWith('data:') ||
    path.startsWith('blob:')
  ) {
    return path
  }

  // Strip leading slashes and relative parent prefixes
  let clean = path.replace(/^(\.\.\/|\.\/|\/)+/, '')

  // Prevent double /arno-dc/
  if (clean.startsWith('arno-dc/')) {
    clean = clean.substring(8).replace(/^\/+/, '')
  }

  // Detect if application is accessed via /arno-dc/ subdirectory
  const isSubdir = typeof window !== 'undefined' && window.location.pathname.includes('/arno-dc')
  const base = isSubdir ? '/arno-dc/' : '/'

  return `${base}${clean}`
}

export function handleImageError(event: Event, fallback = '') {
  const target = event.target as HTMLImageElement
  if (!target) return
  if (fallback) {
    target.src = fallback
  } else {
    // Hide image and show fallback element if any
    target.style.display = 'none'
    const parent = target.parentElement
    const fallbackEl = parent?.querySelector('.img-fallback') as HTMLElement | null
    if (fallbackEl) {
      fallbackEl.style.display = 'flex'
    }
  }
}
