<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Frozen route shells for module streams. Each returns 501 until the owning
 * agent stream implements it — this keeps the endpoint index (specs/14) stable
 * and merge conflicts at zero. Agents replace ONLY their own methods/files.
 *
 * Ownership: A=leads/clients(04) B=opportunities(05) C=surveys(06)
 *            D=activities(07) E=followups/notifications(08) F=users/settings(09) G=reports(15)
 */
class ModuleStubController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        return $this->stub($request);
    }

    /** Catch apiResource actions (index/store/show/update/destroy). */
    public function __call($method, $args)
    {
        return $this->stub(request());
    }

    protected function stub(Request $request)
    {
        $owner = [
            'leads' => 'Agent A (specs/04)', 'clients' => 'Agent A (specs/04)',
            'opportunities' => 'Agent B (specs/05)', 'surveys' => 'Agent C (specs/06)',
            'activities' => 'Agent D (specs/07)', 'followups' => 'Agent E (specs/08)',
            'notifications' => 'Agent E (specs/08)', 'users' => 'Agent F (specs/09)',
            'reports' => 'Agent G (specs/15)',
        ];
        $segment = $request->segment(3) ?? 'unknown';

        return $this->fail(
            'Not implemented in base scaffold. Owner: '.($owner[$segment] ?? 'unassigned').'.',
            501
        );
    }
}
