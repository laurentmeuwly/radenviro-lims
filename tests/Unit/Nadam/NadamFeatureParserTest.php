<?php

namespace Tests\Unit\Nadam;

use App\Services\Nadam\InvalidNadamFeature;
use App\Services\Nadam\NadamFeatureParser;
use PHPUnit\Framework\TestCase;

class NadamFeatureParserTest extends TestCase
{
    private function parser(): NadamFeatureParser
    {
        return new NadamFeatureParser([
            'network_name' => 'NADAM',
            'value_unit' => 'nSv/h',
            'average_types' => ['Stündlich' => 'hourly'],
        ]);
    }

    /** Feature réelle du fichier d'exemple NAZ, surchargeable. */
    private function feature(array $override = []): array
    {
        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [7.877861, 47.572158]],
            'properties' => array_merge([
                'odl_measurement_point_id' => 78,
                'odl_station_id' => 768,
                'odl_probe_id' => null,
                'odl_probe_kind_id' => 1,
                'odl_team_id' => null,
                'odl_network_id' => 2,
                'odl_station_name' => 'Möhlin, SMN',
                'odl_station_code' => 'MOE',
                'odl_probe_name' => null,
                'odl_probe_kind_name' => 'IGS421',
                'odl_team_name' => null,
                'odl_network_name' => 'NADAM',
                'average_type' => 'Stündlich',
                'average_date' => '2026-09-07T12:00:00.000+02:00',
                'value' => '122.5',
                'value_unit' => 'nSv/h',
                'latitude' => '47.572158',
                'longitude' => '7.877861',
                'height_above_sea' => 344,
                'precipitation' => 0,
            ], $override),
        ];
    }

    public function testNominalFeature()
    {
        $r = $this->parser()->parse($this->feature());

        $this->assertSame(768, $r->stationExternalId);
        $this->assertSame('MOE', $r->stationCode);
        $this->assertSame('hourly', $r->averageType);
        $this->assertSame('2026-09-07 10:00:00', $r->measuredAtUtc); // +02:00 → UTC
        $this->assertSame('122.5', $r->value);
        $this->assertSame('0', $r->precipitation);
        $this->assertSame([], $r->warnings);
    }

    public function testWinterTimeOffset()
    {
        $r = $this->parser()->parse($this->feature(['average_date' => '2026-12-01T12:00:00.000+01:00']));
        $this->assertSame('2026-12-01 11:00:00', $r->measuredAtUtc);
    }

    /** Nuit du 25.10.2026 : 02:00 locale existe deux fois, l'offset les distingue. */
    public function testOctoberDstAmbiguousHourGivesTwoDistinctInstants()
    {
        $summer = $this->parser()->parse($this->feature(['average_date' => '2026-10-25T02:00:00.000+02:00']));
        $winter = $this->parser()->parse($this->feature(['average_date' => '2026-10-25T02:00:00.000+01:00']));

        $this->assertSame('2026-10-25 00:00:00', $summer->measuredAtUtc);
        $this->assertSame('2026-10-25 01:00:00', $winter->measuredAtUtc);
        $this->assertNotSame($summer->key(), $winter->key());
    }

    public function testDateWithoutMillisecondsIsAccepted()
    {
        $r = $this->parser()->parse($this->feature(['average_date' => '2026-09-07T12:00:00+02:00']));
        $this->assertSame('2026-09-07 10:00:00', $r->measuredAtUtc);
    }

    public function testDateWithoutOffsetIsRejected()
    {
        $this->expectException(InvalidNadamFeature::class);
        $this->parser()->parse($this->feature(['average_date' => '2026-09-07T12:00:00.000']));
    }

    public function testPrecipitationNullStaysNull()
    {
        $r = $this->parser()->parse($this->feature(['precipitation' => null]));
        $this->assertNull($r->precipitation);
    }

    public function testInvalidPrecipitationKeepsDoseValue()
    {
        $r = $this->parser()->parse($this->feature(['precipitation' => 'n/a']));
        $this->assertNull($r->precipitation);
        $this->assertSame('122.5', $r->value);
        $this->assertCount(1, $r->warnings);
    }

    public function testNumericValueIsNormalized()
    {
        $this->assertSame('96.333', $this->parser()->parse($this->feature(['value' => 96.333]))->value);
        $this->assertSame('100', $this->parser()->parse($this->feature(['value' => 100]))->value);
    }

    /**
     * @dataProvider invalidFeatures
     */
    public function testInvalidFeaturesAreRejected(array $override)
    {
        $this->expectException(InvalidNadamFeature::class);
        $this->parser()->parse($this->feature($override));
    }

    public function invalidFeatures(): array
    {
        return [
            'valeur absente' => [['value' => null]],
            'valeur négative' => [['value' => '-1.0']],
            'valeur texte' => [['value' => 'abc']],
            'unité µSv/h' => [['value_unit' => 'µSv/h']],
            'autre réseau' => [['odl_network_name' => 'MADUK']],
            'moyenne journalière' => [['average_type' => 'Täglich']],
            'sans station id' => [['odl_station_id' => null]],
            'sans code' => [['odl_station_code' => '']],
        ];
    }

    public function testFeatureWithoutPropertiesIsRejected()
    {
        $this->expectException(InvalidNadamFeature::class);
        $this->parser()->parse(['type' => 'Feature']);
    }
}
