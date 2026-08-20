<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_admin_login_page(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('id="admin-app"', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_active_user_can_login_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'VerySecure123!',
            'is_active' => true,
        ]);

        $this->postJson(route('admin.login.store'), [
            'email' => 'ADMIN@example.com',
            'password' => 'VerySecure123!',
        ])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'VerySecure123!',
            'is_active' => false,
        ]);

        $this->postJson(route('admin.login.store'), [
            'email' => 'inactive@example.com',
            'password' => 'VerySecure123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonMissing(['inactive']);

        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected_with_a_generic_error(): void
    {
        $this->postJson(route('admin.login.store'), [
            'email' => 'unknown@example.com',
            'password' => 'IncorrectPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'The provided credentials could not be verified.');

        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $credentials = [
            'email' => 'limited@example.com',
            'password' => 'IncorrectPassword123!',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('admin.login.store'), $credentials)
                ->assertUnprocessable();
        }

        $this->postJson(route('admin.login.store'), $credentials)
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many login attempts. Please wait before trying again.');
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_deactivated_authenticated_user_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->deleteJson(route('admin.logout'))
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60')
            ->assertJsonPath('redirect', route('admin.login'));

        $this->assertGuest();
    }

    public function test_logout_rejects_the_wrong_http_verb(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('admin.logout'))
            ->assertMethodNotAllowed();
    }

    public function test_authenticated_mutations_are_rate_limited(): void
    {
        Route::middleware(['web', 'auth', 'active', 'secure.mutations'])
            ->post('/_testing/admin-mutation', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        foreach (range(1, 60) as $attempt) {
            $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
                ->postJson('/_testing/admin-mutation')
                ->assertOk();
        }

        $this->withHeader('X-Idempotency-Key', (string) Str::uuid())
            ->postJson('/_testing/admin-mutation')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Too many changes were requested. Please wait before trying again.');
    }

    public function test_store_requests_are_idempotent_per_user_and_route(): void
    {
        $executions = 0;
        Route::middleware(['web', 'auth', 'active', 'secure.mutations', 'idempotent.admin'])
            ->post('/_testing/idempotent-store', function () use (&$executions) {
                $executions++;

                return response()->json(['created' => true, 'execution' => $executions], 201);
            })
            ->name('testing.idempotent-store');

        $user = User::factory()->create(['is_active' => true]);
        $key = (string) Str::uuid();
        $this->actingAs($user);

        $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/_testing/idempotent-store')
            ->assertCreated()
            ->assertHeader('X-Idempotency-Key', $key)
            ->assertJsonPath('execution', 1);

        $this->withHeader('X-Idempotency-Key', $key)
            ->postJson('/_testing/idempotent-store')
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replayed', 'true')
            ->assertJsonPath('execution', 1);

        $this->assertSame(1, $executions);
    }
}
