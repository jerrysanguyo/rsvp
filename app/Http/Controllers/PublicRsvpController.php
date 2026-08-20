<?php

namespace App\Http\Controllers;

use App\Models\RsvpLink;
use Illuminate\View\View;

class PublicRsvpController extends Controller
{
    public function show(RsvpLink $rsvpLink): View
    {
        return view('rsvp', [
            'rsvpLink' => [
                'title' => $rsvpLink->title,
                'expires_at' => $rsvpLink->expires_at->toIso8601String(),
                'status' => $rsvpLink->status(),
                'is_available' => $rsvpLink->isAvailable(),
            ],
        ]);
    }
}
