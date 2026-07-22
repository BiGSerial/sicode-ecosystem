<?php

namespace App\Http\Controllers\Auth;

use App\CoreIntegration\CurrentCompanyContext;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers {
        logout as protected logoutUsingLaravelTrait;
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */



    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function authenticated(Request $request, $user)
    {
        $context = (string) config('sicode.core.expected_context');
        $hasSuspendedLink = \Illuminate\Support\Facades\Schema::hasTable('core_identity_links')
            && \App\Models\CoreIdentityLink::query()
                ->where('legacy_user_id', $user->id)
                ->where('application_context', $context)
                ->where('status', \App\Models\CoreIdentityLink::STATUS_SUSPENDED)
                ->exists();

        if ($hasSuspendedLink) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw \Illuminate\Validation\ValidationException::withMessages([
                $this->username() => [trans('auth.failed')],
            ]);
        }

        app(CurrentCompanyContext::class)->establishFromLegacyUser($user);
        $request->session()->put('core_launch.auth_source', 'legacy');
    }

    public function logout(Request $request)
    {
        app(CurrentCompanyContext::class)->clear();

        return $this->logoutUsingLaravelTrait($request);
    }
}
