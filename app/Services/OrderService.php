<?php
namespace App\Services;
use App\Models\{Cart,CartItem,Coupon,Order,Variant,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class OrderService {
 public function place(User $user,Cart $cart,array $data,?string $couponCode): Order {
  return DB::transaction(function()use($user,$cart,$data,$couponCode){
   $cart=Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
   $items=CartItem::where('cart_id',$cart->id)->orderBy('variant_id')->lockForUpdate()->get();
   if($items->isEmpty())throw ValidationException::withMessages(['cart'=>'O carrinho está vazio.']);
   $lines=[];$subtotal=0;
   foreach($items as $item){
    $v=Variant::with('product')->lockForUpdate()->findOrFail($item->variant_id);
    if(!$v->active || !$v->product->active || $v->available<$item->quantity)throw ValidationException::withMessages(['stock'=>'Estoque insuficiente para '.$v->product->name.'.']);
    $price=(float)$v->product->current_price;
    $subtotal+=$price*$item->quantity;
    $lines[]=[$item,$v,$price];
   }
   // Trava o cupom e recalcula os totais com valores do banco.
   $coupon=null;$discount=0;
   if($couponCode){
    $coupon=Coupon::where('code',strtoupper(trim($couponCode)))->lockForUpdate()->first();
    if(!$coupon||!$coupon->active||($coupon->starts_at&&now()->lt($coupon->starts_at))||($coupon->ends_at&&now()->gt($coupon->ends_at))||($coupon->max_uses!==null&&$coupon->used >=$coupon->max_uses)||$subtotal<(float)$coupon->min_order)throw ValidationException::withMessages(['coupon'=>'Cupom inválido ou esgotado.']);
    if($coupon->per_user_limit!==null && DB::table('coupon_uses')->where('coupon_id',$coupon->id)->where('user_id',$user->id)->count()>=$coupon->per_user_limit)throw ValidationException::withMessages(['coupon'=>'Limite de uso atingido.']);
    $discount=min($subtotal,round($coupon->type==='percent'?$subtotal*(float)$coupon->value/100:(float)$coupon->value,2));
   }
   $shipping=app(ShippingService::class)->price($subtotal-$discount,$data['shipping_method']);
   $order=Order::create(['number'=>'AUR-'.now()->format('ymd').'-'.strtoupper(Str::random(7)),'user_id'=>$user->id,'name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone']??null,'zip'=>$data['zip'],'address'=>$data['address'],'complement'=>$data['complement']??null,'district'=>$data['district']??null,'city'=>$data['city'],'state'=>strtoupper($data['state']),'subtotal'=>$subtotal,'discount'=>$discount,'shipping'=>$shipping,'total'=>$subtotal-$discount+$shipping,'coupon_id'=>$coupon?->id,'financial_status'=>'pending','fulfillment_status'=>'new','shipping_status'=>'pending']);
   foreach($lines as [$item,$v,$price]){
    $order->items()->create(['product_id'=>$v->product_id,'variant_id'=>$v->id,'name'=>$v->product->name,'sku'=>$v->sku,'variant_label'=>$v->color.' / '.$v->size,'quantity'=>$item->quantity,'unit_price'=>$price,'total'=>$price*$item->quantity]);
    $v->increment('reserved',$item->quantity);
   }
   $order->shipment()->create(['method'=>$data['shipping_method'],'price'=>$shipping,'estimate_days'=>app(ShippingService::class)->estimate($data['shipping_method'])]);
   $provider=config('shop.payment_mode')==='mercadopago'?'mercadopago':'demo';
   $order->payments()->create(['provider'=>$provider,'idempotency_key'=>$provider.'-'.$order->number,'amount'=>$order->total,'status'=>'pending']);
   if($coupon){$coupon->increment('used');DB::table('coupon_uses')->insert(['coupon_id'=>$coupon->id,'user_id'=>$user->id,'order_id'=>$order->id,'created_at'=>now(),'updated_at'=>now()]);}
   $cart->items()->delete();
   return $order->load('items','shipment');
  },3);
 }
}
