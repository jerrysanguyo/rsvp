<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RsvpLinkResource;
use App\Models\RsvpLink;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $rsvpLinks = RsvpLink::query()->latest()->get();

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
            ],
        ]);
    }
}
