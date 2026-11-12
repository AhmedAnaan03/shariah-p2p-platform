<?php

namespace App\Services;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes and reads KYC files on the private 'kyc' disk (PB-09). The only class that touches
 * the files themselves, so every file is encrypted on the way in and decrypted on the way out.
 */
class KycDocumentStore
{
    public const DISK = 'kyc';

    /**
     * Save an uploaded (already validated) file for its owner and record it.
     */
    public function store(User $owner, UploadedFile $file, string $documentType): KycDocument
    {
        // The name on disk is random, never the uploader's file name: it can't be guessed,
        // can't contain "../" and can't overwrite another file. guessExtension() and
        // getMimeType() read the file's actual content, not the name or the browser's claim.
        $path = $owner->id.'/'.Str::random(40).'.'.$file->guessExtension();

        // Encrypted with APP_KEY (AES-256) before it touches the disk, so a copied disk or
        // backup is unreadable without the key. Same protection as national_id_no in MySQL.
        $this->disk()->put($path, Crypt::encryptString($file->get()));

        try {
            return $owner->kycDocuments()->create([
                'document_type' => $documentType,
                'original_name' => self::displayName($file->getClientOriginalName()),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        } catch (Throwable $e) {
            // No row means no way to reach the file, so don't leave it behind.
            $this->disk()->delete($path);

            throw $e;
        }
    }

    /**
     * The decrypted bytes of a document. Callers must have checked KycDocumentPolicy first.
     */
    public function contents(KycDocument $document): string
    {
        return Crypt::decryptString($this->disk()->get($document->storage_path));
    }

    public function exists(KycDocument $document): bool
    {
        return $this->disk()->exists($document->storage_path);
    }

    /**
     * The uploader's file name, kept only as a label: path separators and control characters
     * removed, at most 255 characters (the column size).
     */
    public static function displayName(string $name): string
    {
        $clean = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', $name) ?? '');

        return mb_substr($clean === '' ? 'document' : $clean, 0, 255);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
