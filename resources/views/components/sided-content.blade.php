<div class="flex flex-col-reverse lg:flex-row gap-4 items-start">
	<div class="w-full lg:w-content lg:shrink-0 bg-bg-pr rounded-2xl p-5 border border-bord-sc">
		{{ $slot }}
	</div>
	<div class="w-full lg:w-80 lg:shrink-0">
		{{ $sideContent }}
	</div>
</div>
