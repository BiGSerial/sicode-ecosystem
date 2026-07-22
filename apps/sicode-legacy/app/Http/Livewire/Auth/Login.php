<?php

namespace App\Http\Livewire\Auth;

use App\CoreIntegration\CurrentCompanyContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public $email;
    public $password;
    public $remember;
    public $show = 0;
    public $msg = '';

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required',
        ];
    }

    public function login()
    {
        $this->show = 1;

        $this->msg = "EFETUANDO LOGIN... AGUARDE... ";

        $this->emitSelf('refresh');

        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
        ];

        $this->validate();

        $remember = $this->remember;

        // dd($remember);

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            $context = (string) config('sicode.core.expected_context');
            $hasSuspendedLink = \App\Models\CoreIdentityLink::query()
                ->where('legacy_user_id', $user->id)
                ->where('application_context', $context)
                ->where('status', \App\Models\CoreIdentityLink::STATUS_SUSPENDED)
                ->exists();

            if ($hasSuspendedLink) {
                Auth::logout();
                session()->invalidate();
                $this->show = 0;
                $this->emitSelf('refresh');
                $this->addError('email', 'As credenciais fornecidas não correspondem aos nossos registros.');
                return;
            }

            session()->regenerate();
            app(CurrentCompanyContext::class)->establishFromLegacyUser(Auth::user());
            session()->put('core_launch.auth_source', 'legacy');

            $this->msg = "REDIRECIONANDO... ";

            $this->emitSelf('refresh');

            if (Auth::user()->first_pass) {
                return redirect()->route('login.show.change');
            }

            if (Auth::user()->onlyparner) {
                return redirect()->route('partner.main.viability');
            }

            return redirect()->intended('/home');
        } else {
            $this->show = 0;
        }

        $this->emitSelf('refresh');

        $this->addError('email', 'As credenciais fornecidas não correspondem aos nossos registros.');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
