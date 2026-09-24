<?php

namespace Tests\Unit;

use App\Services\AmountInWordsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsServiceTest extends TestCase
{
    private AmountInWordsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmountInWordsService;
    }

    #[DataProvider('amounts')]
    public function test_conversion(float $amount, string $expected): void
    {
        $this->assertSame($expected, $this->service->convert($amount));
    }

    public static function amounts(): array
    {
        return [
            'zero' => [0, 'Zero Rupees Only'],
            'one rupee' => [1, 'One Rupee Only'],
            'nineteen' => [19, 'Nineteen Rupees Only'],
            'twenty one' => [21, 'Twenty One Rupees Only'],
            'one hundred' => [100, 'One Hundred Rupees Only'],
            'thousand' => [1000, 'One Thousand Rupees Only'],
            'lakh grouping' => [123456, 'One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only'],
            'with paise' => [123456.78, 'One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees and Seventy Eight Paise Only'],
            'crore grouping' => [12345678, 'One Crore Twenty Three Lakh Forty Five Thousand Six Hundred Seventy Eight Rupees Only'],
            'paise only' => [0.99, 'Ninety Nine Paise Only'],
            'one rupee one paisa' => [1.01, 'One Rupee and One Paise Only'],
            'negative' => [-50, 'Minus Fifty Rupees Only'],
        ];
    }

    public function test_paise_rounding_carries_into_rupees(): void
    {
        // 99.999 must not render as "Ninety Nine Rupees and One Hundred Paise".
        $this->assertSame('One Hundred Rupees Only', $this->service->convert(99.999));
    }
}
