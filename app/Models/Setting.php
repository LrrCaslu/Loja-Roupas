<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model {
 protected $table='settings';
 protected $guarded=[];
 
 public static function get(string $key, $default=null) {return static::query()->where('key',$key)->value('value') ?? $default;}
}
