<?php
namespace App\Services;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;
final class AttemptLimit{
 public static function check(string $key,int $max):void{if(RateLimiter::tooManyAttempts($key,$max))throw new HttpException(429,'Túl sok próbálkozás.',null,['Retry-After'=>(string)max(1,RateLimiter::availableIn($key))]);}
}
