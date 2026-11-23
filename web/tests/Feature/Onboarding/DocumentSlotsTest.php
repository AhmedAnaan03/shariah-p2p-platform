<?php

namespace Tests\Feature\Onboarding;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesOnboardedUsers;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-53: fixed document slots per kind of user. Each slot shows what it needs, holds one
 * current document, can be replaced, and nothing outside the slots can be uploaded. The
 * existing privacy and encryption still apply (KycDocumentUploadTest, KycDocumentAccessTest).
 */
class DocumentSlotsTest extends TestCase
{
    use CreatesOnboardedUsers, MakesKycFiles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(KycDocumentStore::DISK);
    }

    private function userAtDocuments(string $kind): User
    {
        if ($kind === 'investor') {
            $user = User::factory()->investor()->create();
            InvestorProfile::factory()->for($user)->create();

            return $user;
        }

        $user = User::factory()->borrower()->create();
        ($kind === 'sme' ? BorrowerProfile::factory() : BorrowerProfile::factory()->individual())->for($user)->create();

        return $user;
    }

    private function upload(User $user, string $type, ?string $name = null)
    {
        $role = $user->role->name;

        return $this->actingAs($user)
            ->from("/{$role}/onboarding/documents")
            ->post("/{$role}/onboarding/documents", ['document_type' => $type, 'file' => $this->pdfFile($name ?? "{$type}.pdf")]);
    }

    /**
     * [kind of user, URL prefix, slots in order, first slot's label]
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: string}>
     */
    public static function slotsByKind(): array
    {
        return [
            'individual borrower' => ['individual', 'borrower', ['national_id', 'proof_of_address', 'salary_slip'], 'National ID card'],
            'SME borrower' => ['sme', 'borrower', ['national_id', 'business_registration', 'proof_of_address', 'financial_statement'], 'National ID card'],
            'investor' => ['investor', 'investor', ['national_id', 'proof_of_address'], 'National ID card'],
        ];
    }

    #[DataProvider('slotsByKind')]
    public function test_each_kind_of_user_gets_its_own_fixed_slots_with_what_each_needs(string $kind, string $prefix, array $types, string $firstLabel): void
    {
        $user = $this->userAtDocuments($kind);

        $this->actingAs($user)->get("/{$prefix}/onboarding/documents")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Onboarding/Documents')
                ->where('slots', fn ($slots) => collect($slots)->pluck('type')->all() === $types)
                ->where('slots.0.label', $firstLabel)
                ->where('slots', fn ($slots) => collect($slots)->every(fn ($slot) => $slot['description'] !== '' && $slot['document'] === null))
            );
    }

    public function test_an_sme_slot_says_what_the_business_must_upload(): void
    {
        $this->actingAs($this->userAtDocuments('sme'))->get('/borrower/onboarding/documents')
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.1.label', 'Business registration certificate')
                ->where('slots.2.label', 'Proof of business address')
                ->where('slots.3.label', 'Financial statement')
            );
    }

    /**
     * Uploads outside the user's slots: [kind of user, document type].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function extraUploads(): array
    {
        return [
            'individual uploads a business registration' => ['individual', 'business_registration'],
            'individual uploads a financial statement' => ['individual', 'financial_statement'],
            'investor uploads a financial statement' => ['investor', 'financial_statement'],
            "anyone uploads 'other'" => ['sme', 'other'],
            'anyone uploads a credit report' => ['sme', 'credit_report'],
            'an unknown type' => ['individual', 'passport'],
        ];
    }

    #[DataProvider('extraUploads')]
    public function test_no_upload_is_possible_outside_the_slots(string $kind, string $type): void
    {
        $user = $this->userAtDocuments($kind);

        $this->upload($user, $type)
            ->assertSessionHasErrors(['document_type' => 'Upload into one of the document slots shown. Other documents are not needed.']);

        $this->assertSame(0, KycDocument::count());
        $this->assertSame([], Storage::disk(KycDocumentStore::DISK)->allFiles());
    }

    public function test_a_filled_slot_shows_its_file_with_a_link_to_view_it(): void
    {
        $user = $this->userAtDocuments('individual');
        $this->upload($user, 'national_id', 'my-id.pdf')
            ->assertRedirect('/borrower/onboarding/documents')
            ->assertSessionHas('status', 'National ID card uploaded.');

        $document = KycDocument::sole();
        $this->actingAs($user)->get('/borrower/onboarding/documents')
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.0.document.id', $document->id)
                ->where('slots.0.document.original_name', 'my-id.pdf')
                ->where('slots.0.document.url', route('documents.show', $document))
                ->missing('slots.0.document.storage_path')
            );
        $this->actingAs($user)->get(route('documents.show', $document))->assertOk();
    }

    public function test_replace_puts_the_new_file_in_the_slot_and_keeps_the_old_one_out_of_sight(): void
    {
        $user = $this->userAtDocuments('individual');
        $this->upload($user, 'national_id', 'blurred.pdf');
        $old = KycDocument::sole();

        $this->upload($user, 'national_id', 'sharp.pdf')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'National ID card replaced.');

        $old->refresh();
        $this->assertNotNull($old->replaced_at, 'The old upload is marked replaced, not deleted.');
        Storage::disk(KycDocumentStore::DISK)->assertExists($old->storage_path);
        $this->assertSame(2, KycDocument::where('user_id', $user->id)->count());

        $current = $user->fresh()->kycDocuments;
        $this->assertCount(1, $current);
        $this->assertSame('sharp.pdf', $current->sole()->original_name);
        $this->assertSame('pending', $current->sole()->status);
    }

    public function test_a_rejected_document_can_be_replaced(): void
    {
        $user = $this->onboardedBorrower(['status' => 'rejected']);
        $user->kycDocuments()->where('document_type', 'national_id')->update(['status' => 'rejected', 'rejection_reason' => 'Blurred.']);

        $this->upload($user, 'national_id', 'new-id.pdf')->assertSessionHasNoErrors();

        $current = $user->fresh()->kycDocuments->firstWhere('document_type', 'national_id');
        $this->assertSame('new-id.pdf', $current->original_name);
        $this->assertSame('pending', $current->status);
    }

    public function test_the_replacement_is_encrypted_on_disk_like_every_upload(): void
    {
        $user = $this->userAtDocuments('investor');
        $this->upload($user, 'proof_of_address');
        $this->upload($user, 'proof_of_address');

        foreach (KycDocument::all() as $document) {
            $onDisk = Storage::disk(KycDocumentStore::DISK)->get($document->storage_path);
            $this->assertStringNotContainsString('%PDF', $onDisk);
            $this->assertStringStartsWith('%PDF', Crypt::decryptString($onDisk));
        }
    }

    public function test_the_database_refuses_two_current_documents_in_one_slot(): void
    {
        $user = $this->userAtDocuments('individual');
        KycDocument::factory()->for($user)->ofType('national_id')->create();

        $this->expectException(QueryException::class);
        KycDocument::factory()->for($user)->ofType('national_id')->create();
    }

    public function test_replaced_documents_and_credit_reports_do_not_take_a_slot(): void
    {
        $user = $this->userAtDocuments('individual');
        KycDocument::factory()->for($user)->ofType('national_id')->create(['replaced_at' => now()->subDay()]);
        KycDocument::factory()->for($user)->ofType('national_id')->create(['replaced_at' => now()->subHour()]);
        KycDocument::factory()->for($user)->ofType('national_id')->create();

        $this->assertSame(3, KycDocument::where('user_id', $user->id)->count());
        $this->assertCount(1, $user->fresh()->kycDocuments);
    }

    public function test_continue_needs_every_slot_filled(): void
    {
        $user = $this->userAtDocuments('individual');
        $this->upload($user, 'national_id');

        $this->actingAs($user)->from('/borrower/onboarding/documents')->put('/borrower/onboarding/documents')
            ->assertRedirect('/borrower/onboarding/documents')
            ->assertSessionHasErrors(['documents' => 'Upload every document before you continue. Still needed: Proof of address, Salary slip.']);

        $this->upload($user, 'proof_of_address');
        $this->upload($user, 'salary_slip');
        $this->actingAs($user)->put('/borrower/onboarding/documents')->assertRedirect('/borrower/onboarding/review');
    }

    public function test_switching_to_sme_adds_the_business_slots_and_sends_the_user_back_to_documents(): void
    {
        $user = $this->onboardedBorrower(submitted: false);
        $user->borrowerProfile->update([
            'borrower_type' => 'sme', 'business_name' => 'Reef Catch', 'business_registration_no' => 'C-1/2020',
            'sector' => 'fisheries', 'years_operating' => 3,
        ]);

        $this->actingAs($user)->get('/borrower/onboarding')->assertRedirect('/borrower/onboarding/documents');
        $this->actingAs($user)->get('/borrower/onboarding/documents')
            ->assertInertia(fn (Assert $page) => $page->has('slots', 4)->where('slots.1.document', null));
    }
}
