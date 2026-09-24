<?php

namespace App\Http\Controllers;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use App\Http\Requests\StoreQuoteTemplateRequest;
use App\Http\Requests\UpdateQuoteTemplateRequest;
use App\Models\QuoteTemplate;
use App\Services\QuotationDocumentService;
use App\Services\TemplateUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class QuoteTemplateController extends Controller
{
    public function __construct(
        private readonly TemplateUploadService $uploads,
        private readonly QuotationDocumentService $documents,
    ) {}

    /**
     * Card grid of templates with search and trash link.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', QuoteTemplate::class);

        $search = $request->string('search')->trim()->value();

        $templates = QuoteTemplate::query()
            ->search($search)
            ->withCount('quotes')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('templates.index', [
            'templates' => $templates,
            'search' => $search,
        ]);
    }

    /**
     * 4-step editor (design.md 2.2, quoteflow_template_editor.html).
     */
    public function create(Request $request): View
    {
        $this->authorize('create', QuoteTemplate::class);

        return view('templates.form', [
            'template' => new QuoteTemplate([
                'accent_color' => AccentPalette::default(),
                'header_alignment' => HeaderAlignment::default(),
                'doc_title' => 'QUOTATION',
                'default_gst_rate' => 18,
            ]),
            'copyFrom' => QuoteTemplate::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Persist a new template.
     */
    public function store(StoreQuoteTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', QuoteTemplate::class);

        $data = $this->attributes($request);

        $template = DB::transaction(function () use ($data, $request) {
            $template = QuoteTemplate::query()->create([
                ...$data,
                'logo_path' => $this->uploads->storeLogo($request),
                'signature_path' => $this->uploads->storeSignature($request),
                'created_by' => $request->user()->id,
            ]);

            // BR-04: at most one default template.
            if ($template->is_default) {
                $this->clearOtherDefaults($template);
            }

            return $template;
        });

        return Redirect::route('templates.show', $template)
            ->with('status', 'Template created successfully.');
    }

    /**
     * Template detail with a live A4 preview.
     */
    public function show(QuoteTemplate $template): View
    {
        $this->authorize('view', $template);

        return view('templates.show', [
            'template' => $template,
            'quoteCount' => $template->quotes()->count(),
            'previewDoc' => $this->documents->fromTemplate($template),
        ]);
    }

    /**
     * Editor pre-filled with the existing template.
     */
    public function edit(QuoteTemplate $template): View
    {
        $this->authorize('update', $template);

        return view('templates.form', [
            'template' => $template,
            'copyFrom' => QuoteTemplate::query()
                ->whereKeyNot($template->getKey())
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Persist changes. Existing quotes are unaffected (BR-01).
     */
    public function update(UpdateQuoteTemplateRequest $request, QuoteTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        DB::transaction(function () use ($request, $template) {
            $data = $this->attributes($request);

            // Remove old uploads when asked, or when a replacement is supplied.
            if ($request->boolean('remove_logo')) {
                $this->uploads->delete($template->logo_path);
                $data['logo_path'] = null;
            }

            if ($request->boolean('remove_signature')) {
                $this->uploads->delete($template->signature_path);
                $data['signature_path'] = null;
            }

            if ($request->hasFile('logo')) {
                $this->uploads->delete($template->logo_path);
                $data['logo_path'] = $this->uploads->storeLogo($request);
            }

            if ($request->hasFile('signature')) {
                $this->uploads->delete($template->signature_path);
                $data['signature_path'] = $this->uploads->storeSignature($request);
            }

            $template->update($data);

            if ($template->is_default) {
                $this->clearOtherDefaults($template);
            }
        });

        return Redirect::route('templates.show', $template)
            ->with('status', 'Template updated. Existing quotations are unchanged.');
    }

    /**
     * Copy a template, including its branding files (PRD FR-04).
     */
    public function duplicate(QuoteTemplate $template): RedirectResponse
    {
        $this->authorize('create', QuoteTemplate::class);

        $copy = $template->replicate(['is_default', 'last_used_at', 'created_by']);
        $copy->name = $template->name.' (copy)';
        $copy->is_default = false;

        // Share the same stored file; deleting one copy must not break the other.
        $copy->logo_path = $template->logo_path;
        $copy->signature_path = $template->signature_path;
        $copy->created_by = auth()->id();
        $copy->save();

        return Redirect::route('templates.edit', $copy)
            ->with('status', 'Template duplicated.');
    }

    /**
     * Make this the default template (BR-04).
     */
    public function setDefault(QuoteTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        DB::transaction(function () use ($template) {
            QuoteTemplate::query()->whereKeyNot($template->getKey())->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return Redirect::back()->with('status', "\"{$template->name}\" is now the default template.");
    }

    /**
     * Move a template to the trash (decision #8).
     */
    public function destroy(QuoteTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $name = $template->name;

        if ($template->is_default) {
            $template->update(['is_default' => false]);
        }

        $template->delete();

        return Redirect::route('templates.index')
            ->with('status', "\"{$name}\" moved to trash.");
    }

    /**
     * Restore a template from trash.
     */
    public function restore(int $template): RedirectResponse
    {
        $model = QuoteTemplate::onlyTrashed()->findOrFail($template);

        $this->authorize('restore', $model);

        $model->restore();

        return Redirect::route('templates.trash')->with('status', "\"{$model->name}\" restored.");
    }

    /**
     * Permanently delete a trashed template and its uploaded files.
     */
    public function forceDestroy(int $template): RedirectResponse
    {
        $model = QuoteTemplate::onlyTrashed()->findOrFail($template);

        $this->authorize('forceDelete', $model);

        $liveQuotes = $model->quotes()->exists();

        $this->uploads->delete($model->logo_path);
        $this->uploads->delete($model->signature_path);

        if ($liveQuotes) {
            // Quotes keep their own snapshot, so the template row can go while
            // the printed history survives (BR-01).
            $model->quotes()->update(['template_id' => null]);
        }

        $name = $model->name;
        $model->forceDelete();

        return Redirect::route('templates.trash')
            ->with('status', "\"{$name}\" permanently deleted.");
    }

    /**
     * Trash listing.
     */
    public function trash(): View
    {
        $this->authorize('viewAny', QuoteTemplate::class);

        return view('templates.trash', [
            'templates' => QuoteTemplate::onlyTrashed()->orderBy('name')->paginate(12),
        ]);
    }

    /**
     * Only the columns that belong to the template itself; files are handled
     * separately by TemplateUploadService (one concern per class).
     *
     * @return array<string, mixed>
     */
    private function attributes(StoreQuoteTemplateRequest $request): array
    {
        return $request->safe()->only([
            'name', 'is_default', 'accent_color', 'header_alignment',
            'letterhead_display_name', 'doc_title', 'company_name', 'company_gstin',
            'tagline', 'address', 'email', 'mobile_1', 'mobile_2', 'stamp_place',
            'authorized_person', 'designation', 'default_gst_rate', 'intro_message',
            'delivery_period', 'warranty', 'validity_text', 'extra_terms', 'notes',
        ]);
    }

    /**
     * Ensure no other template remains the default (BR-04).
     */
    private function clearOtherDefaults(QuoteTemplate $template): void
    {
        QuoteTemplate::query()
            ->whereKeyNot($template->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
