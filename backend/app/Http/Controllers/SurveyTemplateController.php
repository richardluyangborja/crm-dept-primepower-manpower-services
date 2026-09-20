<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyTemplateRequest;
use App\Http\Requests\UpdateSurveyTemplateRequest;
use App\Http\Resources\SurveyTemplateResource;
use App\Models\SurveyTemplate;
use App\Traits\ApiResponse;

/** Step 5 (specs/06): templates — manager+ creates, all view. */
class SurveyTemplateController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $this->authorize('viewAny', SurveyTemplate::class);
        $templates = SurveyTemplate::orderBy('name')->get();

        return $this->ok(SurveyTemplateResource::collection($templates));
    }

    public function store(StoreSurveyTemplateRequest $request)
    {
        $this->authorize('create', SurveyTemplate::class);
        $template = SurveyTemplate::create($request->validated());

        return $this->created(new SurveyTemplateResource($template), 'Template ready — send to a client.');
    }

    public function show(SurveyTemplate $surveyTemplate)
    {
        $this->authorize('view', $surveyTemplate);

        return $this->ok(new SurveyTemplateResource($surveyTemplate));
    }

    public function update(UpdateSurveyTemplateRequest $request, SurveyTemplate $surveyTemplate)
    {
        $this->authorize('update', $surveyTemplate);
        $surveyTemplate->update($request->validated());

        return $this->ok(new SurveyTemplateResource($surveyTemplate->refresh()), 'Template updated.');
    }

    public function destroy(SurveyTemplate $surveyTemplate)
    {
        $this->authorize('delete', $surveyTemplate);
        $surveyTemplate->delete();

        return $this->ok(null, 'Template removed.');
    }
}
