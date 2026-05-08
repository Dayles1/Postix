{{-- mobile overlay --}}
<div :class="$store.sidebar.isMobileOpen ? 'block xl:hidden' : 'hidden'"
     @click="$store.sidebar.setMobileOpen(false)"
     class="fixed z-50 h-screen w-full bg-black/50">
</div>