<?php

namespace Tests\Feature\Kyc;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesKycFiles;
use Tests\TestCase;

/**
 * PB-09: documents are only viewable by their owner and administrators.
 */
class KycDocumentAccessTest extends TestCase
{
    use MakesKycFiles, RefreshDatabase;

    private User $owner;

    private KycDocument $document;

    private string $original;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(KycDocumentStore::DISK);

        $this->owner = User::factory()->borrower()->create();
        $file = $this->pdfFile('Aishath ID card.pdf');
        $this->original = $file->getContent();
        $this->document = app(KycDocumentStore::class)->store($this->owner, $file, 'national_id');
    }

    public function test_the_owner_can_open_their_document(): void
    {
        $response = $this->actingAs($this->owner)->get("/documents/{$this->document->id}");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename="Aishath ID card.pdf"');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame($this->original, $response->getContent(), 'The decrypted file is returned.');
    }

    public function test_an_admin_can_open_any_document(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get("/documents/{$this->document->id}");

        $response->assertOk();
        $this->assertSame($this->original, $response->getContent());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function otherUsers(): array
    {
        return [
            'another borrower' => ['borrower', false],
            'another, verified borrower' => ['borrower', true],
            'an investor' => ['investor', false],
            'a verified investor' => ['investor', true],
        ];
    }

    #[DataProvider('otherUsers')]
    public function test_anyone_else_gets_404_as_if_the_document_did_not_exist(string $role, bool $verified): void
    {
        $factory = User::factory()->{$role}();
        $someoneElse = ($verified ? $factory->verified() : $factory)->create();

        $response = $this->actingAs($someoneElse)->get("/documents/{$this->document->id}");

        $response->assertNotFound();
        $this->assertStringNotContainsString('%PDF', $response->getContent());
    }

    public function test_a_document_that_does_not_exist_also_gets_404(): void
    {
        // Same answer as for someone else's document, so ids can't be probed.
        $this->actingAs(User::factory()->borrower()->create())->get('/documents/999999')->assertNotFound();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get("/documents/{$this->document->id}")->assertRedirect('/login');
    }

    public function test_a_row_whose_file_is_missing_gives_404_not_an_error(): void
    {
        Storage::disk(KycDocumentStore::DISK)->delete($this->document->storage_path);

        $this->actingAs($this->owner)->get("/documents/{$this->document->id}")->assertNotFound();
    }

    public function test_the_file_cannot_be_reached_by_its_storage_path(): void
    {
        $path = $this->document->storage_path;

        $this->get("/storage/{$path}")->assertNotFound();
        $this->get("/storage/kyc/{$path}")->assertNotFound();
        $this->actingAs($this->owner)->get("/storage/kyc/{$path}")->assertNotFound();
    }

    public function test_no_route_serves_files_straight_from_storage(): void
    {
        $storageRoutes = array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route) => str_starts_with($route->uri(), 'storage'),
        );

        $this->assertSame([], array_map(fn (Route $route) => $route->uri(), array_values($storageRoutes)));
    }
}
