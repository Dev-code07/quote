{{--
    Shared placeholder for modules scheduled in a later phase.
    Replaced by the real index in Phase 3 (templates) / Phase 4 (quotes).
--}}
<x-app-layout :title="$title">
    <x-section-header :title="$title" :description="$description" />

    <x-card>
        <x-empty-state
            :title="$title.' arrive in '.$phase"
            :description="'This module is scheduled for '.$phase.'. The navigation entry is live so the shell can be reviewed end to end.'"
            :icon="'<svg width=\'28\' height=\'28\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.6\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><circle cx=\'12\' cy=\'12\' r=\'9\'/><polyline points=\'12 7 12 12 15 14\'/></svg>'"
        />
    </x-card>
</x-app-layout>