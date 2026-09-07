<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

trait Helper
{
    // PRIVATE method declared in the trait, in ITS OWN file
    private function secretHelper(int $x): int
    {
        return $x * 10;
    }
}
