<?php
namespace App\Services;
final class SmsDeliveryException extends \RuntimeException {
 public function __construct(string $message,public readonly bool $uncertain=false,public readonly bool $retryable=false){parent::__construct($message);}
}
