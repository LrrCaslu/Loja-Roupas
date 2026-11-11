<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model {
 public function getRouteKeyName(): string {return 'slug';}
 protected $table='products';
 protected $guarded=[];
 protected function casts(): array {return ['price'=>'decimal:2','sale_price'=>'decimal:2','active'=>'boolean','featured'=>'boolean','is_new'=>'boolean'];}
 public function category(){return $this->belongsTo(Category::class);} public function brand(){return $this->belongsTo(Brand::class);} public function collection(){return $this->belongsTo(Collection::class);} public function variants(){return $this->hasMany(Variant::class);} public function images(){return $this->hasMany(ProductImage::class)->orderBy('sort_order');} public function orderItems(){return $this->hasMany(OrderItem::class);} public function reviews(){return $this->hasMany(Review::class)->where('approved',true);} public function getCurrentPriceAttribute(){return $this->sale_price && $this->sale_price < $this->price?$this->sale_price:$this->price;} public function getCoverAttribute(){return $this->images->first()?->path ?? '/demo/items/garment-01.svg';}
}
