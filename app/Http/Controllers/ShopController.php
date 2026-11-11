<?php
namespace App\Http\Controllers;
use App\Models\{Banner,Brand,Category,Collection,NewsletterSubscriber,Product,Review,Setting};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ShopController extends Controller {
 public function home(){
  $featured=Product::with('images','brand')->where('active',true)->where('featured',true)->latest()->limit(8)->get();
  $new=Product::with('images','brand')->where('active',true)->orderByDesc('created_at')->limit(8)->get();
  $sale=Product::with('images','brand')->where('active',true)->whereNotNull('sale_price')->limit(8)->get();
  $banner=Banner::where('active',true)->where('position','home')->orderBy('sort_order')->first();
  $secondary=Banner::where('active',true)->where('position','secondary')->orderBy('sort_order')->first();
  $brands=Brand::where('active',true)->orderBy('sort_order')->limit(8)->get();
  $categoriesHome=Category::where('active',true)->orderBy('sort_order')->limit(7)->get();
  return view('shop.home',compact('featured','new','sale','banner','secondary','brands','categoriesHome'));
 }
 public function catalog(Request $request,?Category $category=null){
  $query=Product::query()->with('images','brand','category')->where('active',true);
  if($category)$query->where('category_id',$category->id);
  if($request->filled('q')){ $s=trim((string)$request->string('q'));$query->where(function($q)use($s){$q->where('name','like',"%{$s}%")->orWhere('description','like',"%{$s}%")->orWhere('sku','like',"%{$s}%")->orWhereHas('brand',fn($b)=>$b->where('name','like',"%{$s}%"));});}
  if(!$category && $request->filled('category'))$query->where('category_id',$request->integer('category'));
  if($request->filled('brand'))$query->where('brand_id',$request->integer('brand'));
  if($request->filled('min'))$query->whereRaw('COALESCE(sale_price,price) >= ?',[(float)$request->input('min')]);
  if($request->filled('max'))$query->whereRaw('COALESCE(sale_price,price) <= ?',[(float)$request->input('max')]);
  if($request->filled('size'))$query->whereHas('variants',fn($v)=>$v->where('active',true)->where('size',$request->input('size'))->whereRaw('stock > reserved'));
  if($request->filled('color'))$query->whereHas('variants',fn($v)=>$v->where('active',true)->where('color',$request->input('color'))->whereRaw('stock > reserved'));
  if($request->boolean('available'))$query->whereHas('variants',fn($v)=>$v->where('active',true)->whereRaw('stock > reserved'));
  if($request->boolean('sale'))$query->whereNotNull('sale_price');
  match($request->input('sort')){'price_asc'=>$query->orderByRaw('COALESCE(sale_price,price) asc'),'price_desc'=>$query->orderByRaw('COALESCE(sale_price,price) desc'),'popular'=>$query->withSum(['orderItems as units_sold'=>fn($q)=>$q->whereHas('order',fn($o)=>$o->where('financial_status','paid'))],'quantity')->orderByDesc('units_sold'),default=>$query->latest()};
  $products=$query->paginate(12)->withQueryString();
  $brands=Brand::where('active',true)->orderBy('name')->get();
  $allCategories=Category::where('active',true)->orderBy('sort_order')->get();
  return view('shop.catalog',compact('products','brands','allCategories','category'));
 }
 public function category(Request $request,Category $category){return $this->catalog($request,$category);}
 public function product(Product $product)
 {
  abort_unless($product->active, 404);

  $product->load('images', 'brand', 'category', 'reviews.user');
  $product->load(['variants' => fn ($query) => $query->where('active', true)]);

  $related = Product::with('images')
   ->where('active', true)
   ->where('category_id', $product->category_id)
   ->where('id', '!=', $product->id)
   ->limit(4)
   ->get();

  // Prepare o payload no controller; closures dentro de @json() podem gerar
  // PHP inválido ao compilar a view Blade.
  $variantsForJs = $product->variants->map(fn ($variant) => [
   'id' => $variant->id,
   'size' => $variant->size,
   'color' => $variant->color,
   'available' => $variant->available,
  ])->values()->all();

  return view('shop.product', compact('product', 'related', 'variantsForJs'));
 }
 public function suggestions(Request $request){$s=trim((string)$request->input('q',''));return response()->json(strlen($s)<2?[]:Product::where('active',1)->where('name','like',"%{$s}%")->limit(6)->get(['name','slug','price']));}
 public function shippingQuote(Request $request){
  $d=$request->validate(['cep'=>'required|regex:/^[0-9]{5}-?[0-9]{3}$/','value'=>'nullable|numeric|min:0']);
  $net=(float)($d['value']??0);return response()->json(['mode'=>'DEMONSTRATIVO','standard'=>app(\App\Services\ShippingService::class)->price($net,'standard'),'express'=>34.90,'estimated_days'=>[7,3],'message'=>'Valores simulados; não são cotações de transportadora.']);
 }
 public function sitemap(){
  $urls=[route('home'),route('catalog')];
  foreach(Category::where('active',true)->get() as $c)$urls[]=route('category',$c);
  foreach(Product::where('active',true)->get() as $p)$urls[]=route('product',$p);
  $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
  foreach($urls as $url)$xml.='<url><loc>'.htmlspecialchars($url,ENT_XML1,'UTF-8').'</loc></url>';
  return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=UTF-8']);
 }
 public function newsletter(Request $request){$d=$request->validate(['email'=>'required|email|max:190','consent'=>'accepted']);NewsletterSubscriber::firstOrCreate(['email'=>strtolower($d['email'])],['consent_at'=>now()]);return back()->with('success','Inscrição confirmada. Obrigado!');}
 public function page(string $page){$pages=['sobre'=>'Sobre a Aurora','contato'=>'Fale conosco','trocas'=>'Trocas e devoluções','privacidade'=>'Privacidade e LGPD','termos'=>'Termos de uso','ajuda'=>'Perguntas frequentes','medidas'=>'Guia de medidas'];abort_unless(isset($pages[$page]),404);return view('shop.page',['title'=>$pages[$page],'page'=>$page]);}
}
