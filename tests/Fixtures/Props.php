<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

final class Other
{
    public int $count = 7;   // PUBLIC property, same name as A's private
}

final class A
{
    private int $count = 0;  // PRIVATE — scramble
    private Other $other;
    public function __construct() { $this->other = new Other(); }
    public function run(): string
    {
        $this->count = 3;                     // $this-> private, scramble
        return $this->count . ':' . $this->other->count;  // $this->other->count is PUBLIC, keep
    }
}
