<?php

namespace App\Policies;

use App\Models\KycDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may open a KYC document (PB-09). Laravel finds this class by its name
 * (KycDocument → KycDocumentPolicy), and the controller calls it with
 * Gate::authorize('view', $document).
 */
class KycDocumentPolicy
{
    public function view(User $user, KycDocument $document): Response
    {
        if ($user->id === $document->user_id || $user->hasRole(Role::ADMIN)) {
            return Response::allow();
        }

        // 404 rather than 403: a 403 would confirm that document 57 exists, so someone could
        // count through ids to learn how many documents there are. 404 reveals nothing.
        return Response::denyAsNotFound();
    }
}
