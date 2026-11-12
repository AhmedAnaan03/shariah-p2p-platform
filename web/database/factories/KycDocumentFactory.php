<?php

namespace Database\Factories;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A document row only, with no file on disk. Tests that open the file upload one through
 * KycDocumentStore instead.
 *
 * @extends Factory<KycDocument>
 */
class KycDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->borrower(),
            'document_type' => 'national_id',
            'original_name' => 'id-card.pdf',
            'storage_path' => fn (array $attributes) => $attributes['user_id'].'/'.Str::random(40).'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 120_000,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['document_type' => $type]);
    }

    public function rejected(string $reason = 'The image is unreadable.'): static
    {
        return $this->state(fn () => ['status' => 'rejected', 'rejection_reason' => $reason]);
    }
}
