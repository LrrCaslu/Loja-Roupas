<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\{User,Category,Brand,Collection,Product,Variant,Order,Payment,Review,Banner,Coupon,Setting,StockMovement};
class DatabaseSeeder extends Seeder {
 public function run():void {
  // Protege ambientes reais de credenciais de demonstração conhecidas.
  if(!app()->environment('local','testing')){$this->command?->warn('Seeders de demonstração são exclusivos do ambiente local/testing.');return;}
  $users=[['Admin Aurora','admin@auroramultimarcas.test','Admin@123456','superadmin'],['Gerente Aurora','gerente@auroramultimarcas.test','Gerente@123456','admin'],['Operador Aurora','operador@auroramultimarcas.test','Operador@123456','operator'],['Cliente Teste','cliente@auroramultimarcas.test','Cliente@123456','customer']];
  foreach($users as [$name,$email,$password,$role])User::updateOrCreate(['email'=>$email],['name'=>$name,'password'=>$password,'role'=>$role,'active'=>true]);
  if(Product::exists())return;
  $cats=['Moda Feminina','Moda Masculina','Moda Infantil','Calçados','Acessórios','Novidades','Promoções'];$cids=[];
  foreach($cats as $i=>$name){$cids[]=Category::create(['name'=>$name,'slug'=>\Illuminate\Support\Str::slug($name),'sort_order'=>$i,'active'=>true])->id;}
  $bnames=['Aurora Atelier','Norte Studio','Linnea','Terracota','Vento & Mar','Bossa Essentials','Lume Active'];$bids=[];
  foreach($bnames as $i=>$name)$bids[]=Brand::create(['name'=>$name,'slug'=>\Illuminate\Support\Str::slug($name),'sort_order'=>$i])->id;
  $col=Collection::create(['name'=>'Essenciais Atemporais','slug'=>'essenciais-atemporais','description'=>'Peças que acompanham cada estação.']);
  $names=['Blazer Solene Alfaiataria','Camisa Linho Areia','Calça Wide Leg Areia','Trench Coat Firenze','Vestido Midi Canelado','Saia Midi Plissada','Cardigan Lume','Blusa Ampla Serena','Jaqueta Couro Eco','Calça Reta Alfaiataria','Macacão Minimal','Vestido Aurora','Camiseta Algodão Premium','Camisa Oxford Azul','Calça Chino Urban','Jaqueta Bomber Clássica','Polo Piquet Natural','Moletom Essential','Bermuda Cargo','Suéter Tricot Fino','Camisa Social Linho','Calça Jeans Slim','Blazer Masculino Oslo','Camiseta Oversized','Conjunto Infantil Doce','Vestido Infantil Flor','Camiseta Infantil Play','Moletom Kids Conforto','Bermuda Infantil Cotton','Casaco Infantil Inverno','Tênis Urbano Minimal','Tênis Casual Off White','Sandália Trançada','Bota Chelsea Clássica','Sapatilha Elegance','Sneaker Retro','Bolsa Couro Vegano','Bolsa Tiracolo Mini','Cinto Fivela Dourada','Óculos de Sol Square','Echarpe Textura','Carteira Slim','Boné Heritage','Brinco Argola Clássico','Mochila City'];
  $prices=[289,179,249,499,229,189,239,129,399,269,319,349,99,169,219,339,139,189,159,209,199,259,449,119,169,149,79,139,99,219,299,329,189,389,159,279,379,219,99,129,89,139,119,79,269];
  foreach($names as $i=>$name){
   $cat=$i<12?0:($i<24?1:($i<30?2:($i<36?3:4)));
   $price=$prices[$i];$onSale=$i%6===0;
   $p=Product::create(['name'=>$name,'slug'=>\Illuminate\Support\Str::slug($name).'-'.$i,'sku'=>'AUR-'.str_pad($i+1,4,'0',STR_PAD_LEFT),'category_id'=>$cids[$cat],'brand_id'=>$bids[$i%count($bids)],'collection_id'=>$col->id,'description'=>'Uma peça essencial para composições elegantes e contemporâneas. Conforto, caimento impecável e atenção aos detalhes em cada acabamento.','details'=>'Desenvolvido para combinar versatilidade e acabamento premium. Ideal para o dia a dia e ocasiões especiais.','care'=>'Lavar com cores similares. Secar à sombra e seguir a etiqueta de composição.','price'=>$price,'sale_price'=>$onSale?round($price*.82,2):null,'featured'=>$i<10,'is_new'=>$i>=26,'active'=>true,'meta_title'=>$name.' | Aurora Multimarcas']);
   $p->images()->create(['path'=>'/demo/items/garment-'.str_pad($i+1,2,'0',STR_PAD_LEFT).'.svg','alt'=>$name,'sort_order'=>0]);
   $sizes=$cat===3?['36','37','38','39']:($cat===4?['Único']:($cat===2?['4','6','8','10']:['P','M','G','GG']));
   foreach($sizes as $j=>$size){
    foreach(['Areia','Preto'] as $k=>$color){$stock=6+(($i+$j*3+$k*7)%23);
     $v=$p->variants()->create(['sku'=>$p->sku.'-'.$j.'-'.$k,'size'=>$size,'color'=>$color,'stock'=>$stock,'reserved'=>0,'min_stock'=>4]);
     StockMovement::create(['variant_id'=>$v->id,'type'=>'entry','change'=>$stock,'before'=>0,'after'=>$stock,'reason'=>'Carga inicial demonstrativa']);
    }
   }
  }
  for($i=0;$i<15;$i++)User::create(['name'=>['Mariana','Lucas','Camila','Pedro','Fernanda','Bruno','Sofia','Gabriel','Ana','Rafael','Laura','Miguel','Bianca','Henrique','Julia'][$i].' Silva','email'=>'cliente'.($i+1).'@exemplo.test','password'=>'Cliente@123456','role'=>'customer','phone'=>'4899999'.str_pad($i,4,'0',STR_PAD_LEFT)]);
  $customers=User::where('role','customer')->get();$products=Product::with('variants')->get();
  for($i=0;$i<30;$i++){
   $user=$customers[$i%$customers->count()];$p=$products[($i*7)%$products->count()];$v=$p->variants[($i*3)%$p->variants->count()];$qty=1+($i%2);$unit=(float)$p->current_price;$subtotal=$unit*$qty;$shipping=$subtotal>=299?0:18.90;
   $status= $i%5===0?'pending':($i%7===0?'declined':'paid');
   $date=now()->subDays(($i*2)%39)->subHours($i%13);
   $order=Order::create(['number'=>'AUR-DEMO-'.str_pad($i+1,4,'0',STR_PAD_LEFT),'user_id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'zip'=>'88200000','address'=>'Rua da Moda, 100','district'=>'Centro','city'=>'Tijucas','state'=>'SC','subtotal'=>$subtotal,'discount'=>0,'shipping'=>$shipping,'total'=>$subtotal+$shipping,'financial_status'=>$status,'fulfillment_status'=>$status==='paid'?['processing','packed','shipped','delivered'][$i%4]:($status==='pending'?'new':'cancelled'),'shipping_status'=>$status==='paid'?'preparing':'pending','created_at'=>$date,'updated_at'=>$date]);
   $order->items()->create(['product_id'=>$p->id,'variant_id'=>$v->id,'name'=>$p->name,'sku'=>$v->sku,'variant_label'=>$v->color.' / '.$v->size,'quantity'=>$qty,'unit_price'=>$unit,'total'=>$subtotal]);
   $order->payments()->create(['provider'=>'demo','idempotency_key'=>'seed-'.$order->number,'amount'=>$order->total,'status'=>$status==='paid'?'approved':$status]);
   $order->shipment()->create(['method'=>'standard','price'=>$shipping,'estimate_days'=>7,'status'=>'pending']);
   if($status==='paid'){$before=$v->stock;$v->decrement('stock',$qty);StockMovement::create(['variant_id'=>$v->id,'type'=>'sale','change'=>-$qty,'before'=>$before,'after'=>$before-$qty,'reason'=>$order->number]);}
   if($status==='pending')$v->increment('reserved',$qty);
  }
  foreach($products->take(9) as $i=>$p)Review::create(['user_id'=>$customers[$i%$customers->count()]->id,'product_id'=>$p->id,'rating'=>4+($i%2),'body'=>'Peça linda, acabamento impecável e entrega cuidadosa. Recomendo a experiência.','verified'=>false,'approved'=>true]);
  Banner::create(['title'=>'Elegância em cada detalhe','subtitle'=>'Coleção Essenciais • peças que acompanham você em todas as estações.','image'=>'/demo/hero.png','button_text'=>'Descobrir coleção','button_url'=>'/loja','position'=>'home','sort_order'=>0,'active'=>true]);
  Coupon::create(['code'=>'BEMVINDA10','type'=>'percent','value'=>10,'min_order'=>150,'max_uses'=>500,'per_user_limit'=>1,'active'=>true]);
  $settings=['store_name'=>'AURORA','store_subtitle'=>'MULTIMARCAS','store_email'=>'contato@auroramultimarcas.test','store_phone'=>'(48) 99999-0000','store_whatsapp'=>'5548999990000','announcement'=>'Frete demonstrativo grátis acima de R$ 299  •  Ambiente de testes','primary_color'=>'#171717','accent_color'=>'#b49b84','free_shipping_min'=>'299','about_text'=>'Uma curadoria de moda que valoriza sua essência.','shipping_text'=>'Fretes apresentados no ambiente local são demonstrativos.','returns_text'=>'Solicite trocas ou devoluções pela sua conta. Política de exemplo sujeita a revisão jurídica.','seo_title'=>'Aurora Multimarcas | Moda feminina, masculina e infantil','seo_description'=>'Descubra coleções, marcas e peças especiais na Aurora Multimarcas.'];
  foreach($settings as $k=>$v)Setting::updateOrCreate(['key'=>$k],['value'=>$v]);
 }
}
