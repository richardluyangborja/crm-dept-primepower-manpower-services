<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Phase 1 company root (specs/03): identity every lead/opp/client hangs off. */
class CompanyController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Company::class);
        $companies = Company::visibleTo($request->user())
            ->filter($request, ['industry', 'owner_id'])
            ->search($request->query('q'), ['name', 'contact_email', 'address_city'])
            ->latest()->paginate(min(100, (int) $request->query('per_page', 15)));

        return $this->paginated(CompanyResource::collection($companies));
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);
        $company->loadCount(['leads', 'clients', 'opportunities']);

        return $this->ok(new CompanyResource($company));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Company::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_province' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);
        $user = $request->user();
        $data['owner_id'] = ($user->role === 'sales_rep' || empty($data['owner_id'])) ? $user->id : $data['owner_id'];
        $company = Company::create($data);

        return $this->created(new CompanyResource($company), 'Company created.');
    }

    public function update(Request $request, Company $company)
    {
        $this->authorize('update', $company);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_province' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'regex:/^\+63\d{10}$/'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);
        $company->update($data);

        return $this->ok(new CompanyResource($company->refresh()), 'Company updated.');
    }

    /** Duplicate-company prompt for lead capture: match by name or phone. */
    public function lookup(Request $request)
    {
        $this->authorize('viewAny', Company::class);
        $name = trim((string) $request->query('name', ''));
        $phone = trim((string) $request->query('phone', ''));
        if ($name === '' && $phone === '') {
            return $this->ok([]);
        }
        $op = Company::visibleTo($request->user())->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $rows = Company::visibleTo($request->user())
            ->where(function ($q) use ($name, $phone, $op) {
                if ($name !== '') {
                    $q->orWhere('name', $op, "%{$name}%");
                }
                if ($phone !== '') {
                    $q->orWhere('contact_phone', $op, "%{$phone}%");
                }
            })
            ->withCount(['leads as open_leads_count' => fn ($q) => $q->whereNotIn('status', ['converted', 'unqualified'])])
            ->orderBy('name')->limit(5)->get();

        return $this->ok(CompanyResource::collection($rows));
    }
}
