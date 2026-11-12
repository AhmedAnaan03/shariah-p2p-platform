<?php

namespace App\Models;

use Database\Factories\KycDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Review fields (status, reviewed_by, reviewed_at, rejection_reason) are set by admin code,
// not mass-assigned from an upload form. storage_path is only ever set by KycDocumentStore.
#[Fillable(['document_type', 'original_name', 'storage_path', 'mime_type', 'size_bytes'])]
// The path on the private disk never leaves the server: React gets the controller URL instead.
#[Hidden(['storage_path'])]
class KycDocument extends Model
{
    /** @use HasFactory<KycDocumentFactory> */
    use HasFactory;

    /**
     * Plain-language names for the document_type enum. ('other' exists in the schema but is
     * not offered in this item.)
     */
    public const TYPE_LABELS = [
        'national_id' => 'National ID card',
        'business_registration' => 'Business registration certificate',
        'financial_statement' => 'Financial statement',
        'proof_of_address' => 'Proof of address',
        'other' => 'Other document',
    ];

    /**
     * Which document types each role may upload.
     */
    public const TYPES_BY_ROLE = [
        Role::BORROWER => ['national_id', 'business_registration', 'financial_statement', 'proof_of_address'],
        Role::INVESTOR => ['national_id', 'proof_of_address'],
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
        ];
    }

    /**
     * @return list<string>
     */
    public static function typesFor(string $role): array
    {
        return self::TYPES_BY_ROLE[$role] ?? [];
    }

    /**
     * Whether this document still counts towards verification: waiting for review or approved.
     * A rejected document has to be replaced by a new upload.
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
