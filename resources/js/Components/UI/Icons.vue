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

export const HomeIcon     = ic('<path d="M2 8L8 2L14 8V14.5H10V10H6V14.5H2z"/>')
export const InboxIcon    = ic('<path d="M1.5 8.5l2.5-6h8l2.5 6V14H1.5zM1.5 8.5h4l1 2h3l1-2h4"/>')
export const UserIcon     = ic('<circle cx="8" cy="5.5" r="3"/><path d="M1.5 14.5a6.5 6.5 0 0 1 13 0"/>')
export const PlusIcon     = ic('<path d="M8 2v12M2 8h12"/>')
export const HistoryIcon  = ic('<circle cx="9" cy="9" r="5.5"/><path d="M9 6v3.5l2.5 1.5"/><path d="M2.5 2.5v3.5h3.5"/><path d="M2.5 2.5A8 8 0 0 1 9 1"/>')
export const SettingsIcon = ic('<circle cx="8" cy="8" r="2.5"/><path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.6 3.4l-1.1 1.1M4.5 11.5l-1.1 1.1M12.6 12.6l-1.1-1.1M4.5 4.5L3.4 3.4"/>')
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
export const MenuIcon     = ic('<path d="M2 4h12M2 8h12M2 12h12"/>')
export const CalendarIcon = ic('<rect x="2" y="3.5" width="12" height="10" rx="1.5"/><path d="M5 2v3M11 2v3M2 6.5h12"/>')
export const CommentIcon  = ic('<path d="M2 2h12v9H8.5L6 13.5V11H2z"/>')
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
export const UsersIcon     = ic('<circle cx="6" cy="5.5" r="2"/><path d="M2 13c.4-2 2-3.5 4-3.5s3.6 1.5 4 3.5"/><circle cx="11.5" cy="5" r="1.8"/><path d="M13.5 12.5c-.4-1.8-1.6-3-3-3h-.5"/>')
export const PaperclipIcon = ic('<path d="M13 7.5 7.5 13A3.5 3.5 0 0 1 3 8.5L8 3.5A2 2 0 0 1 11 6l-5 5a.7.7 0 0 1-1-1l4-4"/>')
export const DownloadIcon  = ic('<path d="M8 3v7M5 7l3 3 3-3"/><path d="M3 13h10"/>')
export const LogoutIcon    = ic('<path d="M9.5 2.5H3a1 1 0 0 0-1 1V12.5a1 1 0 0 0 1 1H9.5M6.5 8H14M11 5L14 8L11 11"/>')
export const SunIcon       = ic('<circle cx="8" cy="8" r="3"/><path d="M8 1v1.5M8 13.5V15M15 8h-1.5M2.5 8H1M12.6 3.4l-1.1 1.1M4.5 11.5l-1.1 1.1M12.6 12.6l-1.1-1.1M4.5 4.5L3.4 3.4"/>')
export const MoonIcon      = ic('<path d="M13.5 9.5A6 6 0 1 1 6.5 2.5a5 5 0 0 0 7 7z"/>')
export const MonitorIcon   = ic('<path d="M2 2.5h12v8H2zM5.5 13.5h5M8 10.5v3"/>')
</script>
