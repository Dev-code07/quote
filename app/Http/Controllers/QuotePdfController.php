<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\PdfService;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuotePdfController extends Controller
{
    public function __construct(private readonly PdfService $pdf) {}

    /**
     * Download the quotation as an A4 PDF (PRD FR-11).
     */
    public function download(Quote $quote): SymfonyResponse
    {
        $this->authorize('view', $quote);

        return $this->pdf->download($quote);
    }

    /**
     * Render the quotation in a new browser tab.
     */
    public function show(Quote $quote): SymfonyResponse
    {
        $this->authorize('view', $quote);

        return $this->pdf->stream($quote);
    }
}
