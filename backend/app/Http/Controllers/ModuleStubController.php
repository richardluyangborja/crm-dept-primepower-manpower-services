<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * RETIRED after Step 7: every module stream is implemented and no route
 * references this controller anymore. Kept (not deleted) so git history
 * shows the scaffold-to-feature progression. Do not add new stubs here.
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
        $owner = [];
        $segment = $request->segment(3) ?? 'unknown';

        return $this->fail(
            'Not implemented in base scaffold. Owner: '.($owner[$segment] ?? 'unassigned').'.',
            501
        );
    }
}
