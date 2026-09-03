<div class="bg-bg-pr rounded-2xl pt-5">
    @if(isset($filter))
        <div class="pb-4">
            <div class="px-4">
                {{ $filter }}
            </div>
            <x-div class="px-4"></x-div>
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px]">
            {{ $slot }}
        </table>
    </div>
</div>