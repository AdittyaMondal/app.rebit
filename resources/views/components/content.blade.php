@props([
	'width' => 'w-full max-w-[var(--spacing-content)]',
])

<div class="{{ $width }} bg-bg-pr rounded-2xl p-4 md:p-6 border border-bord-sc">
	{{ $slot }}
</div>
