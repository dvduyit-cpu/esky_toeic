<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;
class ShareInertia extends Middleware {
 protected $rootView='app';
 public function share(Request $request):array{return array_merge(parent::share($request),['auth'=>['user'=>$request->user()?->only(['id','name','email','role'])],'flash'=>['success'=>$request->session()->get('success')]]);}
}
