@props([
	'controls' => null
])

<header class="mb-6 fixed top-0 right-0 left-0 lg:left-sidebar-width z-30 bg-transparent backdrop-blur-md">
    <div class="flex items-center py-3 h-16">
        {{-- Mobile hamburger button --}}
        <button
            x-on:click="sidebarOpen = true"
            class="ml-4 size-10 flex items-center justify-center rounded-full text-lab-pr2 lg:hidden"
            aria-label="Open menu"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>

		@if(isset($controls))
			<div class="pr-4 md:pr-16 ml-auto">
				<div class="flex items-center gap-2 md:gap-4 leading-none">
					{{ $controls }}

					<a target="_blank" href="{{ me()->profile_url }}" class="shrink-0 cursor-pointer items-center">
						<div class="size-[40px] rounded-full overflow-hidden">
							<img class="w-full" src="{{ me()->avatar_url }}" alt="Image">
						</div>
					</a>
				</div>
			</div>
		@endif
    </div>
</header>