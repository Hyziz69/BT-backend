<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * NTI admins bypass all company-level checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin'], true)) {
            return true;
        }

        return null;
    }

    /**
     * Any member of the company can view it.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    /**
     * Owners and managers can edit the company profile.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->isCompanyManager();
    }

    /**
     * Only the owner can invite, kick and assign roles.
     */
    public function manageMembers(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->isCompanyOwner();
    }

    /**
     * Owners and managers can create / edit challenges on behalf of the company.
     */
    public function manageChallenges(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->isCompanyManager();
    }
}
