<?php

namespace Tests\Feature\Kyc;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-09: documents upload to private storage; file type and size are validated.
 */
class KycDocumentUploadTest extends TestCase
{
    use MakesKycFiles, RefreshDatabase;

    private const TOO_LARGE = 'Files must be 5 MB or smaller.';

    private const WRONG_TYPE = 'Upload a PDF, JPG or PNG file.';

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway disk for each test, so real KYC files are never touched.
        Storage::fake(KycDocumentStore::DISK);
    }

    private function upload(User $user, ?UploadedFile $file, string $type = 'national_id')
    {
        return $this->actingAs($user)
            ->from('/borrower/documents')
            ->post('/borrower/documents', array_filter(['document_type' => $type, 'file' => $file]));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function validFiles(): array
    {
        return [
            'PDF' => ['pdfFile', 'application/pdf', 'pdf'],
            'PNG' => ['pngFile', 'image/png', 'png'],
            'JPG' => ['jpgFile', 'image/jpeg', 'jpg'],
        ];
    }

    #[DataProvider('validFiles')]
    public function test_a_pdf_jpg_or_png_is_stored_on_the_private_disk(string $maker, string $mime, string $extension): void
    {
        $borrower = User::factory()->borrower()->create();
        $file = $this->{$maker}();

        $this->upload($borrower, $file)
            ->assertRedirect('/borrower/documents')
            ->assertSessionHas('status');

        $document = KycDocument::sole();
        $this->assertSame($borrower->id, $document->user_id);
        $this->assertSame('national_id', $document->document_type);
        $this->assertSame('pending', $document->status);
        $this->assertSame($mime, $document->mime_type, 'The type is detected from the content.');
        $this->assertSame($file->getSize(), $document->size_bytes);
        $this->assertSame($file->getClientOriginalName(), $document->original_name);

        // Random name in the owner's folder, with the extension of the real type.
        $this->assertMatchesRegularExpression("#^{$borrower->id}/[A-Za-z0-9]{40}\\.{$extension}$#", $document->storage_path);
        Storage::disk(KycDocumentStore::DISK)->assertExists($document->storage_path);
    }

    public function test_the_file_is_encrypted_on_disk(): void
    {
        $borrower = User::factory()->borrower()->create();
        $file = $this->pdfFile();
        $original = $file->getContent();

        $this->upload($borrower, $file);

        $onDisk = Storage::disk(KycDocumentStore::DISK)->get(KycDocument::sole()->storage_path);
        $this->assertNotSame($original, $onDisk);
        $this->assertStringNotContainsString('%PDF', $onDisk, 'The PDF header must not be readable on disk.');
        $this->assertSame($original, Crypt::decryptString($onDisk), 'Decrypting with APP_KEY gives back the original.');
    }

    public function test_the_disk_has_no_public_url_and_is_not_served(): void
    {
        $config = config('filesystems.disks.kyc');

        $this->assertArrayNotHasKey('url', $config);
        $this->assertFalse($config['serve']);
        $this->assertSame('private', $config['visibility']);
        $this->assertStringStartsNotWith(public_path(), $config['root']);
        $this->assertFalse(config('filesystems.disks.local.serve'), "Laravel's storage/{path} route must stay off.");
    }

    public function test_an_upload_exactly_5_mb_is_accepted(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->upload($borrower, $this->pdfFile('statement.pdf', 5 * 1024 * 1024))->assertSessionHasNoErrors();

        $this->assertSame(1, KycDocument::count());
    }

    /**
     * Blank and invalid uploads: [file maker and arguments, document type, field, message].
     *
     * @return array<string, array{0: ?array, 1: string, 2: string, 3: string}>
     */
    public static function invalidUploads(): array
    {
        return [
            'no file' => [null, 'national_id', 'file', 'Choose a file to upload.'],
            'one byte over 5 MB' => [['pdfFile', 'big.pdf', 5 * 1024 * 1024 + 1], 'national_id', 'file', self::TOO_LARGE],
            'Word document' => [['fileWithContent', 'id.docx', "PK\x03\x04 word document"], 'national_id', 'file', self::WRONG_TYPE],
            'plain text' => [['fileWithContent', 'notes.txt', 'my id number is A123456'], 'national_id', 'file', self::WRONG_TYPE],
            'PHP code renamed to .pdf' => [['fileWithContent', 'id-card.pdf', "<?php system(\$_GET['c']);"], 'national_id', 'file', self::WRONG_TYPE],
            'HTML with script renamed to .png' => [['fileWithContent', 'id-card.png', '<!doctype html><script>alert(1)</script>'], 'national_id', 'file', self::WRONG_TYPE],
            'real PDF named .exe' => [['pdfFile', 'id-card.exe'], 'national_id', 'file', self::WRONG_TYPE],
            'blank document type' => [['pdfFile'], '', 'document_type', 'Choose what kind of document this is.'],
            'document type not on the list' => [['pdfFile'], 'passport', 'document_type', 'Choose what kind of document this is from the list.'],
            "'other' is not offered" => [['pdfFile'], 'other', 'document_type', 'Choose what kind of document this is from the list.'],
        ];
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_uploads_are_rejected_and_nothing_is_stored(?array $file, string $type, string $field, string $message): void
    {
        $borrower = User::factory()->borrower()->create();
        $upload = $file ? $this->{$file[0]}(...array_slice($file, 1)) : null;

        $this->upload($borrower, $upload, $type)
            ->assertRedirect('/borrower/documents')
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, KycDocument::count());
        $this->assertSame([], Storage::disk(KycDocumentStore::DISK)->allFiles());
    }

    public function test_a_file_php_refused_as_too_large_gets_the_size_message(): void
    {
        // What Laravel receives when the file is bigger than upload_max_filesize (10M).
        $borrower = User::factory()->borrower()->create();
        $refused = $this->fileWithContent('scan.pdf', '', UPLOAD_ERR_INI_SIZE);

        $this->upload($borrower, $refused)
            ->assertSessionHasErrors(['file' => 'The file could not be uploaded. Files must be 5 MB or smaller.']);
    }

    public function test_folder_parts_and_control_characters_are_removed_from_the_original_name(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->upload($borrower, $this->pdfFile("..\\..\\id\x07card.pdf"))->assertSessionHasNoErrors();

        $this->assertSame('idcard.pdf', KycDocument::sole()->original_name);
        $this->assertStringNotContainsString('..', KycDocument::sole()->storage_path);
    }

    public function test_a_verified_borrowers_documents_are_locked(): void
    {
        $borrower = User::factory()->borrower()->verified()->create();

        $this->upload($borrower, $this->pdfFile())->assertForbidden();

        $this->assertSame(0, KycDocument::count());
    }

    public function test_a_rejected_borrower_who_uploads_goes_back_to_the_review_queue(): void
    {
        $borrower = User::factory()->borrower()->create();
        $borrower->forceFill(['status' => 'rejected', 'status_reason' => 'ID is blurred.'])->save();

        $this->upload($borrower, $this->pngFile())->assertSessionHasNoErrors();

        $this->assertSame('pending_verification', $borrower->fresh()->status);
    }

    public function test_the_documents_page_lists_own_documents_without_their_storage_path(): void
    {
        $borrower = User::factory()->borrower()->create();
        $this->upload($borrower, $this->pdfFile());
        KycDocument::factory()->create(); // someone else's

        $this->actingAs($borrower)->get('/borrower/documents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Index')
                ->has('documents', 1)
                ->has('documents.0', fn (Assert $document) => $document
                    ->where('type_label', 'National ID card')
                    ->where('status', 'pending')
                    ->where('url', route('documents.show', KycDocument::where('user_id', $borrower->id)->sole()))
                    ->missing('storage_path')
                    ->etc()
                )
                ->has('documentTypes', 4)
                ->where('uploadUrl', route('borrower.documents.store'))
                ->where('maxBytes', 5 * 1024 * 1024)
            );
    }

    public function test_only_borrowers_can_use_the_borrower_upload(): void
    {
        foreach (['investor', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)->get('/borrower/documents')->assertForbidden();
            $this->actingAs($user)->post('/borrower/documents', ['document_type' => 'national_id', 'file' => $this->pdfFile()])
                ->assertForbidden();
        }

        $this->assertSame(0, KycDocument::count());
    }
}
