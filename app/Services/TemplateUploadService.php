<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Logo and signature file handling (PRD FR-13, rules.md section 9).
 *
 * Validation of MIME type, extension and size happens in the FormRequest; this
 * service is only responsible for storing under a generated name and deleting.
 * Files are never referenced by their original name.
 */
class TemplateUploadService
{
    private const DIRECTORY_LOGO = 'templates/logos';

    private const DIRECTORY_SIGNATURE = 'templates/signatures';

    /**
     * Store the uploaded logo and return its relative path, or null.
     */
    public function storeLogo(Request $request): ?string
    {
        return $this->store($request->file('logo'), self::DIRECTORY_LOGO);
    }

    /**
     * Store the uploaded signature and return its relative path, or null.
     */
    public function storeSignature(Request $request): ?string
    {
        return $this->store($request->file('signature'), self::DIRECTORY_SIGNATURE);
    }

    /**
     * Delete a stored file if it exists.
     */
    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Store a validated upload under a generated name.
     */
    private function store(?UploadedFile $file, string $directory): ?string
    {
        if (! $file) {
            return null;
        }

        return Storage::disk('public')->putFile($directory, $file);
    }
}
