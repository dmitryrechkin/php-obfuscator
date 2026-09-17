<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

final class Lister
{
    private string $prefix = 'p';

    public function run(array $items): string
    {
        // private method reached ONLY through a string callable
        return implode(',', array_map([$this, 'formatItem'], $items)) . ':' . (method_exists($this, 'hasFlag') ? 'has' : 'no');
    }

    private function formatItem(string $item): string
    {
        return $this->prefix . $item;
    }

    private function hasFlag(): bool
    {
        return true;
    }
}
