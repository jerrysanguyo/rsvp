<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RsvpLinks\DestroyRsvpLinkRequest;
use App\Http\Requests\Admin\RsvpLinks\StoreRsvpLinkRequest;
use App\Http\Requests\Admin\RsvpLinks\UpdateRsvpLinkRequest;
use App\Http\Resources\RsvpLinkResource;
use App\Models\RsvpLink;
use App\Services\CrudService;
use Illuminate\Http\JsonResponse;

class RsvpLinkController extends Controller
{
    public function __construct(private readonly CrudService $crudService) {}

    public function store(StoreRsvpLinkRequest $request): JsonResponse
    {
        $link = $this->crudService->store(
            $request,
            new RsvpLink,
            [...$request->payload(), 'created_by' => $request->user()->getKey()],
            'RSVP link created',
        );

        return (new RsvpLinkResource($link))
            ->additional(['message' => 'RSVP link created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRsvpLinkRequest $request, RsvpLink $rsvpLink): JsonResponse
    {
        $rsvpLink = $this->crudService->update(
            $request,
            $rsvpLink,
            $request->payload(),
            'RSVP link updated',
        );

        return (new RsvpLinkResource($rsvpLink))
            ->additional(['message' => 'RSVP link updated successfully.'])
            ->response();
    }

    public function destroy(DestroyRsvpLinkRequest $request, RsvpLink $rsvpLink): JsonResponse
    {
        $this->crudService->destroy($request, $rsvpLink, 'RSVP link deleted');

        return response()->json([
            'message' => 'RSVP link removed successfully.',
        ]);
    }
}
