<?php

namespace App\Contracts;

interface CepLookup
{
    /** @return array{postal_code:string,street:string,neighborhood:string,city:string,state:string}|null */
    public function find(string $postalCode): ?array;
}
