<template>
    <div class="p-4 border-b border-b-bord-pr last:border-none">
        <!-- Header: Avatar + Name + Timestamp -->
        <div class="flex justify-between items-center pb-3">
            <div class="flex items-center gap-2">
                <div class="shrink-0">
                    <span class="skeleton-circle size-8"></span>
                </div>
                <div class="flex-1 flex flex-col gap-1.5">
                    <span class="skeleton h-3 w-24"></span>
                    <span class="skeleton h-2.5 w-14"></span>
                </div>
            </div>
            <div class="shrink-0 ml-auto">
                <span class="skeleton-circle size-4"></span>
            </div>
        </div>
        <!-- Media placeholder (FIRST — matches media-first layout) -->
        <div class="block mb-3">
            <span class="skeleton-rounded h-48 w-full"></span>
        </div>
        <!-- Caption / Text lines (AFTER media) -->
        <div class="block mb-3 px-1">
            <div class="flex flex-col gap-2">
                <span class="skeleton h-3 w-11/12"></span>
                <span class="skeleton h-3 w-7/12"></span>
            </div>
        </div>
        <!-- Action buttons row -->
        <div class="flex items-center gap-3 px-1">
            <span class="skeleton-circle size-5"></span>
            <span class="skeleton-circle size-5"></span>
            <span class="skeleton-circle size-5"></span>
            <div class="ml-auto">
                <span class="skeleton h-3 w-10"></span>
            </div>
        </div>
    </div>
</template>