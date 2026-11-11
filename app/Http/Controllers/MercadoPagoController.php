<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\{MercadoPagoGateway,PaymentService};
use Illuminate\Http\Request;
class MercadoPagoController extends Controller {
 public function start(Request $r,Order $order,MercadoPagoGateway $gateway){
  abort_unless($order->user_id===$r->user()->id,403);
  abort_unless(config('shop.payment_mode')==='mercadopago',403);
  return redirect()->away($gateway->createPreference($order));
 }
 public function webhook(Request $r,MercadoPagoGateway $gateway,PaymentService $service){
  if(config('shop.payment_mode')!=='mercadopago'||!$gateway->validateSignature($r))return response()->json(['message'=>'Assinatura inválida'],401);
  if(($r->input('type')??$r->query('type'))!=='payment')return response()->json(['ok'=>true]);
  $id=(string)($r->query('data.id')??$r->query('data_id')??$r->input('data.id',''));
  try{$data=$gateway->fetchPayment($id);}catch(\Throwable $e){return response()->json(['message'=>'Falha temporária ao consultar o pagamento'],503);}
  $number=$data['external_reference']??null;
  if(!$number)return response()->json(['ok'=>true]);
  $order=Order::where('number',$number)->first();
  if(!$order)return response()->json(['ok'=>true]);
  $service->settleMercadoPago($order,$data);
  return response()->json(['ok'=>true]);
 }
}