<?php
namespace App\Services;
use App\Models\{Order,Variant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PaymentService {
 public function settleMercadoPago(Order $order,array $data):Order {
  if((string)($data['external_reference']??'')!==$order->number||($data['currency_id']??'')!=='BRL'||abs((float)($data['transaction_amount']??0)-(float)$order->total)>0.01)throw ValidationException::withMessages(['payment'=>'Pagamento não corresponde ao pedido.']);
  $status=$data['status']??'';
  if(!in_array($status,['approved','rejected','cancelled'],true))return $order;
  $decision=$status==='approved'?'approved':'declined';
  return $this->settleDemo($order,$decision,'mercadopago',(string)$data['id']);
 }
 // Transições idempotentes: somente uma confirmação pode movimentar estoque.
 public function settleDemo(Order $order,string $decision,string $provider='demo',?string $externalId=null): Order {
  if(!in_array($decision,['approved','declined'],true))throw ValidationException::withMessages(['decision'=>'Decisão inválida.']);
  return DB::transaction(function()use($order,$decision,$provider,$externalId){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   $payment=$locked->payments()->where('provider',$provider)->lockForUpdate()->firstOrFail();
   if($locked->financial_status!=='pending')return $locked;
   foreach($locked->items()->orderBy('variant_id')->get() as $item){
    if($item->variant_id===null)continue;
    $v=Variant::lockForUpdate()->findOrFail($item->variant_id);
    if($v->reserved<$item->quantity)throw new \RuntimeException('Reserva inconsistente para '.$v->sku);
    if($decision==='approved'){
     if($v->stock<$item->quantity)throw new \RuntimeException('Estoque inconsistente');
     $before=$v->stock;$v->decrement('stock',$item->quantity);
     $v->decrement('reserved',$item->quantity);
     $v->refresh();
     DB::table('stock_movements')->insert(['variant_id'=>$v->id,'user_id'=>null,'type'=>'sale','change'=>-$item->quantity,'before'=>$before,'after'=>$v->stock,'reason'=>$locked->number,'created_at'=>now(),'updated_at'=>now()]);
    } else $v->decrement('reserved',$item->quantity);
   }
   $locked->update(['financial_status'=>$decision==='approved'?'paid':'declined','fulfillment_status'=>$decision==='approved'?'processing':'cancelled']);
   if($decision==='declined'&&$locked->coupon_id){DB::table('coupons')->where('id',$locked->coupon_id)->where('used','>',0)->decrement('used');DB::table('coupon_uses')->where('order_id',$locked->id)->delete();}
   $payment->update(['status'=>$decision,'external_id'=>$externalId??$payment->external_id]);
   return $locked->fresh();
  },3);
 }
 public function refundDemo(Order $order,bool $restock=false): Order {
  return DB::transaction(function()use($order,$restock){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($locked->financial_status==='refunded')return $locked;
   $payment=$locked->payments()->where('provider','demo')->lockForUpdate()->first();
   if(!$payment||$locked->financial_status!=='paid')throw ValidationException::withMessages(['refund'=>'Apenas pagamentos demonstrativos aprovados podem ser reembolsados por esta operação.']);
   if($restock)foreach($locked->items()->orderBy('variant_id')->get() as $item){
    if(!$item->variant_id)continue;$v=Variant::lockForUpdate()->find($item->variant_id);if(!$v)continue;
    $before=$v->stock;$v->increment('stock',$item->quantity);
    DB::table('stock_movements')->insert(['variant_id'=>$v->id,'user_id'=>auth()->id(),'type'=>'refund_return','change'=>$item->quantity,'before'=>$before,'after'=>$before+$item->quantity,'reason'=>$locked->number,'created_at'=>now(),'updated_at'=>now()]);
   }
   $locked->update(['financial_status'=>'refunded']);$payment->update(['status'=>'refunded']);return $locked->fresh();
  },3);
 }
 public function cancelPending(Order $order): Order {
  return DB::transaction(function()use($order){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($locked->financial_status!=='pending')throw ValidationException::withMessages(['order'=>'Somente pedidos aguardando pagamento podem ser cancelados aqui.']);
   foreach($locked->items()->orderBy('variant_id')->get() as $item){if($item->variant_id){$v=Variant::lockForUpdate()->findOrFail($item->variant_id);$v->decrement('reserved',min($v->reserved,$item->quantity));}}
   $locked->update(['financial_status'=>'cancelled','fulfillment_status'=>'cancelled']);
   $locked->payments()->update(['status'=>'cancelled']);
   if($locked->coupon_id){DB::table('coupons')->where('id',$locked->coupon_id)->where('used','>',0)->decrement('used');DB::table('coupon_uses')->where('order_id',$locked->id)->delete();}
   return $locked->fresh();
  },3);
 }
}
