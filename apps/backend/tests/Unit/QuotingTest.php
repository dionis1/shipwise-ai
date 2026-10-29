<?php

namespace App\Tests\Unit;

use App\CarrierCatalog\Domain\Eligibility;
use App\CarrierIntegration\Application\CarrierRateProvider;
use App\CarrierIntegration\Application\ProviderRegistry;
use App\CarrierIntegration\Domain\RateResult;
use App\CarrierIntegration\Infrastructure\DevelopmentTariffProvider;
use App\Pricing\Domain\Money;
use App\Pricing\Domain\PriceCalculator;
use App\Quoting\Domain\QuoteViolation;
use App\Quoting\Domain\ShipmentRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuotingTest extends TestCase
{
    public static function shipment(): array
    {
        return ['originCountry' => 'DE', 'originPostalCode' => '10115', 'destinationCountry' => 'FR', 'destinationPostalCode' => '75001', 'weightGrams' => 1001, 'lengthMm' => 300, 'widthMm' => 200, 'heightMm' => 100, 'quantity' => 1, 'handover' => 'any', 'speed' => 'any'];
    }

    public static function invalid(): iterable
    {
        yield ['weightGrams', 0, 'parcel_invalid'];
        yield ['lengthMm', 2.1, 'parcel_invalid'];
        yield ['heightMm', 2001, 'parcel_invalid'];
        yield ['quantity', 2, 'single_parcel_only'];
        yield ['originCountry', 'ZZ', 'country_invalid'];
        yield ['originPostalCode', 'abc', 'postal_invalid'];
        yield ['speed', 'overnight', 'options_invalid'];
        yield ['handover', 'insurance', 'options_invalid'];
    }

    #[DataProvider('invalid')]
    public function testShipmentBoundaries(string $field, mixed $value, string $reason): void
    {
        $data = self::shipment();
        $data[$field] = $value;
        $this->expectExceptionMessage($reason);
        new ShipmentRequest($data);
    }

    public function testDimensionRotationAndRouteRules(): void
    {
        $data = self::shipment();
        $data['lengthMm'] = 100;
        $data['heightMm'] = 300;
        $service = ['enabled' => true, 'carrierEnabled' => true, 'rules' => ['routes' => ['DE:FR'], 'maxWeightGrams' => 2000, 'maxDimensionsMm' => [300, 200, 100], 'handover' => ['drop_off'], 'speed' => 'standard']];
        $eligibility = new Eligibility();
        self::assertSame([], $eligibility->reasons(new ShipmentRequest($data), $service));
        $service['enabled'] = false;
        $service['rules']['routes'] = [];
        $service['rules']['maxWeightGrams'] = 1000;
        $service['rules']['maxDimensionsMm'] = [100, 100, 100];
        self::assertSame(['disabled', 'route_unsupported', 'weight_exceeded', 'dimensions_exceeded'], $eligibility->reasons(new ShipmentRequest($data), $service));
    }

    public function testIntegerRoundingAndPriceBreakdown(): void
    {
        self::assertSame(1, (new Money(5, 'EUR'))->basisPoints(1000)->minor);
        $rule = ['version' => 'v1', 'currency' => 'EUR', 'fixedMarginMinor' => 100, 'marginBasisPoints' => 1000, 'minimumMarginMinor' => 150, 'feeMinor' => 25, 'taxRoutes' => ['DE:FR' => ['basisPoints' => 1900, 'version' => 'synthetic-1']]];
        $price = (new PriceCalculator())->calculate(['baseMinor' => 500, 'surchargeMinor' => 0, 'currency' => 'EUR'], $rule, 'DE:FR');
        self::assertSame(150, $price['marginMinor']);
        self::assertSame(128, $price['taxMinor']);
        self::assertSame(803, $price['totalMinor']);
        $this->expectExceptionMessage('tax_undefined');
        (new PriceCalculator())->calculate(['baseMinor' => 500, 'surchargeMinor' => 0, 'currency' => 'EUR'], $rule, 'US:DE');
    }

    public function testCurrencyMismatch(): void
    {
        $this->expectException(QuoteViolation::class);
        (new Money(10, 'EUR'))->add(new Money(10, 'USD'));
    }

    public function testMoneyOverflow(): void
    {
        $this->expectExceptionMessage('money_invalid');
        (new Money(100000000, 'EUR'))->add(new Money(1, 'EUR'));
    }

    public function testProviderRoundsWeightUpAndRefusesProduction(): void
    {
        $tariff = ['baseMinor' => 400, 'perKgMinor' => 50, 'surchargeMinor' => 0, 'currency' => 'EUR'];
        $request = new ShipmentRequest(self::shipment());
        self::assertSame(500, (new DevelopmentTariffProvider('test'))->quote($request, $tariff)->rate['baseMinor']);
        self::assertSame('unavailable', (new DevelopmentTariffProvider('prod'))->quote($request, $tariff)->status);
    }

    public static function outcomes(): iterable
    {
        foreach (RateResult::FAILURES as $failure) {
            yield [$failure];
        }
    }

    #[DataProvider('outcomes')]
    public function testRegistryPreservesFailureClassification(string $failure): void
    {
        $provider = new class($failure) implements CarrierRateProvider {
            public function __construct(private string $failure)
            {
            }

            public function code(): string
            {
                return 'test';
            }

            public function quote(ShipmentRequest $request, array $tariff): RateResult
            {
                return new RateResult($this->failure);
            }
        };
        self::assertSame($failure, (new ProviderRegistry([$provider]))->quote('test', new ShipmentRequest(self::shipment()), ['currency' => 'EUR'])->status);
    }

    public function testUntrustedProviderResponseIsRejected(): void
    {
        $provider = new class implements CarrierRateProvider {
            public function code(): string
            {
                return 'test';
            }

            public function quote(ShipmentRequest $request, array $tariff): RateResult
            {
                return new RateResult('success', ['baseMinor' => 1.5, 'surchargeMinor' => 0, 'currency' => 'EUR']);
            }
        };
        self::assertSame('invalid_response', (new ProviderRegistry([$provider]))->quote('test', new ShipmentRequest(self::shipment()), ['currency' => 'EUR'])->status);
        self::assertSame('unsupported_rating', (new ProviderRegistry([]))->quote('missing', new ShipmentRequest(self::shipment()), [])->status);
    }

    public function testCustomerCannotSupplySellingPrices(): void
    {
        $data = self::shipment();
        $data['totalMinor'] = 1;
        $this->expectExceptionMessage('shipment_fields');
        new ShipmentRequest($data);
    }

    public function testProviderExceptionIsAnExplicitPartialFailure(): void
    {
        $provider = new class implements CarrierRateProvider {
            public function code(): string
            {
                return 'broken';
            }

            public function quote(ShipmentRequest $request, array $tariff): RateResult
            {
                throw new \RuntimeException('Untrusted provider error');
            }
        };
        self::assertSame('temporary_failure', (new ProviderRegistry([$provider]))->quote('broken', new ShipmentRequest(self::shipment()), [])->status);
    }
}
