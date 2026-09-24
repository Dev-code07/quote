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
            icon="clock"
        />
    </x-card>
</x-app-layout>