<?php

namespace App\Exception;

class InsufficientBalanceException extends \RuntimeException
{
    public function __construct(string $message = 'Solde insuffisant pour effectuer cette opération')
    {
        parent::__construct($message, 400);
    }
}
