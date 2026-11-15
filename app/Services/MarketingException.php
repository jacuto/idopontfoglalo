<?php
namespace App\Services;
/** Safe, user-facing integration errors; never raw provider/SQL responses. */
final class MarketingException extends \RuntimeException {}
