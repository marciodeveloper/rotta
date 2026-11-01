<?php

namespace Tests\Unit\Services;

use App\Services\DocumentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentValidatorTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> */
    public static function documents(): array
    {
        return [
            'valid CPF' => ['professional', '529.982.247-25', true],
            'invalid CPF digit' => ['professional', '529.982.247-24', false],
            'repeated CPF' => ['professional', '111.111.111-11', false],
            'valid CNPJ' => ['company', '11.222.333/0001-81', true],
            'invalid CNPJ digit' => ['company', '11.222.333/0001-80', false],
        ];
    }

    #[DataProvider('documents')]
    public function test_validates_document_check_digits_for_applicant_type(string $type, string $document, bool $expected): void
    {
        $validator = new DocumentValidator;

        $result = $validator->isValidFor($type, $document);

        $this->assertSame($expected, $result);
    }
}
