<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerificationDecisionRequest;
use App\Models\BorrowerProfile;
use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\VerificationService;
use App\Support\VerificationChecklist;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The administrator's verification queue and review page (PB-11). Every route is inside the
 * admin portal group, so only administrators reach this controller.
 */
class VerificationController extends Controller
{
    /**
     * Pending borrowers and investors whose profile and required documents are complete,
     * oldest registration first. Incomplete accounts are only counted.
     */
    public function index(): Response
    {
        [$ready, $incomplete] = User::awaitingVerification()->get()
            ->partition(fn (User $user) => VerificationChecklist::for($user)->isComplete());

        return Inertia::render('Admin/Verifications/Index', [
            'applicants' => $ready->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->role->label,
                'registered_at' => $user->created_at->toIso8601String(),
                'documents_count' => $user->kycDocuments->where('status', 'pending')->count(),
                'url' => route('admin.verifications.show', $user),
            ])->values(),
            'incompleteCount' => $incomplete->count(),
        ]);
    }

    public function show(User $user): Response
    {
        // Administrators are never verified through this queue.
        abort_if($user->hasRole(Role::ADMIN), 404);

        $user->load(['role', 'borrowerProfile', 'investorProfile', 'kycDocuments']);
        $checklist = VerificationChecklist::for($user);

        return Inertia::render('Admin/Verifications/Show', [
            'applicant' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->role->label,
                'status' => $user->status,
                'status_reason' => $user->status_reason,
                'registered_at' => $user->created_at->toIso8601String(),
            ],
            'profile' => self::profileDetails($user),
            'documents' => $user->kycDocuments->sortByDesc('id')->values()->map->toListItem(),
            'missingDocuments' => array_map(fn ($type) => KycDocument::TYPE_LABELS[$type], $checklist->missingDocumentTypes()),
            'canDecide' => $user->isPending(),
            'canApprove' => $checklist->isReadyForReview(),
            'decideUrl' => route('admin.verifications.decide', $user),
        ]);
    }

    public function decide(VerificationDecisionRequest $request, User $user, VerificationService $verifications): RedirectResponse
    {
        abort_if($user->hasRole(Role::ADMIN), 404);

        if ($request->validated('decision') === 'approve') {
            $verifications->approve($user, $request->user());
            $message = "{$user->name} has been verified and notified.";
        } else {
            $verifications->reject($user, $request->user(), $request->validated('reason'));
            $message = "{$user->name} has been rejected and notified of the reason.";
        }

        return to_route('admin.verifications.index')->with('status', $message);
    }

    /**
     * The profile as label/value pairs for the review page, including the decrypted national ID
     * number, which the administrator compares with the ID document. null if there is no profile.
     *
     * @return list<array{label: string, value: mixed}>|null
     */
    private static function profileDetails(User $user): ?array
    {
        $profile = VerificationChecklist::for($user)->profile();

        if ($profile === null) {
            return null;
        }

        $details = [
            ['label' => 'Phone', 'value' => $profile->phone],
            ['label' => 'National ID card number', 'value' => $profile->national_id_no],
            ['label' => 'Address', 'value' => $profile->address],
        ];

        if ($profile instanceof BorrowerProfile) {
            $details = [
                ['label' => 'Borrowing as', 'value' => BorrowerProfile::TYPES[$profile->borrower_type]],
                ...$details,
                ...($profile->isSme() ? [
                    ['label' => 'Business name', 'value' => $profile->business_name],
                    ['label' => 'Business registration number', 'value' => $profile->business_registration_no],
                    ['label' => 'Sector', 'value' => BorrowerProfile::SECTORS[$profile->sector] ?? $profile->sector],
                    ['label' => 'Years operating', 'value' => $profile->years_operating],
                ] : []),
                [
                    'label' => $profile->isSme() ? 'Declared annual revenue' : 'Declared annual income',
                    'value' => 'MVR '.number_format((float) $profile->declared_annual_income, 2),
                ],
            ];
        }

        return $details;
    }
}
