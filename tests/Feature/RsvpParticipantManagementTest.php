<?php

namespace Tests\Feature;

use App\Models\RsvpLink;
use App\Models\RsvpParticipant;
use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RsvpParticipantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_a_duplicate_participant_and_response_count_is_updated(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $response = $this->responseWithParticipants(['Maria Santos', 'Maria  Santos']);
        $duplicate = $response->participants()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson(route('admin.participants.destroy', $duplicate))
            ->assertOk()
            ->assertJsonPath('message', 'Participant removed successfully.');

        $this->assertDatabaseMissing('rsvp_participants', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('rsvp_responses', [
            'id' => $response->id,
            'participant_count' => 1,
        ]);

        $deletedActivity = Activity::query()
            ->where('event', 'record.deleted')
            ->where('subject_type', RsvpParticipant::class)
            ->sole();

        $this->assertSame('Maria  Santos', $deletedActivity->properties->get('before')['full_name']);
    }

    public function test_removing_the_last_participant_also_removes_the_empty_response(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $response = $this->responseWithParticipants(['Maria Santos']);
        $participant = $response->participants()->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson(route('admin.participants.destroy', $participant))
            ->assertOk();

        $this->assertDatabaseMissing('rsvp_participants', ['id' => $participant->id]);
        $this->assertDatabaseMissing('rsvp_responses', ['id' => $response->id]);
    }

    public function test_guests_cannot_delete_participants(): void
    {
        $response = $this->responseWithParticipants(['Maria Santos']);
        $participant = $response->participants()->firstOrFail();

        $this->deleteJson(route('admin.participants.destroy', $participant))
            ->assertUnauthorized();

        $this->assertDatabaseHas('rsvp_participants', ['id' => $participant->id]);
    }

    /** @param list<string> $names */
    private function responseWithParticipants(array $names): RsvpResponse
    {
        $link = RsvpLink::factory()->create();
        $response = RsvpResponse::query()->create([
            'rsvp_link_id' => $link->id,
            'submission_key' => (string) Str::uuid(),
            'will_attend' => true,
            'participant_count' => count($names),
            'submitted_at' => now(),
        ]);

        $response->participants()->createMany(array_map(
            fn (string $name): array => ['full_name' => $name, 'will_attend' => true],
            $names,
        ));

        return $response;
    }
}
