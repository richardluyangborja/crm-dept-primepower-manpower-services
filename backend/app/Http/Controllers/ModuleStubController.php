<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Frozen route shells for module streams. Each returns 501 until the owning
 * agent stream implements it — this keeps the endpoint index (specs/14) stable
 * and merge conflicts at zero. Agents replace ONLY their own methods/files.
 *
 * Ownership (sequential build, specs/17): step 6=users/settings(09) step 7=reports(15)
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
            'reports' => 'Step 7 (specs/15)',
        ];
        $segment = $request->segment(3) ?? 'unknown';

        return $this->fail(
            'Not implemented in base scaffold. Owner: '.($owner[$segment] ?? 'unassigned').'.',
            501
        );
    }
}
