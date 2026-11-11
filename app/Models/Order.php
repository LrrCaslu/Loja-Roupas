<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model {
 protected $table='orders';
 protected $guarded=[];
 protected function casts(): array {return ['total'=>'decimal:2','subtotal'=>'decimal:2','discount'=>'decimal:2','shipping'=>'decimal:2'];}
 public function user(){return $this->belongsTo(User::class);} public function items(){return $this->hasMany(OrderItem::class);} public function payments(){return $this->hasMany(Payment::class);} public function shipment(){return $this->hasOne(Shipment::class);}
}
