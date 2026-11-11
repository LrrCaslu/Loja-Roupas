<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Hash,Password};
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
class AuthController extends Controller {
 public function loginForm(){return view('auth.login');}
 public function registerForm(){return view('auth.register');}
 public function login(Request $r,CartService $c){
  $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
  $guestSession=$r->session()->getId();
  if(!Auth::attempt($data, $r->boolean('remember'))){return back()->withErrors(['email'=>'E-mail ou senha inválidos.'])->onlyInput('email');}
  if(!Auth::user()->active){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return back()->withErrors(['email'=>'Conta inativa.']);}
  $c->mergeGuest($guestSession,Auth::user());$r->session()->regenerate();
  return redirect()->intended(Auth::user()->isStaff()?route('admin.dashboard'):route('account'));
 }
 public function register(Request $r,CartService $c){$d=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|unique:users,email','phone'=>'nullable|string|max:25','password'=>'required|string|min:8|confirmed']);$session=$r->session()->getId();$user=User::create($d+['role'=>'customer']);Auth::login($user);$c->mergeGuest($session,$user);$r->session()->regenerate();return redirect()->route('account');}
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('home');}
 public function forgotForm(){return view('auth.forgot');}
 public function forgot(Request $r){$r->validate(['email'=>'required|email']);Password::sendResetLink($r->only('email'));return back()->with('success','Se o e-mail existir, as instruções serão enviadas. No ServBay local, consulte o log de e-mails.');}
 public function resetForm(string $token,Request $r){return view('auth.reset',['token'=>$token,'email'=>$r->query('email')]);}
 public function reset(Request $r){$data=$r->validate(['token'=>'required','email'=>'required|email','password'=>'required|min:8|confirmed']);$status=Password::reset($data,function(User $user,string $password){$user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();event(new PasswordReset($user));});return $status===Password::PASSWORD_RESET?redirect()->route('login')->with('success','Senha atualizada.'):back()->withErrors(['email'=>__($status)]);}
}
