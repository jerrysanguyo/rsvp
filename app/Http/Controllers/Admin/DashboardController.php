<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RsvpLinkResource;
use App\Models\RsvpLink;
use App\Models\RsvpParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $rsvpLinks = RsvpLink::query()->latest()->get();
        $participants = RsvpParticipant::query()
            ->with('response.rsvpLink')
            ->latest()
            ->get()
            ->map(function (RsvpParticipant $participant): array {
                $nameParts = preg_split('/\s+/u', trim($participant->full_name)) ?: [];
                $initials = collect($nameParts)
                    ->when(count($nameParts) > 1, fn ($parts) => $parts->only([0, count($nameParts) - 1]))
                    ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
                    ->implode('');

                return [
                    'id' => $participant->id,
                    'name' => $participant->full_name,
                    'initials' => $initials,
                    'attendance' => $participant->will_attend ? 'attending' : 'declined',
                    'invitation' => $participant->response->rsvpLink->title,
                    'submittedAt' => $participant->response->submitted_at->toIso8601String(),
                    'submittedLabel' => $participant->response->submitted_at->format('M j, g:i A'),
                    'destroy_url' => route('admin.participants.destroy', $participant),
                ];
            });

        return view('admin.app', [
            'page' => 'dashboard',
            'payload' => [
                'user' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
                'logoutUrl' => route('admin.logout'),
                'rsvpLinks' => RsvpLinkResource::collection($rsvpLinks)->resolve($request),
                'rsvpLinkStoreUrl' => route('admin.rsvp-links.store'),
                'participants' => $participants,
            ],
        ]);
    }
}
