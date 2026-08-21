<?php

namespace App\Services;

use App\Models\RsvpLink;
use App\Models\RsvpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RsvpSubmissionService
{
    public function __construct(
        private readonly PlainTextSanitizer $sanitizer,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array{submission_key: string, will_attend: bool, participants: list<array{full_name: string}>}  $payload
     * @return array{response: RsvpResponse, replayed: bool}
     */
    public function submit(Request $request, RsvpLink $rsvpLink, array $payload): array
    {
        $names = array_map(
            fn (array $participant): string => $this->sanitizer->sanitize($participant['full_name'], 'participants.full_name'),
            $payload['participants'],
        );

        if (count(array_unique(array_map('mb_strtolower', $names))) !== count($names)) {
            throw ValidationException::withMessages([
                'participants' => 'Each participant name must be unique in this response.',
            ]);
        }

        return DB::transaction(function () use ($request, $rsvpLink, $payload, $names): array {
            $lockedLink = RsvpLink::query()->whereKey($rsvpLink->getKey())->lockForUpdate()->firstOrFail();

            $existing = RsvpResponse::query()
                ->with('participants')
                ->where('rsvp_link_id', $lockedLink->getKey())
                ->where('submission_key', $payload['submission_key'])
                ->first();

            if ($existing) {
                $existingNames = $existing->participants->pluck('full_name')->all();

                if ($existing->will_attend !== $payload['will_attend'] || $existingNames !== $names) {
                    throw new ConflictHttpException('This submission key was already used for a different RSVP response.');
                }

                return ['response' => $existing, 'replayed' => true];
            }

            if (! $lockedLink->isAvailable()) {
                throw new HttpException(410, 'This RSVP link is no longer accepting responses.');
            }

            $response = RsvpResponse::query()->create([
                'rsvp_link_id' => $lockedLink->getKey(),
                'submission_key' => $payload['submission_key'],
                'will_attend' => $payload['will_attend'],
                'participant_count' => count($names),
                'submitted_at' => now(),
            ]);

            $response->participants()->createMany(array_map(
                fn (string $name): array => [
                    'full_name' => $name,
                    'will_attend' => $payload['will_attend'],
                ],
                $names,
            ));

            $this->auditLog->created($request, $response, 'Public RSVP response received', mustPersist: true);

            return ['response' => $response, 'replayed' => false];
        }, attempts: 3);
    }
}
