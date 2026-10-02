<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminPulse\Tests\Feature;

use Dskripchenko\LaravelAdminPulse\Resources\PulseSampleResource;
use Dskripchenko\LaravelAdminPulse\Tests\TestCase;

/**
 * The samples table reads in the panel's language: its headers used to be
 * made from the column names and the kinds were English captions.
 */
final class CaptionsLocaleTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function columns(): array
    {
        $out = [];
        foreach ((new PulseSampleResource)->columns() as $column) {
            $arr = $column->toArray();
            $out[(string) $arr['name']] = $arr;
        }

        return $out;
    }

    public function test_russian_panel(): void
    {
        app()->setLocale('ru');

        $columns = $this->columns();
        foreach ($columns as $name => $column) {
            $this->assertMatchesRegularExpression('/\p{Cyrillic}|^ID$/u', (string) $column['label'], $name);
        }
        $this->assertSame('HTTP-запрос', $columns['kind']['meta']['labels']['request']);
    }

    public function test_english_panel(): void
    {
        app()->setLocale('en');

        $columns = $this->columns();
        $this->assertSame('Time', $columns['sampled_at']['label']);
        $this->assertSame('Request', $columns['kind']['meta']['labels']['request']);
    }
}
