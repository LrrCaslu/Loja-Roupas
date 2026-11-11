<?php
namespace App\Policies;
use App\Models\{Product,User};
class ProductPolicy {
 public function manage(User $user):bool{return $user->isStaff();}
 public function update(User $user,Product $product):bool{return $user->isStaff();}
}
