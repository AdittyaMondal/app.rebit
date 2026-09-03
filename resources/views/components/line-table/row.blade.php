<div class="flex flex-col sm:flex-row sm:justify-between gap-0.5 sm:gap-2 py-2 border-b border-bord-pr border-dashed">
    <span class="text-par-n text-lab-sc">
        {{ (isset($labelText) == true) ? $labelText : '' }}
    </span>
    <span class="text-par-n text-lab-pr2 sm:ml-12 sm:text-right font-medium">
        {{ (isset($labelValue) == true) ? $labelValue : '' }}
    </span>
</div>