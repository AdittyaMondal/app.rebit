<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed top-0 bottom-0 border-r border-bord-pr left-0 h-screen w-72 bg-bg-pr/70 backdrop-blur-sm z-50 lg:z-auto transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0"
>
    {{-- Mobile close button --}}
    <button
        x-on:click="sidebarOpen = false"
        class="absolute top-4 right-4 size-8 flex items-center justify-center rounded-full bg-fill-qt text-lab-pr2 lg:hidden"
        aria-label="Close sidebar"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>

    <div class="flex">
        {{ $slot }}
    </div>
</aside>
