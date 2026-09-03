<div class="flex flex-col sm:flex-row sm:justify-between gap-0.5 sm:gap-2 py-2 px-4 odd:bg-fill-qt">
    <span class="text-par-n text-lab-sc">
        {{ (isset($labelText) == true) ? $labelText : '' }}
    </span>
    <span class="text-par-n text-lab-pr2 font-medium">
        {{ (isset($labelValue) == true) ? $labelValue : '' }}
    </span>
</div>