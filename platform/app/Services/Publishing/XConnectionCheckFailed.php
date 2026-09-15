<?php

namespace App\Services\Publishing;

final class XConnectionCheckFailed extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
