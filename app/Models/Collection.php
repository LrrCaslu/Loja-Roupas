<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Collection extends Model {
 protected $table='collections';
 protected $guarded=[];
 protected function casts(): array {return ['active'=>'boolean'];}
 public function products(){return $this->hasMany(Product::class);}
}
