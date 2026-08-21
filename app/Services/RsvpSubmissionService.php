<?php

namespace App\Services;

use App\Models\RsvpLink;
use App\Models\RsvpParticipant;
use App\Models\RsvpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $normalizedNames = array_map($this->normalizeName(...), $names);

        if (count(array_unique($normalizedNames)) !== count($normalizedNames)) {
            throw ValidationException::withMessages($this->duplicateNameErrors($names, $normalizedNames));
        }

        return DB::transaction(function () use ($request, $rsvpLink, $payload, $names, $normalizedNames): array {
            $lockedLink = RsvpLink::query()->whereKey($rsvpLink->getKey())->lockForUpdate()->firstOrFail();

            $existing = RsvpResponse::query()
                ->with('participants')
                ->where('rsvp_link_id', $lockedLink->getKey())
                ->where('submission_key', $payload['submission_key'])
                ->first();

            if ($existing) {
                $existingNames = $existing->participants->pluck('full_name')->all();
                $normalizedExistingNames = array_map($this->normalizeName(...), $existingNames);

                if ($existing->will_attend !== $payload['will_attend'] || $normalizedExistingNames !== $normalizedNames) {
                    throw new ConflictHttpException('This submission key was already used for a different RSVP response.');
                }

                return ['response' => $existing, 'replayed' => true];
            }

            if (! $lockedLink->isAvailable()) {
                throw new HttpException(410, 'This RSVP link is no longer accepting responses.');
            }

            $registeredNames = RsvpParticipant::query()
                ->whereHas('response', fn ($query) => $query->where('rsvp_link_id', $lockedLink->getKey()))
                ->pluck('full_name')
                ->map(fn (string $name): string => $this->normalizeName($name));

            $registeredNameSet = $registeredNames->flip();
            $registeredErrors = [];

            foreach ($normalizedNames as $index => $normalizedName) {
                if ($registeredNameSet->has($normalizedName)) {
                    $registeredErrors["participants.{$index}.full_name"] = sprintf(
                        'The full name "%s" is already registered for this invitation.',
                        $names[$index],
                    );
                }
            }

            if ($registeredErrors !== []) {
                throw ValidationException::withMessages($registeredErrors);
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

    private function normalizeName(string $name): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name));
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $normalizedNames
     * @return array<string, string>
     */
    private function duplicateNameErrors(array $names, array $normalizedNames): array
    {
        $counts = array_count_values($normalizedNames);
        $errors = [];

        foreach ($normalizedNames as $index => $normalizedName) {
            if ($counts[$normalizedName] > 1) {
                $errors["participants.{$index}.full_name"] = sprintf(
                    'The full name "%s" has been entered more than once.',
                    $names[$index],
                );
            }
        }

        return $errors;
    }
}
