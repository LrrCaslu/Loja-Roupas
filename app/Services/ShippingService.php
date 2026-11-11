<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class ShippingService {
 public function price(float $net,string $method): float {
  return match($method){'standard'=>$net>=(float)\App\Models\Setting::get('free_shipping_min',299)?0:18.90,'express'=>34.90,'pickup'=>0,default=>throw ValidationException::withMessages(['shipping_method'=>'Frete inválido.'])};
 }
 public function estimate(string $method): int{return match($method){'standard'=>7,'express'=>3,'pickup'=>1,default=>7};}
}
