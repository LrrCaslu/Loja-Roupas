<?php
namespace App\Http\Controllers;
use App\Models\{Address,Order,Product,ReturnRequest};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AccountController extends Controller {
 public function index(Request $r){$orders=$r->user()->orders()->latest()->paginate(10);$addresses=$r->user()->addresses;return view('shop.account',compact('orders','addresses'));}
 public function profile(Request $r){$d=$r->validate(['name'=>'required|string|max:150','phone'=>'nullable|string|max:30','current_password'=>'required_with:password|current_password','password'=>'nullable|string|min:8|confirmed']);$user=$r->user();$user->name=$d['name'];$user->phone=$d['phone']??null;if(!empty($d['password']))$user->password=Hash::make($d['password']);$user->save();return back()->with('success','Perfil atualizado.');}
 public function address(Request $r){$d=$r->validate(['label'=>'required|string|max:50','recipient'=>'required|string|max:150','zip'=>'required|string|max:9','street'=>'required|string|max:255','number'=>'required|string|max:30','complement'=>'nullable|string|max:150','district'=>'nullable|string|max:100','city'=>'required|string|max:120','state'=>'required|string|size:2']);$r->user()->addresses()->create($d);return back()->with('success','Endereço cadastrado.');}
 public function deleteAddress(Request $r,Address $address){abort_unless($address->user_id===$r->user()->id,403);$address->delete();return back()->with('success','Endereço removido.');}
 public function favorite(Request $r,Product $product){$r->user()->favorites()->toggle($product->id);return back()->with('success','Favoritos atualizados.');}
 public function favorites(Request $r){$products=$r->user()->favorites()->with('images')->paginate(12);return view('shop.favorites',compact('products'));}
 public function review(Request $r,Product $product){$d=$r->validate(['rating'=>'required|integer|min:1|max:5','body'=>'required|string|min:10|max:1200']);$verified= $r->user()->orders()->where('financial_status','paid')->whereHas('items',fn($q)=>$q->where('product_id',$product->id))->exists();\App\Models\Review::updateOrCreate(['user_id'=>$r->user()->id,'product_id'=>$product->id,'order_id'=>null],['rating'=>$d['rating'],'body'=>$d['body'],'verified'=>$verified,'approved'=>false]);return back()->with('success','Avaliação enviada para moderação.');}
 public function requestReturn(Request $r,Order $order){abort_unless($order->user_id===$r->user()->id,403);abort_unless($order->financial_status==='paid',422);$d=$r->validate(['type'=>'required|in:return,exchange','reason'=>'required|string|min:10|max:1500']);ReturnRequest::create($d+['user_id'=>$r->user()->id,'order_id'=>$order->id]);return back()->with('success','Solicitação registrada.');}
}
