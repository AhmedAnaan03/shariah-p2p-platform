<?php

namespace App\Http\Requests\Kyc;

use App\Models\KycDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a KYC document upload (PB-09; investors use it too, PB-10).
 */
class StoreKycDocumentRequest extends FormRequest
{
    public const TOO_LARGE = 'Files must be 5 MB or smaller.';

    public const WRONG_TYPE = 'Upload a PDF, JPG or PNG file.';

    /**
     * Only while the account is pending or rejected; once verified the documents are locked.
     */
    public function authorize(): bool
    {
        return $this->user()->canEditVerificationDetails();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Your account has been verified, so your documents can no longer be changed.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = implode(',', KycDocument::ALLOWED_EXTENSIONS);

        return [
            // Each role has its own list (an investor can't upload a business registration).
            'document_type' => ['required', 'string', Rule::in(KycDocument::typesFor($this->user()->role->name))],
            'file' => [
                'required',
                'file',
                // mimes: reads the file's content to work out its real type, so a .php or
                // .html file renamed to .pdf fails. extensions: the name must match too.
                "mimes:{$extensions}",
                "extensions:{$extensions}",
                // In kilobytes: 5120 KB = 5 MB.
                'max:'.KycDocument::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.required' => 'Choose what kind of document this is.',
            'document_type.string' => 'Choose what kind of document this is.',
            'document_type.in' => 'Choose what kind of document this is from the list.',
            'file.required' => 'Choose a file to upload.',
            'file.file' => 'Choose a file to upload.',
            // PHP refused the file before Laravel saw it; by far the commonest cause is that
            // it was larger than upload_max_filesize.
            'file.uploaded' => 'The file could not be uploaded. '.self::TOO_LARGE,
            'file.mimes' => self::WRONG_TYPE,
            'file.extensions' => self::WRONG_TYPE,
            'file.max' => self::TOO_LARGE,
        ];
    }
}
