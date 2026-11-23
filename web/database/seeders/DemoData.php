<?php

namespace Database\Seeders;

use App\Models\Island;
use App\Models\User;
use App\Services\KycDocumentStore;
use App\Support\DocumentSlots;
use Illuminate\Http\UploadedFile;

/**
 * Realistic dummy data for the demo and screenshot accounts (PB-59): Maldivian addresses,
 * financial profiles and a real (tiny, clearly marked) PDF in every document slot, stored
 * encrypted on the private disk like any upload, so "View" works in the demo.
 *
 * Every value is invented. No real person, ID card or salary slip is used.
 */
final class DemoData
{
    /**
     * [atoll, island, house, road, postcode]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public const ADDRESSES = [
        'male' => ['Malé City', 'Malé', 'H. Fehi Villa', 'Majeedhee Magu', '20094'],
        'male_maafannu' => ['Malé City', 'Malé', 'M. Blue Lagoon', 'Sosun Magu', '20187'],
        'hulhumale' => ['Malé City', 'Hulhumalé', 'Hiyaa Building, flat 4-12', 'Nirolhu Magu', '23000'],
        'addu' => ['Seenu (Addu City)', 'Hithadhoo', 'G. Hiyaa', 'Hithadhoo Magu', '19020'],
        'fuvahmulah' => ['Gnaviyani', 'Fuvahmulah', 'Dhoondigan', 'Male Hingun', '18013'],
        'kulhudhuffushi' => ['Haa Dhaalu', 'Kulhudhuffushi', 'Fenfiyaazuge', 'Dhanbu Magu', '02060'],
    ];

    /**
     * The address fields for a profile.
     *
     * @return array<string, mixed>
     */
    public static function address(string $key): array
    {
        [$atoll, $island, $house, $road, $postcode] = self::ADDRESSES[$key];

        return [
            'address_house' => $house,
            'address_road' => $road,
            'island_id' => Island::idFor($atoll, $island),
            'postcode' => $postcode,
        ];
    }

    /**
     * A borrower's financial profile.
     *
     * @return array<string, mixed>
     */
    public static function finances(int $monthlyIncome, int $repayments, int $years, string $housing, int $openLines = 1, int $late = 0, int $records = 0): array
    {
        return [
            'monthly_income' => $monthlyIncome,
            'monthly_debt_repayments' => $repayments,
            'employment_years' => $years,
            'housing' => $housing,
            'open_credit_lines' => $openLines,
            'late_payments_2y' => $late,
            'public_records' => $records,
        ];
    }

    /**
     * Fill every one of the user's document slots with a demo PDF, then give each the status an
     * administrator would have set (pending while waiting, approved once verified).
     *
     * @param  list<string>  $except  slots to leave empty (an account part-way through)
     */
    public static function documents(User $user, string $status = 'pending', array $except = []): void
    {
        $store = app(KycDocumentStore::class);
        $user->unsetRelations();

        foreach (array_diff(DocumentSlots::typesFor($user), $except) as $type) {
            $label = DocumentSlots::labelFor($user, $type);
            $file = UploadedFile::fake()->createWithContent(str_replace('_', '-', $type).'.pdf', self::pdf("{$label} - {$user->name}"));
            $document = $store->store($user, $file, $type);

            if ($status !== 'pending') {
                // Review fields are set in code, never mass-assigned.
                $document->status = $status;
                $document->reviewed_at = now()->subDays(2);
                $document->save();
            }
        }

        $user->unsetRelations();
    }

    /**
     * A one-page PDF that says what it stands in for and that it is not a real document.
     */
    public static function pdf(string $title): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj 0 -28 Td /F1 12 Tf (Demo document for the Amanah prototype. Not a real document.) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
