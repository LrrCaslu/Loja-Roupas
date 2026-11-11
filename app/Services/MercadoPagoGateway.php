<?php
namespace App\Services;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
class MercadoPagoGateway {
 private function token():string {
  $token=config('shop.mp_access_token');
  if(config('shop.payment_mode')!=='mercadopago'||!$token)throw ValidationException::withMessages(['payment'=>'Gateway não configurado.']);
  return $token;
 }
 public function createPreference(Order $order):string {
  if($order->financial_status!=='pending')throw ValidationException::withMessages(['payment'=>'Pedido não está aguardando pagamento.']);
  $payload=['items'=>[['id'=>$order->number,'title'=>'Compra '.$order->number.' - Aurora Multimarcas','quantity'=>1,'currency_id'=>'BRL','unit_price'=>(float)$order->total]],'external_reference'=>$order->number,'payer'=>['email'=>$order->email],'back_urls'=>['success'=>route('order.show',$order),'failure'=>route('order.show',$order),'pending'=>route('order.show',$order)],'auto_return'=>'approved'];
  if(config('shop.mp_webhook_url'))$payload['notification_url']=config('shop.mp_webhook_url');
  $result=Http::withToken($this->token())->acceptJson()->timeout(15)->withHeaders(['X-Idempotency-Key'=>$order->number])->post('https://api.mercadopago.com/checkout/preferences',$payload);
  if(!$result->successful()||!($result->json('init_point')||$result->json('sandbox_init_point')))throw ValidationException::withMessages(['payment'=>'Não foi possível abrir o checkout externo. Verifique suas credenciais de teste.']);
  return config('shop.mp_sandbox')&&$result->json('sandbox_init_point')?$result->json('sandbox_init_point'):$result->json('init_point');
 }
 public function validateSignature(Request $request):bool {
  $secret=config('shop.mp_webhook_secret');$signature=$request->header('x-signature');$requestId=$request->header('x-request-id');
  $dataId=(string)($request->query('data.id')??$request->query('data_id')??$request->input('data.id',''));
  if(!$secret||!$signature||!$requestId||!$dataId)return false;
  $parts=[];foreach(explode(',',$signature) as $piece){$pair=explode('=',trim($piece),2);if(count($pair)===2)$parts[$pair[0]]=$pair[1];}
  if(empty($parts['ts'])||empty($parts['v1'])||!ctype_digit((string)$parts['ts']))return false;
  // A assinatura é calculada sobre os campos que o provedor assina, nunca sobre dados do navegador.
  $manifest='id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$parts['ts'].';';
  return hash_equals(hash_hmac('sha256',$manifest,$secret),strtolower($parts['v1']));
 }
 public function fetchPayment(string $id):array {
  if(!preg_match('/^[0-9]{1,30}$/',$id))throw ValidationException::withMessages(['payment'=>'Identificador inválido.']);
  $response=Http::withToken($this->token())->acceptJson()->timeout(15)->get('https://api.mercadopago.com/v1/payments/'.$id);
  if(!$response->successful())throw new \RuntimeException('Falha ao consultar confirmação de pagamento no gateway.');
  return $response->json();
 }
}