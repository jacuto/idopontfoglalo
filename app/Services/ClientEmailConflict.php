<?php
namespace App\Services;
final class ClientEmailConflict extends \InvalidArgumentException
{
 public function __construct(public readonly array $matches){parent::__construct('Ezzel az e-mail-címmel már van vendég. Válaszd ki a meglévő vendéget, vagy adj meg másik e-mail-címet.');}
 public function payload():array{return ['error'=>$this->getMessage(),'code'=>'client_email_conflict','matches'=>$this->matches,'errors'=>['email'=>[$this->getMessage()]]];}
}
