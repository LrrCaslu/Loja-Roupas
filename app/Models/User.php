<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','email','password','role','phone','active'];
 protected $hidden=['password','remember_token'];
 protected function casts(): array { return ['password'=>'hashed','email_verified_at'=>'datetime','active'=>'boolean']; }
 public function orders(){return $this->hasMany(Order::class);}
 public function addresses(){return $this->hasMany(Address::class);}
 public function favorites(){return $this->belongsToMany(Product::class,'favorites');}
 public function isStaff(): bool {return in_array($this->role,['superadmin','admin','operator'],true);}
}
