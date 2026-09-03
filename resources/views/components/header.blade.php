@props([
	'controls' => null
])

<header class="mb-6 fixed top-0 right-0 left-0 lg:left-72 z-30 bg-bg-pr border-b border-bord-tr">
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
			<div class="pr-4 md:pr-8 ml-auto">
				<div class="flex items-center gap-2 md:gap-3 leading-none">
					{{ $controls }}

                    <x-ui.buttons.header
                        url="{{ me()->profile_url }}"
                        avatarUrl="{{ me()->avatar_url }}"/>

                    @if(config('features.dark_theme.enabled'))
                        <x-ui.buttons.header
                            url="{{ route('user.theme.switch', theme_name() == 'dark' ? 'light' : 'dark') }}"
                            iconName="{{ theme_name() == 'dark' ? 'sun-01' : 'moon-01' }}"
                        iconType="solar" />
                    @endif
				</div>
			</div>
		@endif
    </div>
</header>
