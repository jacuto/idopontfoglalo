<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
final class PlanPricing{
 public static function base(array $s,?string $at=null):int{
  $sub=$s['subscription']??[];$plan=$sub['plan']??'basic';$price=$plan==='pro'?(int)($sub['planPrice']??config('plans.pro_price',6000)):4000;
  if($at!==null&&!empty($sub['planHistory'])){
   $plan=$sub['initialPlan']??'basic';$price=$plan==='pro'?(int)($sub['initialPlanPrice']??config('plans.pro_price',6000)):4000;
   foreach($sub['planHistory'] as $h)if(Time::parse($h['startsAt'])->lte(Time::parse($at)))$price=(int)($h['price']??($h['plan']==='pro'?config('plans.pro_price',6000):4000));
  }
  return $price;
 }
}
