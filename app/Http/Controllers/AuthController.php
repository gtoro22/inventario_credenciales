<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LoginThrottle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(private AuditService $audit, private LoginThrottle $throttle) {}

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $email = Str::lower($request->string('email')->toString());
        $key = $email.'|'.$request->ip();

        $this->throttle->ensureNotLocked($key);

        $attempt = Auth::attempt(
            ['email' => $email, 'password' => $request->string('password')->toString(), 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $attempt) {
            $this->throttle->hit($key);
            // Nunca se registra la contraseña intentada.
            $this->audit->log('auth.login_failed', email: $email, label: $email);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros o la cuenta está desactivada.',
            ]);
        }

        $this->throttle->clear($key);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->log('auth.login', $user, label: $user->email);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['data' => $this->profile($user)]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->audit->log('auth.logout', $user, label: $user->email);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Sesión cerrada.']);
        }

        return redirect()->route('login');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profile($request->user())]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->input('password')]);
        $this->audit->log('user.password_changed', $user, label: $user->email);

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->isSuperAdmin(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'roles' => $user->roles()->get(['roles.id', 'roles.slug', 'roles.name']),
            'profiles' => $user->profiles()->get(['profiles.id', 'profiles.slug', 'profiles.name']),
            'permissions' => $user->permissionSlugs(),
            'project_ids' => $user->isSuperAdmin() ? null : $user->accessibleProjectIds(),
        ];
    }
}
