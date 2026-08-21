<?php

namespace App\Services;

use App\Models\RsvpParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RsvpParticipantService
{
    public function __construct(private readonly CrudService $crudService) {}

    public function destroy(Request $request, RsvpParticipant $participant): void
    {
        DB::transaction(function () use ($request, $participant): void {
            $response = $participant->response()->lockForUpdate()->firstOrFail();

            $this->crudService->destroy($request, $participant, 'RSVP participant deleted');

            $remainingCount = $response->participants()->count();

            if ($remainingCount === 0) {
                $this->crudService->destroy($request, $response, 'Empty RSVP response deleted');

                return;
            }

            $this->crudService->update(
                $request,
                $response,
                ['participant_count' => $remainingCount],
                'RSVP response participant count updated',
            );
        }, attempts: 3);
    }
}
