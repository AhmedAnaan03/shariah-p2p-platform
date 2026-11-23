<?php

namespace App\Http\Controllers;

use App\Models\KycDocument;
use App\Services\KycDocumentStore;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens a KYC document (PB-09, PB-10), for its owner and administrators, guarded by
 * KycDocumentPolicy. Uploading moved into the onboarding wizard's document slots (PB-53,
 * OnboardingController).
 */
class KycDocumentController extends Controller
{
    /**
     * Send one document's file to its owner or an administrator. This is the only way to read
     * a KYC file: the disk has no public URL.
     */
    public function show(KycDocument $document, KycDocumentStore $store): Response
    {
        // Owner or admin; anyone else gets 404 (see KycDocumentPolicy).
        Gate::authorize('view', $document);

        abort_unless($store->exists($document), 404);

        return response($store->contents($document), 200, [
            // The type the server detected at upload, never what the browser claimed.
            'Content-Type' => $document->mime_type,
            // Shown in the browser (PDF viewer or image), with the original name if saved.
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $document->original_name,
                self::asciiFallback($document->original_name),
            ),
            // Don't let the browser guess a different type from the content.
            'X-Content-Type-Options' => 'nosniff',
            // Don't let the browser or any proxy keep a copy.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Old browsers only understand plain-ASCII file names in the header.
     */
    private static function asciiFallback(string $name): string
    {
        return preg_replace('/[^\x20-\x7E]|[%"\/\\\\]/', '_', Str::ascii($name));
    }
}
