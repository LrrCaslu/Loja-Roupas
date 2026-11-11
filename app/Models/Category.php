<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Category extends Model {
 public function getRouteKeyName(): string {return 'slug';}
 protected $table='categories';
 protected $guarded=[];
 protected function casts(): array {return ['active'=>'boolean'];}
 public function products(){return $this->hasMany(Product::class);}
}
