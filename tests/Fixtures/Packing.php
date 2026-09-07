<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

interface BoxSink
{
    public function setBoxes(array $boxes): void; // PUBLIC contract — never rename
}

final class BulkPacker implements BoxSink
{
    public array $received = [];
    public function setBoxes(array $boxes): void
    {
        $this->received = $boxes;
    }
}

final class Packer
{
    private BulkPacker $inner;
    public function __construct()
    {
        $this->inner = new BulkPacker();
    }

    public function apply(array $settings): void
    {
        if (isset($settings['boxes'])) {
            $this->setBoxes($settings['boxes']);   // $this-> : PRIVATE, safe to scramble
        }
    }

    // PRIVATE, same name as the interface method on a DIFFERENT class
    private function setBoxes(array $boxes): void
    {
        $this->inner->setBoxes($boxes);            // $this->inner-> : PUBLIC on BulkPacker, must NOT scramble
    }

    public function result(): array
    {
        return $this->inner->received;
    }
}
