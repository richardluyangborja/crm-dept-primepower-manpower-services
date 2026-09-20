<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/** Standard API envelopes — the ONLY shapes agents may return (see specs/14). */
trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], $code);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    protected function paginated(mixed $paginator, ?string $message = null): JsonResponse
    {
        // Resource collections shape each row (specs/14) while keeping the
        // flat meta envelope ({current_page, per_page, total, ...}) the SPA
        // relies on. Raw paginators still work for scalar-only lists.
        if ($paginator instanceof \Illuminate\Http\Resources\Json\AnonymousResourceCollection) {
            $payload = $paginator->response()->getData(true);
            $response = ['data' => $payload['data'] ?? [], 'meta' => $payload['meta'] ?? []];
        } else {
            $payload = $paginator->toArray();
            $response = ['data' => $payload['data'], 'meta' => collect($payload)->except('data')->toArray()];
        }
        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }

    protected function fail(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }
}
