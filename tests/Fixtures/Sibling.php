<?php
declare(strict_types=1);
namespace OneTeamSoftware\Test;

/**
 * The sibling-instance case. PHP visibility is class-level, not instance-level:
 * an object may call a PRIVATE method of ANOTHER instance of its own class.
 * Minimised from the real shipped break -- AbilityDefinition::withLogger() does
 * $clone = clone $this; $clone->buildExecuteCallable(); -- which fataled on
 * activation with "Call to undefined method AbilityDefinition::sp...()".
 */
final class Definition
{
    private string $label;
    /** @var callable */
    private $execute;

    public function __construct(string $label)
    {
        $this->label = $label;
        $this->execute = $this->buildExecuteCallable();      // $this-> : same instance
    }

    public function withSuffix(string $suffix): self
    {
        $clone = clone $this;
        $clone->label = $this->label . $suffix;
        $clone->execute = $clone->buildExecuteCallable();    // $clone-> : SIBLING instance, same class
        return $clone;
    }

    // PRIVATE, reached through both $this-> and a sibling $clone->
    private function buildExecuteCallable(): callable
    {
        $label = $this->label;
        return static function () use ($label): string {
            return 'run:' . $label;
        };
    }

    public function run(): string
    {
        return ($this->execute)();
    }
}
