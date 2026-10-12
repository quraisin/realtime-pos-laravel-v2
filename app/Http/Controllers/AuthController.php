<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show unified login page
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        $defaultRole = $request->query('role', 'cashier');
        return view('auth.login', ['defaultRole' => $defaultRole]);
    }

    /**
     * Show Cashier specific login page
     */
    public function showCashierLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login', ['defaultRole' => 'cashier', 'isDirectCashier' => true]);
    }

    /**
     * Show KDS (Kitchen) specific login page
     */
    public function showKdsLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login', ['defaultRole' => 'kitchen', 'isDirectKds' => true]);
    }

    /**
     * Handle authentication attempt
     */
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'expected_role' => 'nullable|string|in:cashier,kitchen,superadmin',
        ]);

        $usernameInput = trim($request->input('username'));
        $password = $request->input('password');
        $expectedRole = $request->input('expected_role');

        // Find user by username or email
        $user = User::where('username', $usernameInput)
            ->orWhere('email', $usernameInput)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            \App\Services\FeatureLog::auth('LOGIN_FAILED', "Percobaan login gagal untuk username/email: '{$usernameInput}'", [
                'username_input' => $usernameInput,
            ], 'warning');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah. Silakan coba kembali.',
                ], 422);
            }

            return back()->withInput()->withErrors([
                'username' => 'Username atau password yang dimasukkan salah.',
            ]);
        }

        // Validate role if expected_role is specified
        if ($expectedRole && !$user->hasRole($expectedRole)) {
            $roleLabel = $expectedRole === 'kitchen' ? 'Dapur (KDS)' : 'Kasir';
            $msg = "Akun ini tidak memiliki hak akses sebagai {$roleLabel}.";

            \App\Services\FeatureLog::auth('ROLE_UNAUTHORIZED', "Pengguna '{$user->username}' ditolak login ke portal {$expectedRole}", [
                'user_id' => $user->id,
                'username' => $user->username,
                'expected_role' => $expectedRole,
                'actual_roles' => $user->getRoleNames()->toArray(),
            ], 'warning');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            return back()->withInput()->withErrors(['username' => $msg]);
        }

        // Authenticate the user
        Auth::login($user, $request->boolean('remember', true));
        $request->session()->regenerate();

        \App\Services\FeatureLog::auth('LOGIN_SUCCESS', "Pengguna '{$user->username}' ({$user->name}) berhasil login", [
            'user_id' => $user->id,
            'username' => $user->username,
            'roles' => $user->getRoleNames()->toArray(),
        ]);

        $redirectUrl = $this->determineRedirectUrl($user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Login berhasil! Mengalihkan...',
                'redirect' => $redirectUrl,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'roles' => $user->getRoleNames(),
                ],
            ]);
        }

        return redirect()->intended($redirectUrl);
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = Auth::user();
        if ($currentUser) {
            \App\Services\FeatureLog::auth('LOGOUT', "Pengguna '{$currentUser->username}' ({$currentUser->name}) berhasil logout", [
                'user_id' => $currentUser->id,
                'username' => $currentUser->username,
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }

    /**
     * Get current authenticated user details
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['authenticated' => false], 401);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    /**
     * Helper to redirect based on role
     */
    private function redirectBasedOnRole(User $user): RedirectResponse
    {
        return redirect($this->determineRedirectUrl($user));
    }

    /**
     * Determine destination URL based on role
     */
    private function determineRedirectUrl(User $user): string
    {
        if ($user->hasRole('superadmin') || $user->hasPermissionTo('cms.access')) {
            return route('cms.index');
        }

        if ($user->hasRole('kitchen')) {
            return route('kds.index');
        }

        return route('pos.index');
    }
}
