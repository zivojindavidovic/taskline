const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function parseLocalDate(input) {
  if (!input) return null
  if (input instanceof Date) return Number.isNaN(input.getTime()) ? null : new Date(input)
  const s = String(input)
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s)
  if (m) {
    return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10))
  }
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d
}

function startOfDay(d) {
  const x = new Date(d)
  x.setHours(0, 0, 0, 0)
  return x
}

export function dueDayDiff(dueDate) {
  const due = parseLocalDate(dueDate)
  if (!due) return null
  const today = startOfDay(new Date())
  return Math.round((startOfDay(due) - today) / 86400000)
}

export function formatShortDate(dateLike) {
  const d = parseLocalDate(dateLike)
  if (!d) return ''
  return `${MONTHS[d.getMonth()]} ${d.getDate()}`
}

export function formatDueDate(dueDate, startDate = null, completed = false) {
  if (!dueDate) return { label: '', urgent: false }
  if (startDate && startDate !== dueDate) {
    return { label: `${formatShortDate(startDate)}–${formatShortDate(dueDate)}`, urgent: false }
  }
  const diff = dueDayDiff(dueDate)
  if (completed || diff === null) return { label: formatShortDate(dueDate), urgent: false }
  if (diff < 0) {
    const n = Math.abs(diff)
    return { label: n === 1 ? '1 day overdue' : `${n} days overdue`, urgent: true }
  }
  if (diff === 0) return { label: 'today', urgent: true }
  if (diff === 1) return { label: 'tomorrow', urgent: true }
  return { label: formatShortDate(dueDate), urgent: false }
}
