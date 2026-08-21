<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Participants\DestroyRsvpParticipantRequest;
use App\Models\RsvpParticipant;
use App\Services\RsvpParticipantService;
use Illuminate\Http\JsonResponse;

class RsvpParticipantController extends Controller
{
    public function destroy(
        DestroyRsvpParticipantRequest $request,
        RsvpParticipant $rsvpParticipant,
        RsvpParticipantService $participantService,
    ): JsonResponse {
        $participantService->destroy($request, $rsvpParticipant);

        return response()->json([
            'message' => 'Participant removed successfully.',
        ]);
    }
}
