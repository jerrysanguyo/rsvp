<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rsvp\StoreRsvpResponseRequest;
use App\Models\RsvpLink;
use App\Services\RsvpSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicRsvpController extends Controller
{
    public function show(RsvpLink $rsvpLink): View
    {
        return view('rsvp', [
            'rsvpLink' => [
                'title' => $rsvpLink->title,
                'event_date' => $rsvpLink->event_date?->format('Y-m-d'),
                'event_time' => $rsvpLink->event_time,
                'venue' => $rsvpLink->venue,
                'venue_map_url' => $rsvpLink->venue_map_url,
                'expires_at' => $rsvpLink->expires_at->toIso8601String(),
                'status' => $rsvpLink->status(),
                'is_available' => $rsvpLink->isAvailable(),
                'submission_url' => route('rsvp.store', $rsvpLink),
            ],
        ]);
    }

    public function store(
        StoreRsvpResponseRequest $request,
        RsvpLink $rsvpLink,
        RsvpSubmissionService $submissionService,
    ): JsonResponse {
        $result = $submissionService->submit($request, $rsvpLink, $request->payload());
        $response = $result['response']->loadMissing('participants');

        return response()->json([
            'message' => $response->will_attend
                ? 'Your royal RSVP has been received. We look forward to celebrating with you!'
                : 'Thank you for letting us know. Your response has been received.',
            'data' => [
                'will_attend' => $response->will_attend,
                'participant_count' => $response->participant_count,
                'participants' => $response->participants
                    ->map(fn ($participant): array => ['full_name' => $participant->full_name])
                    ->values(),
            ],
        ], $result['replayed'] ? 200 : 201);
    }
}
