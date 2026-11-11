<?php
namespace Tests\Feature;
use App\Models\{Category,Order,Product,Review,User,Variant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRegressionTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void { parent::setUp(); $this->seed(\Database\Seeders\DatabaseSeeder::class); }
 private function client(): User {return User::where('email','cliente@auroramultimarcas.test')->firstOrFail();}
 private function admin(): User {return User::where('email','admin@auroramultimarcas.test')->firstOrFail();}

 public function test_category_page_does_not_compare_bound_model_to_integer():void {
  $category=Category::where('slug','moda-feminina')->firstOrFail();
  $this->get('/categoria/'.$category->slug)->assertOk()->assertSee('Moda Feminina');
  $this->get('/categoria/'.$category->slug.'?sort=price_asc&available=1')->assertOk();
  $this->get('/categoria/'.$category->slug.'?category=999')->assertOk();
 }
 public function test_catalog_filters_and_sort_work_independently():void {
  $this->get('/loja?category='.Category::firstOrFail()->id.'&sort=price_desc&size=M&color=Preto')->assertOk();
 }
 public function test_product_page_exposes_its_own_variants_when_related_products_exist():void {
  $product=Product::where('slug','blazer-solene-alfaiataria-0')->firstOrFail();
  $expectedId=$product->variants()->firstOrFail()->id;
  $result=$this->get('/produto/'.$product->slug)->assertOk();
  $result->assertSee('window.AURORA_VARIANTS=',false)->assertSee('"id":'.$expectedId,false);
 }
 public function test_screenshot_product_url_compiles_and_displays_variations():void {
  $product = Product::where('slug', 'camiseta-algodao-premium-12')->firstOrFail();
  $response = $this->get('/produto/'.$product->slug)->assertOk();
  $response->assertSee('window.AURORA_VARIANTS=', false);
  $response->assertSee('id="color-choices"', false);
  $response->assertSee('id="size-choices"', false);
 }
 public function test_inventory_page_eager_loads_stock_movement_variant():void {
  $this->actingAs($this->admin());
  $this->get('/admin/estoque')->assertOk()->assertSee('Últimas movimentações');
  $movement=\App\Models\StockMovement::with('variant.product')->firstOrFail();
  $this->assertNotNull($movement->variant);
  $this->assertNotNull($movement->variant->product);
 }
 public function test_archived_variation_cannot_be_purchased():void {
  $v=Variant::where('active',true)->firstOrFail();
  $v->update(['active'=>false]);
  $this->post('/carrinho/adicionar',['variant_id'=>$v->id,'quantity'=>1])->assertSessionHasErrors('variant_id');
 }
 public function test_duplicate_review_is_edited_not_inserted_twice():void {
  $this->actingAs($this->client());
  $product=Product::firstOrFail();
  $url='/produto/'.$product->slug.'/avaliacao';
  $this->post($url,['rating'=>4,'body'=>'Produto confortável, bom acabamento e ótimo caimento.'])->assertRedirect();
  $this->post($url,['rating'=>5,'body'=>'Depois de usar, gostei bastante da qualidade.'])->assertRedirect();
  $this->assertEquals(1,Review::where('user_id',$this->client()->id)->where('product_id',$product->id)->whereNull('order_id')->count());
 }
 public function test_customer_cannot_submit_return_for_pending_order():void {
  $client=$this->client();$order=Order::where('user_id',$client->id)->where('financial_status','pending')->first();
  if(!$order){$this->markTestSkipped('Cliente sem pedido pendente nos seeders.');return;}
  $this->actingAs($client)->post('/pedido/'.$order->id.'/devolucao',['type'=>'return','reason'=>'Desejo devolver esta compra por motivos pessoais'])->assertUnprocessable();
 }
 public function test_pending_order_cannot_be_marked_as_shipped():void {
  $order=Order::where('financial_status','pending')->firstOrFail();
  $this->actingAs($this->admin())->patch('/admin/pedidos/'.$order->id,['fulfillment_status'=>'shipped','shipping_status'=>'shipped'])->assertSessionHasErrors();
  $this->assertEquals('pending',$order->fresh()->shipping_status);
 }
 public function test_invalid_report_dates_are_validation_errors_not_500():void {
  $this->actingAs($this->admin())->get('/admin/relatorios?from=nao-e-uma-data')->assertSessionHasErrors('from');
 }
 public function test_inactive_customer_cannot_access_account():void {
  $client=$this->client();$client->update(['active'=>false]);
  $this->actingAs($client)->get('/minha-conta')->assertForbidden();
 }
}
