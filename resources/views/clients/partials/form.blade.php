{{--
    Create / edit client dialog.

    One form serves both actions: the Alpine `editing` flag switches the action
    URL, HTTP method and heading, so field markup exists only once
    (rules.md section 3 — no duplicated markup).
--}}
<x-modal name="client-form" maxWidth="2xl">
    <form
        x-data="{
            editing: false,
            createUrl: @js(route('clients.store')),
            updateUrlTemplate: @js(route('clients.update', ['client' => '__ID__'])),
            reset() {
                this.editing = false;
                this.$refs.form.reset();
                this.$refs.form.querySelector('#f_is_active').checked = true;
            },
            fill(client) {
                this.editing = true;
                const f = this.$refs.form;
                f.querySelector('#f_name').value = client.name ?? '';
                f.querySelector('#f_contact_person').value = client.contact_person ?? '';
                f.querySelector('#f_email').value = client.email ?? '';
                f.querySelector('#f_phone').value = client.phone ?? '';
                f.querySelector('#f_gstin').value = client.gstin ?? '';
                f.querySelector('#f_address').value = client.address ?? '';
                f.querySelector('#f_is_active').checked = Boolean(client.is_active);
            }
        }"
        x-ref="form"
        x-bind:action="editing ? updateUrlTemplate.replace('__ID__', editing) : createUrl"
        x-on:open-modal.window="if ($event.detail === 'client-form') reset()"
        x-on:fill-client.window="if ($event.detail.id) { editing = $event.detail.id; fill($event.detail) }"
        method="POST"
        class="w-full"
    >
        @csrf
        <input type="hidden" name="_method" x-bind:value="editing ? 'PUT' : 'POST'">

        <div class="border-b border-app-border px-5 py-4">
            <h3 class="text-[15px] font-bold" x-text="editing ? 'Edit Client' : 'Add Client'">Add Client</h3>
            <p class="mt-0.5 text-xs text-app-muted">Client name is required. All other fields are optional.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
            <x-input
                name="name"
                id="f_name"
                label="Client / Company Name"
                required
                class="sm:col-span-2"
                placeholder="e.g. IIT Mandi"
            />

            <x-input
                name="contact_person"
                id="f_contact_person"
                label="Contact Person"
                placeholder="e.g. Dr. S. Kumar"
            />

            <x-input
                name="phone"
                id="f_phone"
                label="Phone"
                placeholder="+91-98xxx-xxxxx"
            />

            <x-input
                name="email"
                id="f_email"
                type="email"
                label="Email"
                placeholder="client@company.com"
            />

            <x-input
                name="gstin"
                id="f_gstin"
                label="GSTIN"
                maxlength="15"
                placeholder="22AAAAA0000A1Z5"
                hint="15-character GSTIN, if the client is registered."
            />

            <x-textarea
                name="address"
                id="f_address"
                label="Billing Address"
                rows="3"
                class="sm:col-span-2"
                placeholder="Full billing address"
            />

            <div class="flex items-center gap-2 sm:col-span-2">
                <input
                    type="checkbox"
                    name="is_active"
                    id="f_is_active"
                    value="1"
                    checked
                    class="rounded-[4px] border-app-border text-app-accent focus:ring-app-accent/30"
                >
                <label for="f_is_active" class="text-[13px] text-app-muted">Active — can receive new quotations</label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-app-border bg-app-neutral-soft px-5 py-3">
            <x-button type="button" variant="ghost" icon="x" x-on:click="$dispatch('close-modal', 'client-form')">Cancel</x-button>
            <x-button type="submit" icon="check" x-text="editing ? 'Save Changes' : 'Create Client'">Create Client</x-button>
        </div>
    </form>
</x-modal>