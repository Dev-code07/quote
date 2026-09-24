<x-app-layout title="Template Trash">
    <x-section-header title="Template Trash" back="{{ route('templates.index') }}">
        <x-slot name="description">Trashed templates can be restored. Quotations already created keep their own copy of the letterhead.</x-slot>
    </x-section-header>

    <x-card :padding="false">
        @if ($templates->isEmpty())
            <x-empty-state title="Trash is empty" description="Templates you delete will appear here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13.5px]">
                    <thead>
                        <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                            <th class="px-4 py-3 font-semibold">Template</th>
                            <th class="px-4 py-3 font-semibold">Company</th>
                            <th class="px-4 py-3 font-semibold">Deleted</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr class="border-b border-app-border last:border-0 hover:bg-app-neutral-soft/60">
                                <td class="px-4 py-3 font-semibold">{{ $template->name }}</td>
                                <td class="px-4 py-3 text-app-muted">{{ $template->company_name }}</td>
                                <td class="px-4 py-3 text-app-muted">{{ $template->deleted_at?->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('templates.restore', ['template' => $template->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="subtle">Restore</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('templates.force-destroy', ['template' => $template->id]) }}" onsubmit="return confirm('Permanently delete this template and its uploaded files? Quotes already created are not affected.');">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger">Delete Permanently</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-app-border px-4 py-3">{{ $templates->links() }}</div>
        @endif
    </x-card>
</x-app-layout>