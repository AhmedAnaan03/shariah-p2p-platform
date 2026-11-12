<?php

namespace App\Http\Controllers;

use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Models\KycDocument;
use App\Services\KycDocumentStore;
use App\Support\VerificationChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * KYC documents (PB-09, PB-10). index and store are mounted in both the borrower and the
 * investor portal groups, so the role gate has already run; show is shared with admins and
 * guarded by KycDocumentPolicy.
 */
class KycDocumentController extends Controller
{
    /**
     * The signed-in user's own documents and the upload form.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $role = $user->role->name;

        return Inertia::render('Documents/Index', [
            'documents' => $user->kycDocuments()->latest('id')->get()->map->toListItem(),
            'documentTypes' => array_map(
                fn (string $type) => ['value' => $type, 'label' => KycDocument::TYPE_LABELS[$type]],
                KycDocument::typesFor($role),
            ),
            'checklist' => VerificationChecklist::for($user)->toArray(),
            'locked' => ! $user->canEditVerificationDetails(),
            'uploadUrl' => route("{$role}.documents.store"),
            'maxBytes' => KycDocument::MAX_KILOBYTES * 1024,
            'tooLargeMessage' => StoreKycDocumentRequest::TOO_LARGE,
        ]);
    }

    public function store(StoreKycDocumentRequest $request, KycDocumentStore $store): RedirectResponse
    {
        $user = $request->user();

        $store->store($user, $request->file('file'), $request->validated('document_type'));
        $user->reopenReviewIfRejected();

        return to_route("{$user->role->name}.documents.index")
            ->with('status', 'Document uploaded. An administrator will check it when they review your account.');
    }

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
