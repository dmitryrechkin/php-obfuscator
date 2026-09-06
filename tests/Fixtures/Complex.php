<?php

declare(strict_types=1);

namespace OneTeamSoftware\Test;

interface Greeter
{
    public function greet(string $who): string;
}

trait CounterTrait
{
    private int $count = 0;

    protected function bump(): int
    {
        return ++$this->count;
    }
}

abstract class Base implements Greeter
{
    public const PREFIX = 'base';

    abstract public function greet(string $who): string;

    protected function decorate(string $s): string
    {
        return static::PREFIX . ':' . $s;
    }
}

final class Complex extends Base
{
    use CounterTrait;

    public const PREFIX = 'complex';

    private array $log = [];

    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    // PUBLIC contract method — name must survive
    public function greet(string $who): string
    {
        return $this->decorate($this->buildGreeting($who));
    }

    // PRIVATE — name must be scrambled, callers updated
    private function buildGreeting(string $who): string
    {
        $this->record('greet:' . $who);
        return 'hello ' . $who . ' #' . $this->bump();
    }

    // Registered as a WordPress-style callback BY STRING from inside the class.
    // If this private method is renamed but the string is not, this breaks.
    public function register(array &$hooks): void
    {
        // Callback kept as a closure over a private method: the private name
        // never leaks into a string, so it is safe to scramble.
        $hooks['init'] = function () {
            return $this->onInit();
        };
        $hooks['dynamic'] = 'onDynamic';
    }

    private function onInit(): string
    {
        return 'init:' . $this->id;
    }

    // Reached by NAME through call(): the string 'onDynamic' is data, so a
    // rename of this private method WOULD break method_exists. This is the
    // case the obfuscator must either skip or the code must avoid.
    public function onDynamic(): string
    {
        return 'dynamic:' . $this->id;
    }

    // Dynamic dispatch — method name computed at runtime.
    public function call(string $name): string
    {
        if (method_exists($this, $name)) {
            return $this->{$name}();
        }
        return 'missing';
    }

    private function record(string $line): void
    {
        $this->log[] = $line;
    }

    public function history(): array
    {
        return $this->log;
    }

    // Closure capturing $this and a local.
    public function makeAdder(int $base): \Closure
    {
        return function (int $x) use ($base): int {
            return $base + $x + $this->bump();
        };
    }
}
