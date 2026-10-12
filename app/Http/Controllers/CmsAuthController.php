<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class CmsAuthController extends Controller
{
    /**
     * Show dedicated Superadmin CMS login page
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('superadmin') || $user->hasPermissionTo('cms.access')) {
                return redirect()->route('cms.index');
            }
            if ($user->hasRole('kitchen')) {
                return redirect()->route('kds.index');
            }
            return redirect()->route('pos.index');
        }

        return view('cms.login');
    }

    /**
     * Handle authentication attempt specifically for Superadmin CMS
     */
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $usernameInput = trim($request->input('username'));
        $password = $request->input('password');

        // Find user by username or email
        $user = User::where('username', $usernameInput)
            ->orWhere('email', $usernameInput)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            \App\Services\FeatureLog::auth('CMS_LOGIN_FAILED', "Percobaan login CMS gagal untuk username/email: '{$usernameInput}'", [
                'username_input' => $usernameInput,
            ], 'warning');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username/email atau password salah.',
                ], 422);
            }

            return back()->withInput()->withErrors([
                'username' => 'Username/email atau password yang dimasukkan salah.',
            ]);
        }

        // Validate superadmin / CMS permissions
        if (!$user->hasRole('superadmin') && !$user->hasPermissionTo('cms.access')) {
            $msg = 'Akun ini tidak memiliki hak akses Superadmin Qurais Kitchen Dashboard.';

            \App\Services\FeatureLog::auth('CMS_ACCESS_DENIED', "Pengguna '{$user->username}' ditolak akses ke CMS Superadmin. Hak akses tidak mencukupi", [
                'user_id' => $user->id,
                'username' => $user->username,
                'roles' => $user->getRoleNames()->toArray(),
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

        \App\Services\FeatureLog::auth('CMS_LOGIN_SUCCESS', "Superadmin '{$user->username}' ({$user->name}) berhasil login ke CMS Dashboard", [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
        ]);

        $redirectUrl = route('cms.index');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Login Superadmin berhasil! Mengalihkan ke CMS...',
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
     * Logout from CMS
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = Auth::user();
        if ($currentUser) {
            \App\Services\FeatureLog::auth('CMS_LOGOUT', "Superadmin '{$currentUser->username}' ({$currentUser->name}) logout dari CMS", [
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
                'redirect' => route('cms.login'),
            ]);
        }

        return redirect()->route('cms.login');
    }
}
