<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use App\Models\Setting;
use App\Models\Category;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  Gate::policy(\App\Models\Order::class,\App\Policies\OrderPolicy::class);
  Gate::policy(\App\Models\Product::class,\App\Policies\ProductPolicy::class);
  // Share the same request-scoped data with child views AND layouts.
  View::composer('*', function ($view) {
   static $shared=null;
   if($shared===null){
    try {
     $shared=Schema::hasTable('settings') ? [
      'settings'=>Setting::query()->pluck('value','key')->all(),
      'categories'=>Category::where('active',true)->orderBy('sort_order')->limit(12)->get()
     ] : ['settings'=>[],'categories'=>collect()];
    } catch (\Throwable $e) {
     $shared=['settings'=>[],'categories'=>collect()];
    }
   }
   $view->with($shared);
  });
 }
}
