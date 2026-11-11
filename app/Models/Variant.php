<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Variant extends Model {
 protected $table='product_variants';
 protected $guarded=[];
 protected function casts(): array {return ['stock'=>'integer','reserved'=>'integer','active'=>'boolean'];}
 public function product(){return $this->belongsTo(Product::class);} public function getAvailableAttribute(){return $this->active ? max(0,$this->stock-$this->reserved) : 0;}
}
