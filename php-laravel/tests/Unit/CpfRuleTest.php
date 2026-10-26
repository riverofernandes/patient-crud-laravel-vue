<?php

namespace Tests\Unit;

use App\Rules\Cpf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CpfRuleTest extends TestCase
{
    public static function validCpfs(): array
    {
        return [
            'digits only' => ['62518953060'],
            'masked' => ['625.189.530-60'],
            'another' => ['52998224725'],
        ];
    }

    public static function invalidCpfs(): array
    {
        return [
            'wrong check digits' => ['62518953061'],
            'repeated digits' => ['11111111111'],
            'zeros' => ['00000000000'],
            'too short' => ['1234567890'],
            'too long' => ['625189530600'],
            'letters' => ['abcdefghijk'],
            'empty' => [''],
            'null' => [null],
            'array' => [['62518953060']],
        ];
    }

    #[DataProvider('validCpfs')]
    public function test_it_accepts_valid_cpfs(string $cpf): void
    {
        $this->assertTrue($this->passes($cpf));
    }

    #[DataProvider('invalidCpfs')]
    public function test_it_rejects_invalid_cpfs(mixed $cpf): void
    {
        $this->assertFalse($this->passes($cpf));
    }

    public function test_failure_message_is_in_portuguese(): void
    {
        $message = null;

        (new Cpf())->validate('cpf', '12345678900', function (string $msg) use (&$message) {
            $message = $msg;
        });

        $this->assertSame('O CPF informado é inválido.', $message);
    }

    private function passes(mixed $value): bool
    {
        $passed = true;

        (new Cpf())->validate('cpf', $value, function () use (&$passed) {
            $passed = false;
        });

        return $passed;
    }
}
