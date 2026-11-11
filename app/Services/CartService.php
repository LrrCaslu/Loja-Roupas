<?php
namespace App\Services;
use App\Models\{Cart,CartItem,Coupon,Variant,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CartService {
 public function cart(Request $request): Cart {
  return $request->user()
   ? Cart::firstOrCreate(['user_id'=>$request->user()->id],['session_id'=>null])
   : Cart::firstOrCreate(['session_id'=>$request->session()->getId()],['user_id'=>null]);
 }
 public function mergeGuest(string $sessionId, User $user): void {
  DB::transaction(function()use($sessionId,$user){
   $guest=Cart::where('session_id',$sessionId)->first();
   if (!$guest)return;
   $target=Cart::firstOrCreate(['user_id'=>$user->id],['session_id'=>null]);
   foreach($guest->items as $item){
    $variant=Variant::find($item->variant_id);if(!$variant || !$variant->active || !$variant->product?->active)continue;
    $existing=$target->items()->where('variant_id',$item->variant_id)->first();
    $amount=min(max(0,$variant->stock-$variant->reserved), $item->quantity+($existing?->quantity??0));
    if($amount>0) $target->items()->updateOrCreate(['variant_id'=>$item->variant_id],['quantity'=>$amount]);
   }
   $guest->delete();
  });
 }
 public function put(Cart $cart,int $variantId,int $qty,bool $absolute=false): void {
  DB::transaction(function()use($cart,$variantId,$qty,$absolute){
   $v=Variant::with('product')->lockForUpdate()->findOrFail($variantId);
   if(!$v->active || !$v->product->active)throw ValidationException::withMessages(['variant_id'=>'Produto indisponível.']);
   $item=CartItem::where('cart_id',$cart->id)->where('variant_id',$variantId)->lockForUpdate()->first();
   $wanted=$absolute?$qty:($item?->quantity??0)+$qty;
   if($wanted<=0){$item?->delete();return;}
   if($wanted>$v->available)throw ValidationException::withMessages(['quantity'=>'Estoque disponível: '.$v->available.' unidade(s).']);
   $cart->items()->updateOrCreate(['variant_id'=>$variantId],['quantity'=>$wanted]);
  });
 }
 public function quote(Cart $cart,?string $code=null,?User $user=null,string $method='standard'): array {
  $cart->load('items.variant.product.images');
  $subtotal=0;
  foreach($cart->items as $item){if(!$item->variant?->active || !$item->variant?->product?->active)continue;$subtotal += (float)$item->variant->product->current_price*$item->quantity;}
  $coupon=null;$discount=0;
  if($code){
   $coupon=Coupon::where('code',strtoupper(trim($code)))->first();
   if(!$coupon||!$coupon->active||($coupon->starts_at&&now()->lt($coupon->starts_at))||($coupon->ends_at&&now()->gt($coupon->ends_at))||($coupon->max_uses!==null&&$coupon->used >=$coupon->max_uses)||$subtotal < (float)$coupon->min_order){throw ValidationException::withMessages(['coupon'=>'Cupom inválido, expirado ou não aplicável.']);}
   if($user && $coupon->per_user_limit!==null && DB::table('coupon_uses')->where('user_id',$user->id)->where('coupon_id',$coupon->id)->count()>=$coupon->per_user_limit)throw ValidationException::withMessages(['coupon'=>'Limite de uso do cupom atingido.']);
   $discount=$coupon->type==='percent' ? $subtotal*(float)$coupon->value/100:(float)$coupon->value;
   $discount=min($subtotal,round($discount,2));
  }
  $shipping=app(ShippingService::class)->price($subtotal-$discount,$method);
  return compact('subtotal','discount','shipping','coupon')+['total'=>round($subtotal-$discount+$shipping,2)];
 }
}
