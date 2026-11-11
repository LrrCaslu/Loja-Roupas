<?php
namespace App\Http\Controllers;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\Request;
class CartController extends Controller {
 public function index(Request $r,CartService $s){$cart=$s->cart($r);$quote=$s->quote($cart,$r->session()->get('coupon'),$r->user());$hasUnavailableItems=$cart->items->contains(fn($item)=>!$item->variant?->active || !$item->variant?->product?->active || $item->quantity>$item->variant->available);return view('shop.cart',compact('cart','quote','hasUnavailableItems'));}
 public function add(Request $r,CartService $s){$d=$r->validate(['variant_id'=>'required|exists:product_variants,id','quantity'=>'required|integer|min:1|max:99']);$s->put($s->cart($r),$d['variant_id'],$d['quantity']);return redirect()->route('cart')->with('success','Produto adicionado ao carrinho.');}
 public function update(Request $r,CartItem $item,CartService $s){abort_unless($item->cart_id===$s->cart($r)->id,403);$d=$r->validate(['quantity'=>'required|integer|min:0|max:99']);$s->put($s->cart($r),$item->variant_id,$d['quantity'],true);return back()->with('success','Carrinho atualizado.');}
 public function remove(Request $r,CartItem $item,CartService $s){abort_unless($item->cart_id===$s->cart($r)->id,403);$item->delete();return back()->with('success','Item removido.');}
 public function coupon(Request $r,CartService $s){$d=$r->validate(['code'=>'nullable|string|max:35']);$code=strtoupper(trim($d['code']??''));$s->quote($s->cart($r),$code?:null,$r->user());$r->session()->put('coupon',$code?:null);return back()->with('success','Cupom atualizado.');}
}
