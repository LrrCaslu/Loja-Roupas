<?php
namespace App\Http\Controllers;
use App\Http\Requests\ProductRequest;
use App\Models\{AuditLog,Banner,Brand,Category,Collection,Coupon,Order,Product,ProductImage,Review,ReturnRequest,Setting,StockMovement,User,Variant};
use App\Services\{Audit,PaymentService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class AdminController extends Controller {
 private function manager():void{abort_unless(in_array(auth()->user()->role,['superadmin','admin'],true),403);}
 private function privileged():void{abort_unless(auth()->user()->role==='superadmin',403);}
 private function resources():array{return ['categorias'=>Category::class,'marcas'=>Brand::class,'colecoes'=>Collection::class,'banners'=>Banner::class,'cupons'=>Coupon::class,'avaliacoes'=>Review::class,'devolucoes'=>ReturnRequest::class];}
 public function dashboard(Request $r){
  $r->validate(['period'=>'nullable|in:today,7,30,month,custom','from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
  $start=match($r->input('period','30')){'today'=>today(),'7'=>now()->subDays(7)->startOfDay(),'month'=>now()->startOfMonth(),'custom'=>($r->filled('from')?\Carbon\Carbon::parse($r->input('from'))->startOfDay():now()->subDays(30)),default=>now()->subDays(30)->startOfDay()};
  $end=$r->input('period')==='custom'&&$r->filled('to')?\Carbon\Carbon::parse($r->input('to'))->endOfDay():now();
  $ordersQuery=Order::whereBetween('created_at',[$start,$end]);
  $paid=(clone $ordersQuery)->where('financial_status','paid');
  $revenue=(float)(clone $paid)->sum('total');$paidCount=(clone $paid)->count();
  $stats=['revenue'=>$revenue,'orders'=>(clone $ordersQuery)->count(),'paid'=>$paidCount,'pending'=>(clone $ordersQuery)->where('financial_status','pending')->count(),'cancelled'=>(clone $ordersQuery)->whereIn('financial_status',['cancelled','declined'])->count(),'customers'=>User::where('role','customer')->count(),'products'=>Product::count(),'low_stock'=>Variant::where('active',true)->whereRaw('stock - reserved <= min_stock')->count(),'average'=>$paidCount?$revenue/$paidCount:0];
  $sales=Order::where('financial_status','paid')->whereBetween('created_at',[$start,$end])->orderBy('created_at')->get(['created_at','total'])->groupBy(fn($o)=>$o->created_at->format('d/m'))->map(fn($items)=>round($items->sum('total'),2));
  $recent=(clone $ordersQuery)->with('user')->latest()->limit(7)->get();
  $bests=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')->where('orders.financial_status','paid')->whereBetween('orders.created_at',[$start,$end])->select('order_items.name')->selectRaw('SUM(order_items.quantity) as units')->groupBy('order_items.name')->orderByDesc('units')->limit(5)->get();
  return view('admin.dashboard',compact('stats','sales','recent','bests'));
 }
 public function products(Request $r){
  $q=Product::with('images','brand','category')->withSum('variants','stock')->withSum('variants','reserved');
  if($r->filled('q'))$q->where(fn($qq)=>$qq->where('name','like','%'.$r->input('q').'%')->orWhere('sku','like','%'.$r->input('q').'%'));
  if($r->filled('category'))$q->where('category_id',$r->integer('category'));
  if($r->filled('status'))$q->where('active',$r->input('status')==='active');
  $products=$q->latest()->paginate(12)->withQueryString();$categoriesList=Category::orderBy('name')->get();
  return view('admin.products',compact('products','categoriesList'));
 }
 public function createProduct(){$product=null;$cats=Category::orderBy('name')->get();$brands=Brand::orderBy('name')->get();$collections=Collection::orderBy('name')->get();return view('admin.product-form',compact('product','cats','brands','collections'));}
 public function editProduct(Product $product){$product->load('images');$product->load(['variants'=>fn($q)=>$q->where('active',true)]);$cats=Category::orderBy('name')->get();$brands=Brand::orderBy('name')->get();$collections=Collection::orderBy('name')->get();return view('admin.product-form',compact('product','cats','brands','collections'));}
 public function storeProduct(ProductRequest $r){return $this->saveProduct($r,null);}
 public function updateProduct(ProductRequest $r,Product $product){return $this->saveProduct($r,$product);}
 private function saveProduct(ProductRequest $r,?Product $product){
  $data=$r->validated();$created=!$product;
  $product=DB::transaction(function()use($r,$data,$product){
   $fields=collect($data)->only(['name','sku','description','details','care','price','sale_price','category_id','brand_id','collection_id'])->all();
   $fields+=['featured'=>$r->boolean('featured'),'active'=>$r->boolean('active'),'is_new'=>$r->boolean('is_new')];
   if(!$product){$fields['slug']=Str::slug($fields['name']).'-'.strtolower(Str::random(5));$product=Product::create($fields);}else {$product->update($fields);}
   $seen=[];
   $combinations=[];
   foreach($data['variants'] as $i=>$v){
    $combination=mb_strtolower(trim($v['size']).'|'.trim($v['color']));
    if(isset($combinations[$combination]))throw ValidationException::withMessages(['variants'=>'Existem combinações repetidas de tamanho e cor.']);
    $combinations[$combination]=true;
    $submittedSku=trim((string)($v['sku']??''));
    $existing=$submittedSku!=='' ? $product->variants()->where('sku',$submittedSku)->lockForUpdate()->first() : null;
    if(!$existing){$existing=$product->variants()->where('size',$v['size'])->where('color',$v['color'])->lockForUpdate()->first();}
    $vsku=$submittedSku!==''?$submittedSku:($existing?->sku??($product->sku.'-'.strtoupper(Str::random(8))));
    if(Variant::where('sku',$vsku)->when($existing,fn($q)=>$q->where('id','!=',$existing->id))->exists())
     throw ValidationException::withMessages(['variants'=>'SKU de variação já utilizado.']);
    if($product->variants()->where('size',$v['size'])->where('color',$v['color'])->when($existing,fn($q)=>$q->where('id','!=',$existing->id))->exists())
     throw ValidationException::withMessages(['variants'=>'Tamanho e cor duplicados.']);
    $stock=(int)$v['stock'];
    if($existing && $stock<$existing->reserved)throw ValidationException::withMessages(['variants'=>'O estoque não pode ficar menor que o reservado.']);
    if($existing){$before=$existing->stock;$existing->update(['sku'=>$vsku,'size'=>$v['size'],'color'=>$v['color'],'stock'=>$stock,'active'=>true]);}
    else {$before=0;$existing=$product->variants()->create(['sku'=>$vsku,'size'=>$v['size'],'color'=>$v['color'],'stock'=>$stock,'reserved'=>0,'active'=>true]);}
    if($stock!==$before){StockMovement::create(['variant_id'=>$existing->id,'user_id'=>auth()->id(),'type'=>'adjustment','change'=>$stock-$before,'before'=>$before,'after'=>$stock,'reason'=>'Edição do produto']);}
    $seen[]=$existing->id;
   }
   // Variações removidas da ficha ficam indisponíveis, preservando estoque e histórico.
   $product->variants()->whereNotIn('id',$seen)->update(['active'=>false]);
   if($r->hasFile('images'))foreach($r->file('images') as $index=>$image){$path=$image->store('products','public');$product->images()->create(['path'=>'/storage/'.$path,'alt'=>$product->name,'sort_order'=>$product->images()->count()+$index]);}
   if($r->filled('remove_images')){foreach((array)$r->input('remove_images') as $imageId){$image=$product->images()->whereKey((int)$imageId)->first();if(!$image)continue;if(str_starts_with($image->path,'/storage/'))Storage::disk('public')->delete(substr($image->path,9));$image->delete();}}
   return $product;
  },3);
  Audit::record($created?'create':'update','product',$product->id);
  return redirect()->route('admin.products.edit',$product)->with('success',$created?'Produto criado.':'Produto atualizado.');
 }
 public function deleteProduct(Product $product){$product->update(['active'=>false]);Audit::record('archive','product',$product->id);return back()->with('success','Produto arquivado sem perder o histórico.');}
 public function orders(Request $r){$q=Order::with('user');if($r->filled('status'))$q->where('financial_status',$r->input('status'));if($r->filled('q'))$q->where('number','like','%'.$r->input('q').'%');$orders=$q->latest()->paginate(20)->withQueryString();return view('admin.orders',compact('orders'));}
 public function order(Order $order){$order->load('items','user','shipment','payments');return view('admin.order',compact('order'));}
 public function updateOrder(Request $r,Order $order,PaymentService $p){
  $d=$r->validate(['fulfillment_status'=>'nullable|in:new,processing,packed,shipped,delivered,cancelled','shipping_status'=>'nullable|in:pending,preparing,shipped,delivered','tracking_code'=>'nullable|string|max:100','tracking_url'=>'nullable|url|max:500','cancel_pending'=>'nullable|boolean']);
  if($r->boolean('cancel_pending')){
   $p->cancelPending($order);
   Audit::record('cancel','order',$order->id);
   return back()->with('success','Pedido cancelado.');
  }
  DB::transaction(function()use($order,$d){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   $next=$d['fulfillment_status']??$locked->fulfillment_status;
   $shipping=$d['shipping_status']??$locked->shipping_status;
   $transitions=['new'=>['processing'],'processing'=>['packed'],'packed'=>['shipped'],'shipped'=>['delivered'],'delivered'=>[],'cancelled'=>[]];
   if($next!==$locked->fulfillment_status && !in_array($next,$transitions[$locked->fulfillment_status]??[],true))
    throw ValidationException::withMessages(['fulfillment_status'=>'Transição inválida de status.']);
   if($locked->financial_status!=='paid' && ($next!=='new' || !in_array($shipping,['pending'],true)))
    throw ValidationException::withMessages(['order'=>'Pedidos não pagos não podem ser preparados ou despachados.']);
   $shippingStages=['pending'=>0,'preparing'=>1,'shipped'=>2,'delivered'=>3];
   if(($shippingStages[$shipping]??-1)<($shippingStages[$locked->shipping_status]??0))
    throw ValidationException::withMessages(['shipping_status'=>'O status da entrega não pode retroceder.']);
   if($shipping==='shipped' && !in_array($next,['shipped','delivered'],true))
    throw ValidationException::withMessages(['shipping_status'=>'Marque o pedido como enviado antes de atualizar a entrega.']);
   if($shipping==='delivered' && $next!=='delivered')
    throw ValidationException::withMessages(['shipping_status'=>'Marque o pedido como entregue antes de concluir a entrega.']);
   $locked->update(['fulfillment_status'=>$next,'shipping_status'=>$shipping]);
   $shipment=$locked->shipment()->lockForUpdate()->first();
   if($shipment)$shipment->update(['status'=>$shipping,'tracking_code'=>$d['tracking_code']??$shipment->tracking_code,'tracking_url'=>$d['tracking_url']??$shipment->tracking_url]);
  },3);
  Audit::record('status','order',$order->id,['fulfillment'=>$order->fresh()->fulfillment_status,'shipping'=>$order->fresh()->shipping_status]);
  return back()->with('success','Pedido atualizado.');
 }
 public function refundOrder(Request $r,Order $order,PaymentService $service){
  $this->manager();abort_unless(config('shop.payment_mode')==='demo',403);
  $d=$r->validate(['restock'=>'sometimes|boolean']);
  $service->refundDemo($order,$r->boolean('restock'));Audit::record('refund_demo','order',$order->id,['restock'=>$r->boolean('restock')]);
  return back()->with('success','Reembolso demonstrativo concluído (nenhum dinheiro foi transferido).');
 }
 public function inventory(Request $r){$q=Variant::with('product')->whereHas('product');if($r->boolean('low'))$q->where('active',true)->whereRaw('stock-reserved <= min_stock');if($r->filled('q'))$q->whereHas('product',fn($p)=>$p->where('name','like','%'.$r->input('q').'%'));$variants=$q->orderBy('stock')->paginate(25)->withQueryString();$movements=StockMovement::with('variant.product')->latest()->limit(15)->get();return view('admin.inventory',compact('variants','movements'));}
 public function adjustInventory(Request $r,Variant $variant){$d=$r->validate(['change'=>'required|integer|between:-99999,99999','reason'=>'required|string|max:190']);DB::transaction(function()use($variant,$d){$v=Variant::whereKey($variant->id)->lockForUpdate()->firstOrFail();$after=$v->stock+$d['change'];if($after<0||$after<$v->reserved)throw ValidationException::withMessages(['change'=>'Estoque não pode ser negativo ou inferior às reservas.']);$before=$v->stock;$v->update(['stock'=>$after]);StockMovement::create(['variant_id'=>$v->id,'user_id'=>auth()->id(),'type'=>'manual','change'=>$d['change'],'before'=>$before,'after'=>$after,'reason'=>$d['reason']]);});Audit::record('adjust_stock','variant',$variant->id);return back()->with('success','Estoque atualizado.');}
 public function customers(Request $r){$q=User::where('role','customer')->withCount('orders')->withSum(['orders as spent'=>fn($o)=>$o->where('financial_status','paid')],'total');if($r->filled('q'))$q->where(fn($a)=>$a->where('name','like','%'.$r->input('q').'%')->orWhere('email','like','%'.$r->input('q').'%'));$customers=$q->latest()->paginate(20)->withQueryString();return view('admin.customers',compact('customers'));}
 public function reports(Request $r){$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);$from=$r->filled('from')?$r->date('from'):now()->subDays(30);$to=$r->filled('to')?$r->date('to')->endOfDay():now();$orders=Order::whereBetween('created_at',[$from,$to]);$paid=(clone $orders)->where('financial_status','paid');$metrics=['revenue'=>(clone $paid)->sum('total'),'count'=>(clone $paid)->count(),'cancelled'=>(clone $orders)->whereIn('financial_status',['cancelled','declined'])->count(),'discount'=>(clone $paid)->sum('discount'),'shipping'=>(clone $paid)->sum('shipping')];$top=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')->where('orders.financial_status','paid')->whereBetween('orders.created_at',[$from,$to])->select('order_items.name')->selectRaw('SUM(order_items.quantity) qty, SUM(order_items.total) revenue')->groupBy('order_items.name')->orderByDesc('revenue')->limit(15)->get();return view('admin.reports',compact('metrics','top'));}
 public function export(Request $r){$this->manager();$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);$orders=Order::where('financial_status','paid')->when($r->filled('from'),fn($q)=>$q->where('created_at','>=',$r->date('from')->startOfDay()))->when($r->filled('to'),fn($q)=>$q->where('created_at','<=',$r->date('to')->endOfDay()))->latest()->limit(10000)->get();return response()->streamDownload(function()use($orders){$fp=fopen('php://output','w');fwrite($fp,"\xEF\xBB\xBF");fputcsv($fp,['Pedido','Data','Cliente','Subtotal','Desconto','Frete','Total','Status'],';');foreach($orders as $o)fputcsv($fp,[$o->number,$o->created_at->format('d/m/Y'),$o->name,$o->subtotal,$o->discount,$o->shipping,$o->total,$o->financial_status],';');fclose($fp);},'aurora-vendas.csv',['Content-Type'=>'text/csv; charset=UTF-8']);}
 public function resource(Request $r,string $resource){$this->manager();$model=$this->resources()[$resource]??null;abort_unless($model,404);$records=$model::latest()->paginate(18);$editing=$r->filled('edit')?$model::find($r->integer('edit')):null;return view('admin.resource',compact('resource','records','editing'));}
 public function storeResource(Request $r,string $resource){
  $this->manager();$model=$this->resources()[$resource]??null;abort_unless($model,404);
  $id=$r->integer('id')?:null;$record=$id?$model::findOrFail($id):new $model;
  if($resource==='avaliacoes'||$resource==='devolucoes'){$d=$r->validate([$resource==='avaliacoes'?'approved':'status'=>$resource==='avaliacoes'?'required|boolean':'required|in:requested,reviewing,approved,rejected,completed']);$record->update($d);Audit::record('moderate',$resource,$record->id);return back()->with('success','Status atualizado.');}
  if(in_array($resource,['categorias','marcas','colecoes'],true)){
   $d=$r->validate(['name'=>'required|string|max:150','description'=>'nullable|string','active'=>'sometimes|boolean','image_file'=>'nullable|image|mimes:jpg,png,webp|max:4096','sort_order'=>'nullable|integer|min:0']);
   $d['active']=$r->boolean('active');$d['sort_order']=$d['sort_order']??0;
   $d['slug']=$record->exists?$record->slug:Str::slug($d['name']).'-'.strtolower(Str::random(3));
   unset($d['image_file']);if($r->hasFile('image_file'))$d['image']='/storage/'.$r->file('image_file')->store('categories','public');
  }elseif($resource==='banners'){
   $d=$r->validate(['title'=>'required|string|max:190','subtitle'=>'nullable|string|max:255','button_text'=>'nullable|string|max:100','button_url'=>['nullable','max:255',function($attribute,$value,$fail){if((!str_starts_with($value,'/') || str_starts_with($value,'//') || str_contains($value,'\\')) && !str_starts_with($value,'https://'))$fail('Use uma URL interna ou HTTPS.');}],'position'=>'required|in:home,secondary','sort_order'=>'nullable|integer','active'=>'sometimes|boolean','image_file'=>'nullable|image|mimes:jpg,png,webp|max:4096']);
   unset($d['image_file']);$d['active']=$r->boolean('active');
   if($r->hasFile('image_file'))$d['image']='/storage/'.$r->file('image_file')->store('banners','public');
   if(!$record->exists&&!isset($d['image']))$d['image']='/demo/hero.png';
  }else{
   $d=$r->validate(['code'=>['required','string','max:35',Rule::unique('coupons','code')->ignore($id)],'type'=>'required|in:percent,fixed','value'=>'required|numeric|min:0','min_order'=>'nullable|numeric|min:0','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at','max_uses'=>'nullable|integer|min:1','per_user_limit'=>'nullable|integer|min:1','active'=>'sometimes|boolean']);
   $d['code']=strtoupper(trim($d['code']));$d['active']=$r->boolean('active');
   $d['min_order']=$d['min_order']??0;
   if($d['type']==='percent'&&$d['value']>100)throw ValidationException::withMessages(['value'=>'Máximo 100%.']);
  }
  $record->fill($d)->save();Audit::record($id?'update':'create',$resource,$record->id);return redirect()->route('admin.resource',$resource)->with('success','Registro salvo.');
 }
 public function deleteResource(string $resource,int $id){$this->manager();$model=$this->resources()[$resource]??null;abort_unless($model,404);$record=$model::findOrFail($id);if(in_array($resource,['categorias','marcas','colecoes'],true)&&$record->products()->exists())throw ValidationException::withMessages(['record'=>'Há produtos vinculados. Desative o registro em vez de excluir.']);$record->delete();Audit::record('delete',$resource,$id);return back()->with('success','Registro excluído.');}
 public function settings(){$this->manager();$settingValues=Setting::pluck('value','key');return view('admin.settings',compact('settingValues'));}
 public function saveSettings(Request $r){$this->manager();$fields=['store_name','store_subtitle','store_email','store_phone','store_whatsapp','store_address','announcement','primary_color','accent_color','free_shipping_min','social_instagram','social_facebook','about_text','shipping_text','returns_text','seo_title','seo_description'];$d=$r->validate(['store_name'=>'required|string|max:150','store_subtitle'=>'nullable|string|max:150','store_email'=>'nullable|email|max:190','store_phone'=>'nullable|string|max:25','store_whatsapp'=>'nullable|string|max:25','store_address'=>'nullable|string|max:255','announcement'=>'nullable|string|max:180','primary_color'=>'nullable|regex:/^#[a-fA-F0-9]{6}$/','accent_color'=>'nullable|regex:/^#[a-fA-F0-9]{6}$/','free_shipping_min'=>'nullable|numeric|min:0','social_instagram'=>'nullable|url|max:255','social_facebook'=>'nullable|url|max:255','about_text'=>'nullable|string|max:10000','shipping_text'=>'nullable|string|max:10000','returns_text'=>'nullable|string|max:10000','seo_title'=>'nullable|string|max:180','seo_description'=>'nullable|string|max:300','logo'=>'nullable|image|mimes:png,jpg,webp|max:2048','favicon'=>'nullable|image|mimes:png,ico|max:1024']);foreach($fields as $key)Setting::updateOrCreate(['key'=>$key],['value'=>$d[$key]??'']);foreach(['logo','favicon'] as $k)if($r->hasFile($k))Setting::updateOrCreate(['key'=>$k],['value'=>'/storage/'.$r->file($k)->store('branding','public')]);Audit::record('update','settings');return back()->with('success','Configurações salvas.');}
}
