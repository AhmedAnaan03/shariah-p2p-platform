<?php

namespace App\Http\Controllers;

use App\Http\Requests\Investor\RiskAcknowledgementRequest;
use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Http\Requests\Onboarding\AddressRequest;
use App\Http\Requests\Profile\BorrowerProfileRequest;
use App\Http\Requests\Profile\InvestorProfileRequest;
use App\Models\Atoll;
use App\Models\AuditLog;
use App\Models\BorrowerProfile;
use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\KycDocumentStore;
use App\Support\DocumentSlots;
use App\Support\OnboardingSteps;
use App\Support\ProfilePresenter;
use App\Support\RiskAcknowledgement;
use App\Support\VerificationChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The onboarding wizard (PB-53): profile → address → documents → (investors) risk
 * acknowledgement → review and submit for verification.
 *
 * Mounted in both the borrower and the investor portal groups, so the role gate has already
 * run; the user's role picks the steps and the profile. Every save redirects to the next step
 * (or back to the same one), never out of the wizard. Earlier steps can be changed until the
 * account is verified: the form requests refuse saves once it is (canEditVerificationDetails).
 */
class OnboardingController extends Controller
{
    /**
     * /onboarding: resume at the first step that isn't done.
     */
    public function start(Request $request): RedirectResponse
    {
        $steps = OnboardingSteps::for(self::user($request));

        return redirect($steps->url($steps->current()));
    }

    public function show(Request $request, string $step): Response|RedirectResponse
    {
        $user = self::user($request);
        $steps = OnboardingSteps::for($user);

        abort_unless($steps->has($step), 404);

        // A later step than the user has reached: take them to the one they are on.
        if (! $steps->canOpen($step)) {
            return redirect($steps->url($steps->current()));
        }

        return Inertia::render('Onboarding/'.Str::studly($step), [
            'wizard' => [
                'steps' => $steps->toArray(),
                'step' => $step,
                'backUrl' => ($previous = $steps->previous($step)) ? $steps->url($previous) : null,
                'nextUrl' => ($next = $steps->next($step)) && $steps->canOpen($next) ? $steps->url($next) : null,
                'locked' => ! $user->canEditVerificationDetails(),
                'role' => $user->role->name,
            ],
            ...match ($step) {
                OnboardingSteps::PROFILE => $this->profileProps($user),
                OnboardingSteps::ADDRESS => $this->addressProps($user),
                OnboardingSteps::DOCUMENTS => $this->documentProps($user),
                OnboardingSteps::RISK => $this->riskProps($user),
                OnboardingSteps::REVIEW => $this->reviewProps($user),
            },
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $user = self::user($request);

        // Resolving a form request from the container runs its authorisation and validation,
        // exactly as if it were type-hinted; the role decides which one.
        if ($user->hasRole(Role::BORROWER)) {
            $form = app(BorrowerProfileRequest::class);
            $user->borrowerProfile()->updateOrCreate([], $form->profileData());
        } else {
            $form = app(InvestorProfileRequest::class);
            $user->investorProfile()->updateOrCreate([], $form->profileData());
        }

        return $this->continueFrom($user, OnboardingSteps::PROFILE, 'Your details have been saved.');
    }

    public function saveAddress(AddressRequest $request): RedirectResponse
    {
        $user = self::user($request);
        if ($redirect = $this->unlessReached($user, OnboardingSteps::ADDRESS)) {
            return $redirect;
        }

        $profile = VerificationChecklist::for($user)->profile();
        $profile->fill($request->addressData());
        $profile->save();

        return $this->continueFrom($user, OnboardingSteps::ADDRESS, 'Your address has been saved.');
    }

    /**
     * Upload into one slot, or replace what is in it. Stays on the documents step.
     */
    public function uploadDocument(StoreKycDocumentRequest $request, KycDocumentStore $store): RedirectResponse
    {
        $user = self::user($request);
        if ($redirect = $this->unlessReached($user, OnboardingSteps::DOCUMENTS)) {
            return $redirect;
        }

        $type = $request->validated('document_type');
        $replacing = $user->kycDocuments()->where('document_type', $type)->exists();
        $store->storeInSlot($user, $request->file('file'), $type);
        $label = DocumentSlots::labelFor($user, $type);

        return redirect(OnboardingSteps::for($user)->url(OnboardingSteps::DOCUMENTS))
            ->with('status', $replacing ? "{$label} replaced." : "{$label} uploaded.");
    }

    /**
     * "Continue" on the documents step: only once every slot is filled.
     */
    public function saveDocuments(Request $request): RedirectResponse
    {
        $user = self::user($request);
        if ($redirect = $this->unlessReached($user, OnboardingSteps::DOCUMENTS)) {
            return $redirect;
        }

        $missing = VerificationChecklist::for($user)->missingDocumentTypes();
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'documents' => 'Upload every document before you continue. Still needed: '
                    .implode(', ', array_map(fn ($type) => DocumentSlots::labelFor($user, $type), $missing)).'.',
            ]);
        }

        return $this->continueFrom($user, OnboardingSteps::DOCUMENTS);
    }

    /**
     * The investor's last step before review (PB-10, moved into the wizard by PB-53). Accepting
     * twice keeps the first date: that is when the investor first agreed.
     */
    public function acceptRisk(RiskAcknowledgementRequest $request): RedirectResponse
    {
        $user = self::user($request);
        if ($redirect = $this->unlessReached($user, OnboardingSteps::RISK)) {
            return $redirect;
        }

        $profile = $user->investorProfile;

        if ($profile->risk_acknowledged_at === null) {
            DB::transaction(function () use ($profile) {
                // Not mass-assignable: set in code, never from the request.
                $profile->risk_acknowledged_at = now();
                $profile->save();

                AuditLog::record('investor.risk_acknowledged', $profile, [
                    'text_version' => RiskAcknowledgement::VERSION,
                ]);
            });
        }

        return $this->continueFrom($user, OnboardingSteps::RISK, 'Thank you. You have accepted the risk acknowledgement.');
    }

    /**
     * Review and submit: sends a complete account to the admin queue.
     */
    public function submit(Request $request): RedirectResponse
    {
        $user = self::user($request);
        abort_unless($user->canEditVerificationDetails(), 403, 'Your account has been verified, so there is nothing to submit.');

        $steps = OnboardingSteps::for($user);
        if (! VerificationChecklist::for($user)->isComplete()) {
            return redirect($steps->url($steps->current()))
                ->withErrors(['submit' => 'Finish this step before you submit your account for verification.']);
        }

        $wasRejected = $user->status === 'rejected';
        DB::transaction(function () use ($user, $wasRejected) {
            $user->submitForVerification();
            AuditLog::record('user.submitted_for_verification', $user, ['resubmitted_after_rejection' => $wasRejected]);
        });

        return redirect($steps->url(OnboardingSteps::REVIEW))
            ->with('status', 'Submitted. An administrator will check your details and documents, usually within two working days.');
    }

    /**
     * After a save: on to the next step, or the same one if it was the last.
     */
    private function continueFrom(User $user, string $step, ?string $message = null): RedirectResponse
    {
        // The step just saved changed the profile or documents, so read them again.
        $steps = OnboardingSteps::for($user->unsetRelations());
        $next = $steps->next($step);
        $redirect = redirect($steps->url($next !== null && $steps->canOpen($next) ? $next : $steps->current()));

        return $message ? $redirect->with('status', $message) : $redirect;
    }

    /**
     * A save for a step the user can't open yet (e.g. an address before the profile exists)
     * goes back to the step they are on instead.
     */
    private function unlessReached(User $user, string $step): ?RedirectResponse
    {
        $steps = OnboardingSteps::for($user);

        return $steps->canOpen($step) ? null : redirect($steps->url($steps->current()));
    }

    /**
     * @return array<string, mixed>
     */
    private function profileProps(User $user): array
    {
        if ($user->hasRole(Role::INVESTOR)) {
            return [
                'profile' => $user->investorProfile?->makeVisible('national_id_no')->only(['phone', 'national_id_no']),
            ];
        }

        return [
            // The owner sees their own ID number to edit it, so it is made visible here only.
            'profile' => $user->borrowerProfile?->makeVisible('national_id_no')->only([
                'borrower_type', 'phone', 'national_id_no', 'business_name', 'business_registration_no',
                'sector', 'years_operating', 'declared_annual_income',
            ]),
            'borrowerTypes' => self::options(BorrowerProfile::TYPES),
            'sectors' => self::options(BorrowerProfile::SECTORS),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addressProps(User $user): array
    {
        $profile = VerificationChecklist::for($user)->profile();
        $profile->loadMissing('island');

        return [
            'address' => [
                'address_house' => $profile->address_house ?? '',
                'address_road' => $profile->address_road ?? '',
                'atoll_id' => $profile->island ? (string) $profile->island->atoll_id : '',
                'island_id' => $profile->island_id ? (string) $profile->island_id : '',
                'postcode' => $profile->postcode ?? '',
            ],
            'atolls' => Atoll::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentProps(User $user): array
    {
        $role = $user->role->name;

        return [
            'slots' => DocumentSlots::forPage($user),
            'uploadUrl' => route("{$role}.onboarding.documents.upload"),
            'continueUrl' => route("{$role}.onboarding.documents.save"),
            'maxBytes' => KycDocument::MAX_KILOBYTES * 1024,
            'tooLargeMessage' => StoreKycDocumentRequest::TOO_LARGE,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function riskProps(User $user): array
    {
        return [
            'acknowledgement' => RiskAcknowledgement::toArray(),
            'acknowledgedAt' => $user->investorProfile?->risk_acknowledged_at?->toIso8601String(),
            'acceptUrl' => route('investor.onboarding.risk'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewProps(User $user): array
    {
        $checklist = VerificationChecklist::for($user);
        $steps = OnboardingSteps::for($user);

        return [
            'details' => ProfilePresenter::details($user),
            'documents' => $user->kycDocuments->map(fn (KycDocument $document) => [
                ...$document->toListItem(),
                'type_label' => DocumentSlots::labelFor($user, $document->document_type),
            ])->values(),
            'complete' => $checklist->isComplete(),
            'submittedAt' => $user->verification_submitted_at?->toIso8601String(),
            'status' => $user->status,
            'editLinks' => collect($steps->steps())
                ->reject(fn ($step) => $step === OnboardingSteps::REVIEW)
                ->mapWithKeys(fn ($step) => [$step => $steps->url($step)]),
            'submitUrl' => route("{$user->role->name}.onboarding.submit"),
        ];
    }

    /**
     * The signed-in user with no relations cached. Earlier code in the request (the shared
     * Inertia props) may already have loaded the profile or documents, and a step must see what
     * is in the database now.
     */
    private static function user(Request $request): User
    {
        return $request->user()->unsetRelations();
    }

    /**
     * ['key' => 'Label'] → [{ value, label }] for the Select and RadioCards components.
     *
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $labels): array
    {
        return array_map(fn ($value, $label) => ['value' => $value, 'label' => $label], array_keys($labels), $labels);
    }
}
