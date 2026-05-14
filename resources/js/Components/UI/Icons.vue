<script>
import { h } from 'vue'

// Creates a Vue component from raw SVG inner content using h() — no runtime compiler needed
const ic = (svgContent) => ({
  inheritAttrs: false,
  setup(props, { attrs }) {
    return () => h('svg', {
      ...attrs,
      width: 16,
      height: 16,
      viewBox: '0 0 16 16',
      fill: 'none',
      stroke: 'currentColor',
      'stroke-width': '1.5',
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
      innerHTML: svgContent,
    })
  },
})

export const HomeIcon     = ic('<path d="M2.5 7.5 8 3l5.5 4.5V13a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1V7.5z"/>')
export const InboxIcon    = ic('<rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M2 9h3l1 2h4l1-2h3"/>')
export const UserIcon     = ic('<circle cx="8" cy="5.5" r="2.5"/><path d="M3 13.5c.5-2.5 2.5-4 5-4s4.5 1.5 5 4"/>')
export const PlusIcon     = ic('<path d="M8 3v10M3 8h10"/>')
export const HistoryIcon  = ic('<path d="M2.5 8a5.5 5.5 0 1 1 1.6 3.9"/><path d="M2.5 11.5v-3h3"/><path d="M8 5v3l2 1.5"/>')
export const SettingsIcon = ic('<circle cx="8" cy="8" r="2"/><path d="M8 1v2M8 13v2M14 8h-2M4 8H2M12.2 3.8l-1.4 1.4M5.2 10.8l-1.4 1.4M12.2 12.2l-1.4-1.4M5.2 5.2 3.8 3.8"/>')
export const ChevronIcon  = ic('<path d="m4 6 4 4 4-4"/>')
export const ChevronRightIcon = ic('<path d="m6 4 4 4-4 4"/>')
export const SearchIcon   = ic('<circle cx="7" cy="7" r="4.5"/><path d="m11 11 3 3"/>')
export const LockIcon     = ic('<rect x="3.5" y="7" width="9" height="6" rx="1"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/>')
export const BoardIcon    = ic('<rect x="2" y="2.5" width="12" height="11" rx="1.5"/><path d="M6 2.5v11M10 2.5v11"/>')
export const ListIcon     = ic('<path d="M5 4h9M5 8h9M5 12h9"/><circle cx="2.5" cy="4" r=".6" fill="currentColor"/><circle cx="2.5" cy="8" r=".6" fill="currentColor"/><circle cx="2.5" cy="12" r=".6" fill="currentColor"/>')
export const LightningIcon= ic('<path d="M9 2 4 9h3l-1 5 5-7H8l1-5z"/>')
export const MoreIcon     = ic('<circle cx="3" cy="8" r="1" fill="currentColor" stroke="none"/><circle cx="8" cy="8" r="1" fill="currentColor" stroke="none"/><circle cx="13" cy="8" r="1" fill="currentColor" stroke="none"/>')
export const CheckIcon    = ic('<path d="M3 8.5l3 3 7-7"/>')
export const CloseIcon    = ic('<path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/>')
export const CalendarIcon = ic('<rect x="2" y="3.5" width="12" height="10" rx="1.5"/><path d="M5 2v3M11 2v3M2 6.5h12"/>')
export const CommentIcon  = ic('<path d="M2.5 7c0-2.5 2-4.5 5.5-4.5s5.5 2 5.5 4.5-2 4.5-5.5 4.5c-.5 0-1 0-1.5-.1L4 13l.5-2.4C3.2 9.7 2.5 8.5 2.5 7z"/>')
export const SendIcon     = ic('<path d="M2 8 14 2l-4.5 12L7 9 2 8z"/>')
export const TrashIcon    = ic('<path d="M3 4.5h10M6 4.5V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1.5M4.5 4.5l.5 8a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1l.5-8"/>')
export const EditIcon     = ic('<path d="M11 2.5l2.5 2.5L6 12.5H3.5V10L11 2.5z"/>')
export const CopyIcon     = ic('<rect x="5" y="5" width="8" height="8" rx="1"/><path d="M10 5V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h1"/>')
export const LinkIcon     = ic('<path d="M9 4.5h2A2.5 2.5 0 0 1 13.5 7v0A2.5 2.5 0 0 1 11 9.5H9M7 11.5H5A2.5 2.5 0 0 1 2.5 9v0A2.5 2.5 0 0 1 5 6.5h2M5.5 8h5"/>')
export const ArrowRightIcon = ic('<path d="M3 8h10M9 4l4 4-4 4"/>')
export const ArrowLeftIcon  = ic('<path d="M13 8H3M7 4l-4 4 4 4"/>')
export const FlagIcon     = ic('<path d="M3.5 13V3M3.5 3h7l-1.5 2.5L10.5 8h-7"/>')
export const FilterIcon   = ic('<path d="M2 3h12l-4.5 5.5V13l-3-1.5V8.5L2 3z"/>')
export const BellIcon     = ic('<path d="M4 11V8a4 4 0 0 1 8 0v3l1 1.5H3L4 11z"/><path d="M6.5 14a1.5 1.5 0 0 0 3 0"/>')
export const EyeIcon      = ic('<path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8z"/><circle cx="8" cy="8" r="1.8"/>')
export const DragIcon     = ic('<circle cx="6" cy="4" r=".8" fill="currentColor" stroke="none"/><circle cx="10" cy="4" r=".8" fill="currentColor" stroke="none"/><circle cx="6" cy="8" r=".8" fill="currentColor" stroke="none"/><circle cx="10" cy="8" r=".8" fill="currentColor" stroke="none"/><circle cx="6" cy="12" r=".8" fill="currentColor" stroke="none"/><circle cx="10" cy="12" r=".8" fill="currentColor" stroke="none"/>')
export const UsersIcon    = ic('<circle cx="6" cy="5.5" r="2"/><path d="M2 13c.4-2 2-3.5 4-3.5s3.6 1.5 4 3.5"/><circle cx="11.5" cy="5" r="1.8"/><path d="M13.5 12.5c-.4-1.8-1.6-3-3-3h-.5"/>')
</script>
