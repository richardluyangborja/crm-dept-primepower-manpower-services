<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Frozen route shells for module streams. Each returns 501 until the owning
 * agent stream implements it — this keeps the endpoint index (specs/14) stable
 * and merge conflicts at zero. Agents replace ONLY their own methods/files.
 *
 * Ownership (sequential build, specs/17): step 2=opportunities(05) step 5=surveys(06)
 *            step 4=activities(07) step 3=followups/notifications(08) step 6=users/settings(09) step 7=reports(15)
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
            'opportunities' => 'Step 2 (specs/05)', 'surveys' => 'Step 5 (specs/06)',
            'activities' => 'Step 4 (specs/07)', 'followups' => 'Step 3 (specs/08)',
            'notifications' => 'Step 3 (specs/08)', 'users' => 'Step 6 (specs/09)',
            'reports' => 'Step 7 (specs/15)',
        ];
        $segment = $request->segment(3) ?? 'unknown';

        return $this->fail(
            'Not implemented in base scaffold. Owner: '.($owner[$segment] ?? 'unassigned').'.',
            501
        );
    }
}
