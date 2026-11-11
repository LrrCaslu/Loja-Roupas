<?php
namespace Tests\Feature;
use App\Models\{Cart,CartItem,Order,Product,User,Variant};
use App\Services\{CartService,PaymentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CommerceFlowTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void { parent::setUp(); $this->seed(\Database\Seeders\DatabaseSeeder::class); }
 private function customer(): User {return User::where('email','cliente@auroramultimarcas.test')->firstOrFail();}
 private function admin(): User {return User::where('email','admin@auroramultimarcas.test')->firstOrFail();}
 private function variant(): Variant {return Variant::whereRaw('stock > reserved')->firstOrFail();}
 private function shipping(): array {return ['name'=>'Cliente Teste','email'=>'cliente@auroramultimarcas.test','zip'=>'88200000','address'=>'Rua Teste, 10','district'=>'Centro','city'=>'Tijucas','state'=>'SC','shipping_method'=>'standard'];}
 public function test_home_and_catalog_are_public():void {$this->get('/')->assertOk();$this->get('/loja')->assertOk();}
 public function test_non_admin_cannot_access_dashboard():void {$this->actingAs($this->customer())->get('/admin')->assertForbidden();$this->actingAs($this->admin())->get('/admin')->assertOk();}
 public function test_demo_user_can_login():void {$this->post('/entrar',['email'=>'cliente@auroramultimarcas.test','password'=>'Cliente@123456'])->assertRedirect();$this->assertAuthenticated();}
 public function test_product_variations_are_visible():void {$p=Product::firstOrFail();$this->get('/produto/'.$p->slug)->assertOk()->assertSee($p->sku);}
 public function test_cart_rejects_stock_above_available():void {$v=$this->variant();$this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>$v->available+1])->assertSessionHasErrors('quantity');}
 public function test_cart_and_order_create_real_records():void {
  $this->actingAs($this->customer());$v=$this->variant();$available=$v->available;
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1])->assertRedirect('/carrinho');
  $this->post('/finalizar',$this->shipping())->assertRedirect();
  $o=Order::where('user_id',$this->customer()->id)->latest('id')->firstOrFail();
  $this->assertEquals('pending',$o->financial_status);$this->assertEquals($available-1,$v->fresh()->available);
  $this->assertEquals(1,$o->items()->count());
 }
 public function test_payment_replay_moves_stock_exactly_once():void {
  $this->actingAs($this->customer());$v=$this->variant();$start=$v->stock;
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1]);$this->post('/finalizar',$this->shipping());
  $o=Order::where('user_id',$this->customer()->id)->latest('id')->firstOrFail();
  $this->post('/pedido/'.$o->id.'/pagamento-demo',['decision'=>'approved'])->assertRedirect();
  $this->post('/pedido/'.$o->id.'/pagamento-demo',['decision'=>'approved'])->assertRedirect();
  $this->assertEquals($start-1,$v->fresh()->stock);$this->assertEquals('paid',$o->fresh()->financial_status);
 }
 public function test_two_customers_cannot_reserve_the_same_last_unit():void {
  $v=$this->variant();$v->update(['stock'=>1,'reserved'=>0]);
  $first=$this->customer();$second=User::where('role','customer')->where('id','!=',$first->id)->firstOrFail();
  $this->actingAs($first)->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1]);
  $this->post('/finalizar',$this->shipping());
  $this->assertEquals(1,$v->fresh()->reserved);
  $this->actingAs($second)->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1])->assertSessionHasErrors('quantity');
  $this->assertGreaterThanOrEqual(0,$v->fresh()->available);
 }
 public function test_customer_cannot_open_another_users_order():void {
  $o=Order::where('user_id','!=',$this->customer()->id)->firstOrFail();
  $this->actingAs($this->customer())->get('/pedido/'.$o->id)->assertForbidden();
 }
 public function test_admin_can_update_stock_with_movement_history():void {
  $v=$this->variant();$initial=$v->stock;
  $this->actingAs($this->admin())->post('/admin/estoque/'.$v->id,['change'=>2,'reason'=>'Reposição'])->assertRedirect();
  $this->assertEquals($initial+2,$v->fresh()->stock);
  $this->assertDatabaseHas('stock_movements',['variant_id'=>$v->id,'change'=>2,'type'=>'manual']);
 }
 public function test_demo_rejection_releases_stock_reservation():void {
  $this->actingAs($this->customer());$v=$this->variant();$start=$v->available;
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1]);$this->post('/finalizar',$this->shipping());
  $o=Order::where('user_id',$this->customer()->id)->latest('id')->firstOrFail();
  $this->assertEquals($start-1,$v->fresh()->available);
  $this->post('/pedido/'.$o->id.'/pagamento-demo',['decision'=>'declined'])->assertRedirect();
  $this->assertEquals('declined',$o->fresh()->financial_status);$this->assertEquals($start,$v->fresh()->available);
 }
 public function test_cancel_pending_order_releases_reservations():void {
  $this->actingAs($this->customer());$v=$this->variant();$start=$v->available;
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1]);$this->post('/finalizar',$this->shipping());
  $o=Order::where('user_id',$this->customer()->id)->latest('id')->firstOrFail();
  $this->post('/pedido/'.$o->id.'/cancelar')->assertRedirect();
  $this->assertEquals($start,$v->fresh()->available);$this->assertEquals('cancelled',$o->fresh()->financial_status);
 }
 public function test_coupon_changes_calculated_total():void {
  $this->actingAs($this->customer());$v=Variant::whereHas('product',fn($p)=>$p->where('price','>=',150))->whereRaw('stock > reserved')->firstOrFail();
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1]);
  $this->post('/carrinho/cupom',['code'=>'BEMVINDA10'])->assertRedirect();
  $this->assertEquals('BEMVINDA10',session('coupon'));
  $this->post('/finalizar',$this->shipping());
  $o=Order::where('user_id',$this->customer()->id)->latest('id')->firstOrFail();$this->assertGreaterThan(0,(float)$o->discount);
 }
 public function test_staff_can_create_product_with_variants():void {
  $this->actingAs($this->admin());$cat=\App\Models\Category::firstOrFail();
  $this->post('/admin/produtos',['name'=>'Peça Nova Teste','sku'=>'UNIT-NEW-001','price'=>199.90,'category_id'=>$cat->id,'active'=>1,'variants'=>[['size'=>'M','color'=>'Areia','stock'=>4],['size'=>'G','color'=>'Preto','stock'=>5]]])->assertRedirect();
  $product=Product::where('sku','UNIT-NEW-001')->firstOrFail();$this->assertEquals(2,$product->variants()->count());
  $this->assertDatabaseHas('stock_movements',['variant_id'=>$product->variants()->first()->id,'type'=>'adjustment']);
 }
 public function test_operator_cannot_edit_store_settings():void {
  $op=User::where('role','operator')->firstOrFail();$this->actingAs($op)->get('/admin/configuracoes')->assertForbidden();
 }
 public function test_refund_demo_is_not_performed_twice():void {
  $this->actingAs($this->customer());$v=$this->variant();$start=$v->stock;
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1]);$this->post('/finalizar',$this->shipping());
  $o=Order::where('user_id',$this->customer()->id)->latest('id')->firstOrFail();
  $this->post('/pedido/'.$o->id.'/pagamento-demo',['decision'=>'approved']);
  $this->actingAs($this->admin())->post('/admin/pedidos/'.$o->id.'/reembolsar-demo',['restock'=>1])->assertRedirect();
  $this->post('/admin/pedidos/'.$o->id.'/reembolsar-demo',['restock'=>1])->assertRedirect();
  $this->assertEquals('refunded',$o->fresh()->financial_status);$this->assertEquals($start,$v->fresh()->stock);
 }

}
