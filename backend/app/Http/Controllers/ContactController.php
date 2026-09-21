<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContactResource;
use App\Models\Client;
use App\Models\Contact;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Cross-client contacts directory (specs/04 hub): read-only, client-scoped visibility. */
class ContactController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        $user = $request->user();

        $op = (new Contact)->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $contacts = Contact::with('client:id,name')
            ->whereHas('client', fn ($q) => $q->visibleTo($user))
            ->when($request->query('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->query('q'), function ($q, $term) use ($op) {
                $q->where(function ($w) use ($term, $op) {
                    $w->where('full_name', $op, "%{$term}%")
                        ->orWhere('email', $op, "%{$term}%")
                        ->orWhere('phone', $op, "%{$term}%")
                        ->orWhere('position', $op, "%{$term}%")
                        ->orWhereHas('client', fn ($c) => $c->where('name', $op, "%{$term}%"));
                });
            })
            ->orderByDesc('is_primary')->orderBy('full_name')
            ->paginate(min(100, (int) $request->query('per_page', 15)));

        return $this->paginated(ContactResource::collection($contacts));
    }
}
