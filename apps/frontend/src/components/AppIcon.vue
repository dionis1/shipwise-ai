<script setup lang="ts">
import {
  ArrowUpRight,
  ArrowRight,
  LayoutDashboard,
  Users,
  KeyRound,
  ArrowLeftRight,
  Euro,
  Megaphone,
  LifeBuoy,
  Truck,
  CircleCheck,
  ShieldCheck,
  LogIn,
  LogOut,
  UserRound,
  Mail,
  LockKeyhole,
  AlertTriangle,
  ChevronDown,
  ChevronRight,
  ChevronLeft,
  X,
  Menu,
  Search,
  Globe,
  Check,
  FileText,
  Settings,
  Clock,
  Plus,
  SlidersHorizontal,
  PackageOpen,
  Info,
  LoaderCircle,
} from 'lucide-vue-next'
withDefaults(defineProps<{ name: string; size?: number }>(), { size: 20 })
const icons = {
  arrow: ArrowUpRight,
  next: ArrowRight,
  overview: LayoutDashboard,
  staff: Users,
  access: KeyRound,
  operations: ArrowLeftRight,
  finance: Euro,
  marketing: Megaphone,
  support: LifeBuoy,
  carriers: Truck,
  qa: CircleCheck,
  audit: ShieldCheck,
  login: LogIn,
  logout: LogOut,
  user: UserRound,
  mail: Mail,
  lock: LockKeyhole,
  warning: AlertTriangle,
  down: ChevronDown,
  right: ChevronRight,
  left: ChevronLeft,
  close: X,
  menu: Menu,
  search: Search,
  globe: Globe,
  check: Check,
  file: FileText,
  settings: Settings,
  clock: Clock,
  plus: Plus,
  filters: SlidersHorizontal,
  empty: PackageOpen,
  info: Info,
  loading: LoaderCircle,
} as const
</script>
<template>
  <component
    :is="icons[name as keyof typeof icons] || icons.info"
    :size="size"
    :stroke-width="1.8"
    aria-hidden="true"
    focusable="false"
  />
</template>
