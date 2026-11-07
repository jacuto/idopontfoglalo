<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
final class NotificationMail extends Mailable {
 public function __construct(public string $subjectLine,public string $contentHtml,public ?string $replyAddress=null,public ?string $replyName=null){}
 public function build(){ $mail=$this->subject($this->subjectLine)->html($this->contentHtml);if($this->replyAddress)$mail->replyTo($this->replyAddress,$this->replyName);return $mail; }
}
