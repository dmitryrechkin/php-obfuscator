<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

final class Registry
{
    private static ?string $configuredDefault = null;  // PRIVATE static — scramble declaration AND self::/static:: fetches
    private int $count = 0;

    public static function configure(string $value): void
    {
        self::$configuredDefault = $value;
    }

    public static function value(): string
    {
        return static::$configuredDefault ?? 'unset';
    }

    public function bump(): int
    {
        return ++$this->count;
    }
}
