<?php

namespace Tests\Feature;

use App\Models\RsvpLink;
use App\Models\RsvpResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PublicRsvpSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_submit_an_attending_response_with_multiple_participants(): void
    {
        $link = RsvpLink::factory()->create(['is_active' => true, 'expires_at' => now()->addDay()]);

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => true,
                'participants' => [
                    ['full_name' => '  Maria <b>Santos</b>  '],
                    ['full_name' => 'Miguel Santos'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.will_attend', true)
            ->assertJsonPath('data.participant_count', 2)
            ->assertJsonPath('data.participants.0.full_name', 'Maria Santos')
            ->assertJsonPath('data.participants.1.full_name', 'Miguel Santos');

        $this->assertDatabaseHas('rsvp_responses', [
            'rsvp_link_id' => $link->id,
            'will_attend' => true,
            'participant_count' => 2,
        ]);
        $this->assertDatabaseHas('rsvp_participants', ['full_name' => 'Maria Santos', 'will_attend' => true]);
        $this->assertDatabaseHas('rsvp_participants', ['full_name' => 'Miguel Santos', 'will_attend' => true]);

        $activity = Activity::query()
            ->where('event', 'record.created')
            ->where('subject_type', RsvpResponse::class)
            ->sole();

        $this->assertStringNotContainsString('Maria Santos', $activity->properties->toJson());
        $this->assertStringNotContainsString('submission_key', $activity->properties->toJson());
    }

    public function test_declined_response_stores_one_participant_as_not_attending(): void
    {
        $link = RsvpLink::factory()->create();

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => false,
                'participants' => [['full_name' => 'Angela Reyes']],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('rsvp_participants', [
            'full_name' => 'Angela Reyes',
            'will_attend' => false,
        ]);
    }

    public function test_replaying_the_same_submission_key_does_not_create_duplicates(): void
    {
        $link = RsvpLink::factory()->create();
        $key = (string) Str::uuid();
        $payload = [
            'will_attend' => true,
            'participants' => [['full_name' => 'Maria Santos']],
        ];

        $this->withHeader('X-Idempotency-Key', $key)
            ->postJson(route('rsvp.store', $link), $payload)
            ->assertCreated();

        $this->withHeader('X-Idempotency-Key', $key)
            ->postJson(route('rsvp.store', $link), $payload)
            ->assertOk();

        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertDatabaseCount('rsvp_participants', 1);
    }

    public function test_submission_key_cannot_be_reused_with_different_data(): void
    {
        $link = RsvpLink::factory()->create();
        $key = (string) Str::uuid();

        $this->withHeader('X-Idempotency-Key', $key)
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => true,
                'participants' => [['full_name' => 'Maria Santos']],
            ])
            ->assertCreated();

        $this->withHeader('X-Idempotency-Key', $key)
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => false,
                'participants' => [['full_name' => 'Angela Reyes']],
            ])
            ->assertConflict();

        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertDatabaseCount('rsvp_participants', 1);
        $this->assertDatabaseMissing('rsvp_participants', ['full_name' => 'Angela Reyes']);
    }

    public function test_participant_names_cannot_be_registered_twice_for_the_same_link(): void
    {
        $link = RsvpLink::factory()->create();

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => true,
                'participants' => [['full_name' => 'Maria Santos']],
            ])
            ->assertCreated();

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => true,
                'participants' => [['full_name' => '  MARIA   santos  ']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');

        $this->assertDatabaseCount('rsvp_responses', 1);
        $this->assertDatabaseCount('rsvp_participants', 1);
    }

    public function test_same_participant_name_can_be_used_for_a_different_link(): void
    {
        $firstLink = RsvpLink::factory()->create();
        $secondLink = RsvpLink::factory()->create();
        $payload = [
            'will_attend' => true,
            'participants' => [['full_name' => 'Maria Santos']],
        ];

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $firstLink), $payload)
            ->assertCreated();

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $secondLink), $payload)
            ->assertCreated();

        $this->assertDatabaseCount('rsvp_responses', 2);
        $this->assertDatabaseCount('rsvp_participants', 2);
    }

    public function test_closed_links_reject_submissions(): void
    {
        $link = RsvpLink::factory()->create(['is_active' => false]);

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => true,
                'participants' => [['full_name' => 'Maria Santos']],
            ])
            ->assertGone()
            ->assertJsonPath('message', 'This RSVP link is no longer accepting responses.');

        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_submission_validation_enforces_names_guest_limit_and_decline_rules(): void
    {
        $link = RsvpLink::factory()->create();

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => false,
                'participants' => [
                    ['full_name' => 'Maria Santos'],
                    ['full_name' => 'Miguel Santos'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');

        $this->assertDatabaseCount('rsvp_responses', 0);
    }

    public function test_admin_dashboard_receives_saved_participants(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->create(['title' => 'Royal Party']);

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('rsvp.store', $link), [
                'will_attend' => true,
                'participants' => [['full_name' => 'Maria Santos']],
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Maria Santos')
            ->assertSee('Royal Party');
    }
}
