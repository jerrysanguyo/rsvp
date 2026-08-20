<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function create(Request $request): View
    {
        return view('admin.app', [
            'page' => 'login',
            'payload' => [
                'loginUrl' => route('admin.login.store'),
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ],
            ],
        ]);
    }

    /** @throws ValidationException */
    public function store(AdminLoginRequest $request): JsonResponse|RedirectResponse
    {
        $email = $request->string('email')->trim()->lower()->toString();
        $credentials = [
            'email' => $email,
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            $this->auditLog->authentication(
                $request,
                'authentication.login_failed',
                'Admin login rejected',
                properties: ['attempted_email' => $email, 'reason' => 'credentials_or_account_rejected'],
            );

            throw ValidationException::withMessages([
                'email' => 'The provided credentials could not be verified.',
            ]);
        }

        $request->session()->regenerate();
        $this->auditLog->authentication(
            $request,
            'authentication.login_succeeded',
            'Admin logged in',
            $request->user(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Welcome back. You have signed in successfully.',
                'redirect' => route('admin.dashboard'),
            ]);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        $this->auditLog->authentication(
            $request,
            'authentication.logout',
            'Admin logged out',
            $actor,
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You have been signed out securely.',
                'redirect' => route('admin.login'),
            ]);
        }

        return redirect()
            ->route('admin.login')
            ->with('success', 'You have been signed out securely.');
    }
}
