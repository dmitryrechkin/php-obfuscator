<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

final class User
{
    use Helper;

    public function run(int $n): int
    {
        // calls the trait's PRIVATE method from the USING class (different file)
        return $this->secretHelper($n);
    }
}
