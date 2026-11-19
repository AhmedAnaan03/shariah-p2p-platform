<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerificationDecisionRequest;
use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\VerificationService;
use App\Support\DocumentSlots;
use App\Support\ProfilePresenter;
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
     * Pending borrowers and investors who have completed and submitted every onboarding step
     * (PB-53), oldest registration first. Accounts still completing it are only counted.
     */
    public function index(): Response
    {
        [$ready, $incomplete] = User::awaitingVerification()->get()
            ->partition(fn (User $user) => VerificationChecklist::for($user)->isReadyForReview());

        return Inertia::render('Admin/Verifications/Index', [
            'applicants' => $ready->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name,
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
            'profile' => ProfilePresenter::details($user),
            'documents' => $user->kycDocuments->sortByDesc('id')->values()->map(fn (KycDocument $document) => [
                ...$document->toListItem(),
                'type_label' => DocumentSlots::labelFor($user, $document->document_type),
            ]),
            'missingDocuments' => array_map(fn ($type) => DocumentSlots::labelFor($user, $type), $checklist->missingDocumentTypes()),
            // Anything else the user hasn't finished (PB-53), so the admin knows why Approve is off.
            'missingSteps' => array_values(array_filter([
                $checklist->hasProfile() && ! $checklist->hasCompleteAddress() ? 'The address has not been completed or checked.' : null,
                ! $checklist->hasAcceptedRisk() ? 'The risk acknowledgement has not been accepted.' : null,
                $user->isPending() && ! $checklist->isSubmitted() ? 'The account has not been submitted for verification.' : null,
            ])),
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
}
