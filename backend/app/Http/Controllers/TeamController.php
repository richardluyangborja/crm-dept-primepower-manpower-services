<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/** Step 6 (specs/09): Organization — teams list for all, manage for admin+. */
class TeamController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $this->authorize('viewAny', Team::class);

        return $this->ok(TeamResource::collection(Team::withCount('users')->orderBy('name')->get()));
    }

    public function store(StoreTeamRequest $request)
    {
        $this->authorize('create', Team::class);
        $team = Team::create($request->validated());
        $team->audit('created', $request->user()->id, []);

        return $this->created(new TeamResource($team), 'Team created.');
    }

    public function show(Team $team)
    {
        $this->authorize('view', $team);

        return $this->ok(new TeamResource($team->loadCount('users')));
    }

    public function update(Request $request, Team $team)
    {
        $this->authorize('update', $team);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:teams,name,'.$team->id],
            'region' => ['nullable', 'string', 'max:100'],
        ]);
        $team->update($data);
        $team->audit('updated', $request->user()->id, ['fields' => array_keys($data)]);

        return $this->ok(new TeamResource($team->refresh()), 'Team updated.');
    }
}
