<?php

namespace Tests\Feature;

use App\Models\RsvpLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RsvpLinkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_create_a_secure_rsvp_link(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => 'Gaia’s 3rd Birthday',
                ...$this->eventDetails(),
                'expires_at' => now()->addWeek()->toIso8601String(),
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'RSVP link created successfully.')
            ->assertJsonPath('data.status', 'active');

        $link = RsvpLink::firstOrFail();
        $this->assertSame(48, strlen($link->token));
        $this->assertStringContainsString('/rsvp/'.$link->token, $response->json('data.public_url'));
        $this->assertDatabaseHas('rsvp_links', [
            'title' => 'Gaia’s 3rd Birthday',
            'created_by' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_link_creation_sanitizes_plain_text_and_prevents_creator_spoofing(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $otherAdmin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => "  <script>alert('xss')</script>Gaia's <b>Royal</b> Party\u{0000}  ",
                ...$this->eventDetails(),
                'expires_at' => now()->addWeek()->toIso8601String(),
                'is_active' => true,
                'created_by' => $otherAdmin->id,
                'token' => 'attacker-controlled-token',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', "Gaia's Royal Party");

        $link = RsvpLink::firstOrFail();

        $this->assertSame("Gaia's Royal Party", $link->title);
        $this->assertSame($admin->id, $link->created_by);
        $this->assertNotSame('attacker-controlled-token', $link->token);
    }

    public function test_link_creation_rejects_input_that_is_empty_after_sanitization(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => '<script>alert(1)</script>',
                ...$this->eventDetails(),
                'expires_at' => now()->addWeek()->toIso8601String(),
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseCount('rsvp_links', 0);
    }

    public function test_link_creation_requires_a_future_expiration(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => 'Expired invitation',
                ...$this->eventDetails(),
                'expires_at' => now()->subMinute()->toIso8601String(),
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');

        $this->assertDatabaseCount('rsvp_links', 0);
    }

    public function test_inactive_admin_cannot_create_a_link(): void
    {
        $admin = User::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => 'Blocked invitation',
                ...$this->eventDetails(),
                'expires_at' => now()->addWeek()->toIso8601String(),
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('rsvp_links', 0);
    }

    public function test_admin_can_activate_and_deactivate_a_link(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->for($admin, 'creator')->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patchJson(route('admin.rsvp-links.update', $link), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertFalse($link->refresh()->is_active);
    }

    public function test_admin_can_update_event_details_for_an_existing_link(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->for($admin, 'creator')->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.rsvp-links.update', $link), [
                'event_date' => '2027-01-10',
                'event_time' => '3:00-5:00 PM',
                'venue' => 'Jollibee BGC Triangle Drive',
                'venue_map_url' => 'https://maps.app.goo.gl/abc123',
            ])
            ->assertOk()
            ->assertJsonPath('data.event_date', '2027-01-10')
            ->assertJsonPath('data.event_time', '3:00-5:00 PM')
            ->assertJsonPath('data.venue', 'Jollibee BGC Triangle Drive')
            ->assertJsonPath('data.venue_map_url', 'https://maps.app.goo.gl/abc123');

        $this->assertSame('2027-01-10', $link->refresh()->event_date->toDateString());
        $this->assertDatabaseHas('rsvp_links', [
            'id' => $link->id,
            'event_time' => '3:00-5:00 PM',
            'venue' => 'Jollibee BGC Triangle Drive',
            'venue_map_url' => 'https://maps.app.goo.gl/abc123',
        ]);
    }

    public function test_event_details_reject_an_unsafe_or_non_google_maps_link(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->for($admin, 'creator')->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.rsvp-links.update', $link), [
                'venue_map_url' => 'http://example.com/fake-map',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('venue_map_url');

        $this->assertNotSame('http://example.com/fake-map', $link->refresh()->venue_map_url);
    }

    public function test_admin_can_soft_delete_a_link(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->for($admin, 'creator')->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.rsvp-links.destroy', $link))
            ->assertOk()
            ->assertJsonPath('message', 'RSVP link removed successfully.');

        $this->assertSoftDeleted($link);
    }

    public function test_active_public_link_renders_the_rsvp_form_payload(): void
    {
        $link = RsvpLink::factory()->create([
            'title' => 'Gaia’s Royal Party',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('rsvp.show', $link))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Gaia’s Royal Party')
            ->assertSee('is_available&quot;:true', false);
    }

    public function test_inactive_and_expired_links_render_a_closed_payload(): void
    {
        $inactive = RsvpLink::factory()->create(['is_active' => false]);
        $expired = RsvpLink::factory()->create([
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('rsvp.show', $inactive))
            ->assertOk()
            ->assertSee('&quot;status&quot;:&quot;inactive&quot;', false)
            ->assertSee('is_available&quot;:false', false);

        $this->get(route('rsvp.show', $expired))
            ->assertOk()
            ->assertSee('&quot;status&quot;:&quot;expired&quot;', false)
            ->assertSee('is_available&quot;:false', false);
    }

    public function test_public_links_cannot_be_guessed_using_database_ids(): void
    {
        $link = RsvpLink::factory()->create();

        $this->get('/rsvp/'.$link->id)->assertNotFound();
    }

    /** @return array<string, string> */
    private function eventDetails(): array
    {
        return [
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '7:00–9:00 PM',
            'venue' => 'Jollibee Global City',
            'venue_map_url' => 'https://maps.google.com/?q=Jollibee',
        ];
    }
}
