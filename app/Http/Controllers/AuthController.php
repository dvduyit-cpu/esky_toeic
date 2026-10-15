<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class AuthController extends Controller {
 public function show(){return Inertia::render('Login');}
 public function login(Request $request){
  $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
  $key='login:'.strtolower($data['email']).'|'.$request->ip();
  if(RateLimiter::tooManyAttempts($key,5))throw ValidationException::withMessages(['email'=>'Thử lại sau '.RateLimiter::availableIn($key).' giây.']);
  if(!Auth::attempt(['email'=>$data['email'],'password'=>$data['password'],'is_active'=>true])){RateLimiter::hit($key,60);throw ValidationException::withMessages(['email'=>'Thông tin đăng nhập không hợp lệ hoặc tài khoản bị khóa.']);}
  RateLimiter::clear($key);$request->session()->regenerate();return redirect()->intended('/dashboard');
 }
 public function logout(Request $request){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect('/login');}
}
