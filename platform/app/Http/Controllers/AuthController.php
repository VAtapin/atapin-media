<?php
namespace App\Http\Controllers;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public function create() { return view('auth.login'); }
    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:72']);
        $key = 'login:'.hash('sha256', mb_strtolower($data['email']).'|'.$request->ip());
        $ipKey = 'login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            throw ValidationException::withMessages(['email' => __('ui.login_throttled')]);
        }
        if (!Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 900); RateLimiter::hit($ipKey, 900);
            throw ValidationException::withMessages(['email' => __('ui.login_failed')]);
        }
        RateLimiter::clear($key); $request->session()->regenerate(); $audit->record('auth.login');
        return redirect()->intended('/desktop');
    }
    public function destroy(Request $request, Audit $audit)
    {
        $audit->record('auth.logout'); Auth::logout();
        $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect('/login');
    }
}
