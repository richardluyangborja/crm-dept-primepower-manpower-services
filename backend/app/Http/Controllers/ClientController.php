<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ContactResource;
use App\Models\Client;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 1 (specs/04): client CRUD + contacts + 360 header data. */
class ClientController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        $clients = Client::visibleTo($request->user())
            ->filter($request, ['status', 'industry', 'owner_id'])
            ->search($request->query('q'), ['name', 'contact_email', 'address_city'])
            ->latest()->paginate(min(100, (int) $request->query('per_page', 15)));

        return $this->paginated(ClientResource::collection($clients));
    }

    public function store(StoreClientRequest $request)
    {
        $this->authorize('create', Client::class);
        $data = $request->validated();
        $user = $request->user();
        if ($user->role === 'sales_rep' || empty($data['owner_id'])) {
            $data['owner_id'] = $user->id;
        }
        $client = Client::create($data);
        $client->audit('created', $user->id, []);

        return $this->created(new ClientResource($client), 'Client created.');
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);
        $client->load('contacts');

        return $this->ok(new ClientResource($client));
    }

    /**
     * Operations read-backs (specs/18 §3A): mock deployment headcount,
     * mock AR balance, and job-order pipeline counts — surfaced, not buried.
     */
    public function operations(
        Client $client,
        \App\Services\Contracts\WorkforceServiceInterface $workforce,
        \App\Services\Contracts\BillingServiceInterface $billing
    ) {
        $this->authorize('view', $client);
        $jobs = \App\Models\JobOrder::where('client_id', $client->id)->get();

        return $this->ok([
            'deployment' => $workforce->headcountByClient($client->id) + ['mock' => true],
            'billing' => $billing->paymentStatus($client->id) + ['mock' => true],
            'fulfillment' => \App\Services\Insights\ClientFulfillment::forClient($client),
            'job_orders' => [
                'count' => $jobs->count(),
                'active' => $jobs->whereNotIn('status', ['billed'])->count(),
                'by_status' => $jobs->groupBy('status')->map->count(),
            ],
            'mock' => true,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);
        $data = $request->validated();
        $client->update($data);
        $client->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new ClientResource($client->refresh()), 'Client updated.');
    }

    public function destroy(Request $request, Client $client, \App\Services\StepUpService $stepUp)
    {
        $this->authorize('delete', $client);
        // Destructive + irreversible = step-up (specs/16).
        if (! $stepUp->consume($request->header('X-StepUp-Token'), $request->user()->id)) {
            return response()->json(['message' => 'Deleting a client needs a fresh verification code.', 'otp_required' => true], 428);
        }
        $client->delete();
        $client->audit('deleted', auth('api')->id(), []);

        return $this->ok(null, 'Client archived.');
    }

    public function contacts(Client $client)
    {
        $this->authorize('view', $client);

        return $this->ok(ContactResource::collection($client->contacts()->orderByDesc('is_primary')->get()));
    }

    public function storeContact(StoreContactRequest $request, Client $client)
    {
        $this->authorize('update', $client);
        $contact = $client->contacts()->create($request->validated());
        if ($contact->is_primary) {
            $client->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }
        $client->audit('contact_added', $request->user()->id, ['contact_id' => $contact->id]);

        return $this->created(new ContactResource($contact), 'Contact added.');
    }
}
