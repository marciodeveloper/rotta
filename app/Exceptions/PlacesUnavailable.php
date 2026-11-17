<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class PlacesUnavailable extends RuntimeException implements ShouldntReport
{
    public function __construct(string $message, public readonly int $httpStatus = 503, public readonly array $diagnostics = [])
    {
        parent::__construct($message);
    }
}
