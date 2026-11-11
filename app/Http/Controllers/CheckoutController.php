<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\{CartService,OrderService,PaymentService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class CheckoutController extends Controller {
 public function index(Request $r,CartService $s){$cart=$s->cart($r);$quote=$s->quote($cart,$r->session()->get('coupon'),$r->user());if(!$cart->items()->exists())return redirect()->route('cart');if($cart->items->contains(fn($item)=>!$item->variant?->active || !$item->variant?->product?->active || $item->quantity>$item->variant->available))return redirect()->route('cart')->withErrors(['cart'=>'Existe um item indisponível no carrinho. Remova-o ou ajuste a quantidade.']);$addresses=$r->user()->addresses;return view('shop.checkout',compact('cart','quote','addresses'));}
 public function place(Request $r,CartService $s,OrderService $orders){
  $d=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:190','phone'=>'nullable|string|max:30','zip'=>'required|regex:/^[0-9]{5}-?[0-9]{3}$/','address'=>'required|string|max:255','complement'=>'nullable|string|max:150','district'=>'nullable|string|max:150','city'=>'required|string|max:150','state'=>'required|string|size:2','shipping_method'=>'required|in:standard,express,pickup']);
  $order=$orders->place($r->user(),$s->cart($r),$d,$r->session()->get('coupon'));$r->session()->forget('coupon');
  return redirect()->route('order.show',$order);
 }
 public function order(Request $r,Order $order){Gate::authorize('view',$order);$order->load('items','payments','shipment');return view('shop.order',compact('order'));}
 public function demoPay(Request $r,Order $order,PaymentService $p){abort_unless($order->user_id===$r->user()->id,403);abort_unless(config('shop.payment_mode')==='demo',403);$d=$r->validate(['decision'=>'required|in:approved,declined']);$p->settleDemo($order,$d['decision']);return redirect()->route('order.show',$order)->with('success','Pagamento demonstrativo processado (sem cobrança real).');}
 public function cancel(Request $r,Order $order,PaymentService $p){abort_unless($order->user_id===$r->user()->id,403);$p->cancelPending($order);return back()->with('success','Pedido cancelado.');}
}
