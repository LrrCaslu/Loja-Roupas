<?php
namespace App\Services;
use App\Models\AuditLog;
class Audit {
 public static function record(string $action,string $entity,$id=null,array $meta=[]): void {
  AuditLog::create(['user_id'=>auth()->id(),'action'=>$action,'entity'=>$entity,'entity_id'=>$id,'metadata'=>json_encode($meta),'ip'=>request()->ip()]);
 }
}
