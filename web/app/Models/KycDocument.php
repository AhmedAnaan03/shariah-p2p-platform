<?php

namespace App\Models;

use Database\Factories\KycDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Review fields (status, reviewed_by, reviewed_at, rejection_reason) are set by admin code,
// not mass-assigned from an upload form. storage_path is only ever set by KycDocumentStore,
// and so is campaign_id (set for a credit report attached to a financing request, PB-14).
// replaced_at is set by KycDocumentStore when a newer upload takes the document's slot (PB-53);
// the slots each user fills are in App\Support\DocumentSlots.
#[Fillable(['document_type', 'original_name', 'storage_path', 'mime_type', 'size_bytes'])]
// The path on the private disk never leaves the server: React gets the controller URL instead.
#[Hidden(['storage_path'])]
class KycDocument extends Model
{
    /** @use HasFactory<KycDocumentFactory> */
    use HasFactory;

    /**
     * The MMA Credit Information System report a borrower can attach to a financing request
     * (PB-14). It always belongs to a request (kyc_documents.campaign_id; a CHECK constraint
     * enforces it), is uploaded on the request form rather than the documents page, and is
     * decided with the request, not with the account.
     */
    public const TYPE_CREDIT_REPORT = 'credit_report';

    /**
     * The optional supporting document of a Mudarabah business report (PB-29). Like a credit
     * report it belongs to a campaign (the same CHECK), and only the business and administrators
     * can open it.
     */
    public const TYPE_BUSINESS_REPORT = 'business_report';

    /**
     * Plain-language names for the document_type enum. ('other' exists in the schema but is
     * not offered yet.)
     */
    public const TYPE_LABELS = [
        'national_id' => 'National ID card',
        'business_registration' => 'Business registration certificate',
        'financial_statement' => 'Financial statement',
        'proof_of_address' => 'Proof of address',
        'other' => 'Other document',
        self::TYPE_CREDIT_REPORT => 'MMA credit report',
        'salary_slip' => 'Salary slip',
        self::TYPE_BUSINESS_REPORT => 'Business report document',
    ];

    /**
     * Upload limits. The size is in kilobytes because that is what Laravel's `max` rule uses
     * for files: 5120 KB = 5 MB. PHP's own limits (upload_max_filesize, post_max_size) must be
     * higher than this, see web/README.md.
     */
    public const MAX_KILOBYTES = 5120;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'reviewed_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    /**
     * Whether this document still counts towards verification: waiting for review or approved.
     * A rejected document has to be replaced by a new upload in the same slot.
     */
    public function counts(): bool
    {
        return $this->status !== 'rejected';
    }

    /**
     * What a page needs to list this document. The URL points at KycDocumentController@show,
     * which checks the policy; there is no direct link to the file.
     *
     * @return array<string, mixed>
     */
    public function toListItem(): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'type_label' => self::TYPE_LABELS[$this->document_type],
            'original_name' => $this->original_name,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'url' => route('documents.show', $this),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The financing request a credit report belongs to; null for identity documents.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
