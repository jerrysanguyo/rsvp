<?php

namespace Tests\Feature;

use App\Models\RsvpLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_http_request_receives_a_correlation_id_and_is_logged(): void
    {
        $requestId = (string) Str::uuid();

        $this->withHeader('X-Request-ID', $requestId)
            ->get(route('admin.login'))
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId);

        $activity = Activity::query()->where('event', 'http.completed')->latest('id')->firstOrFail();

        $this->assertSame('http', $activity->log_name);
        $this->assertSame($requestId, $activity->properties->get('request_id'));
        $this->assertSame('admin.login', $activity->properties->get('route_name'));
        $this->assertSame(200, $activity->properties->get('status_code'));
    }

    public function test_authentication_events_are_logged_without_passwords(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'VerySecure123!',
            'is_active' => true,
        ]);

        $failedResponse = $this->postJson(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $failedResponse
            ->assertUnprocessable()
            ->assertHeader('X-Request-ID');

        $failed = Activity::query()->where('event', 'authentication.login_failed')->firstOrFail();
        $this->assertSame('admin@example.com', $failed->properties->get('attempted_email'));
        $this->assertStringNotContainsString('WrongPassword123!', $failed->properties->toJson());

        $this->postJson(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'VerySecure123!',
        ])->assertOk();

        $succeeded = Activity::query()->where('event', 'authentication.login_succeeded')->firstOrFail();
        $this->assertSame($user->id, $succeeded->causer_id);

        $this->deleteJson(route('admin.logout'))->assertOk();
        $logout = Activity::query()->where('event', 'authentication.logout')->firstOrFail();
        $this->assertSame($user->id, $logout->causer_id);
    }

    public function test_record_updates_log_safe_before_and_after_snapshots(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->for($admin, 'creator')->create([
            'title' => 'Original invitation',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.rsvp-links.update', $link), [
                'title' => 'Updated invitation',
                'is_active' => false,
            ])
            ->assertOk();

        $activity = Activity::query()->where('event', 'record.updated')->firstOrFail();
        $before = $activity->properties->get('before');
        $after = $activity->properties->get('after');

        $this->assertSame('Original invitation', $before['title']);
        $this->assertSame('Updated invitation', $after['title']);
        $this->assertFalse($after['is_active']);
        $this->assertContains('title', $activity->properties->get('changed_fields'));
        $this->assertArrayNotHasKey('token', $before);
        $this->assertArrayNotHasKey('token', $after);
    }

    public function test_create_and_delete_logs_preserve_the_appropriate_snapshot(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => 'Audited invitation',
                'expires_at' => now()->addWeek()->toIso8601String(),
                'is_active' => true,
            ])
            ->assertCreated();

        $link = RsvpLink::firstOrFail();
        $created = Activity::query()->where('event', 'record.created')->firstOrFail();
        $this->assertNull($created->properties->get('before'));
        $this->assertSame('Audited invitation', $created->properties->get('after')['title']);
        $this->assertArrayNotHasKey('token', $created->properties->get('after'));

        $this->deleteJson(route('admin.rsvp-links.destroy', $link))->assertOk();

        $deleted = Activity::query()->where('event', 'record.deleted')->firstOrFail();
        $this->assertSame('Audited invitation', $deleted->properties->get('before')['title']);
        $this->assertNull($deleted->properties->get('after'));
        $this->assertArrayNotHasKey('token', $deleted->properties->get('before'));
    }

    public function test_failed_validation_creates_a_safe_create_failure_audit(): void
    {
        $admin = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson(route('admin.rsvp-links.store'), [
                'title' => 'Invalid invitation',
                'expires_at' => now()->subDay()->toIso8601String(),
                'is_active' => true,
                'password' => 'must-never-be-logged',
                'token' => 'must-never-be-logged',
            ])
            ->assertUnprocessable();

        $activity = Activity::query()->where('event', 'record.create_failed')->sole();
        $properties = $activity->properties;

        $this->assertSame(RsvpLink::class, $properties->get('model_type'));
        $this->assertNull($properties->get('target_id'));
        $this->assertNull($properties->get('attempted_changes'));
        $this->assertSame(['expires_at'], $properties->get('validation_fields'));
        $this->assertContains('title', $properties->get('attempted_fields'));
        $this->assertNotContains('password', $properties->get('attempted_fields'));
        $this->assertNotContains('token', $properties->get('attempted_fields'));
        $this->assertSame(ValidationException::class, $properties->get('exception'));
        $this->assertNotEmpty($properties->get('request_id'));
        $this->assertStringNotContainsString('must-never-be-logged', $properties->toJson());
    }

    public function test_failed_update_records_target_and_safe_previous_snapshot(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $link = RsvpLink::factory()->for($admin, 'creator')->create(['title' => 'Original title']);

        $this->actingAs($admin)
            ->patchJson(route('admin.rsvp-links.update', $link), [
                'title' => '<script>alert(1)</script>',
            ])
            ->assertUnprocessable();

        $activity = Activity::query()->where('event', 'record.update_failed')->sole();
        $properties = $activity->properties;

        $this->assertSame($link->id, $properties->get('target_id'));
        $this->assertSame('Original title', $properties->get('before')['title']);
        $this->assertArrayNotHasKey('token', $properties->get('before'));
        $this->assertNull($properties->get('attempted_changes'));
        $this->assertSame(['title'], $properties->get('attempted_fields'));
        $this->assertSame(ValidationException::class, $properties->get('exception'));
        $this->assertDatabaseHas('rsvp_links', ['id' => $link->id, 'title' => 'Original title']);
    }
}
