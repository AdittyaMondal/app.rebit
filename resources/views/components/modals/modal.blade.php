@php
	$layoutSide = request()->get('layoutSide', 'center');
@endphp

<div class="fixed inset-0 bg-black/15 z-50 backdrop-blur-xs">
	<div class="flex py-16 md:py-32 px-4 md:px-0 {{ $layoutSide == 'left' ? 'lg:ml-sidebar-width lg:pl-16' : 'justify-center'}}">
		<div class="w-full max-w-[var(--spacing-content)] shrink-0 bg-bg-pr rounded-2xl overflow-hidden shadow-xs">
			{{ $slot }}
		</div>
	</div>
</div>