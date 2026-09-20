<?php

namespace App\Policies;

use App\Models\SurveyTemplate;
use App\Models\User;

class SurveyTemplatePolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, SurveyTemplate $t): bool { return true; }
    public function create(User $user): bool { return in_array($user->role, ['manager', 'admin', 'superadmin'], true); }
    public function update(User $user, SurveyTemplate $t): bool { return in_array($user->role, ['manager', 'admin', 'superadmin'], true); }
    public function delete(User $user, SurveyTemplate $t): bool { return in_array($user->role, ['admin', 'superadmin'], true); }
}
