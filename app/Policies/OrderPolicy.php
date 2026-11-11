<?php
namespace App\Policies;
use App\Models\{Order,User};
class OrderPolicy {
 public function view(User $user,Order $order): bool {return $user->id===$order->user_id || $user->isStaff();}
 public function cancel(User $user,Order $order): bool {return $user->id===$order->user_id && $order->financial_status==='pending';}
}
