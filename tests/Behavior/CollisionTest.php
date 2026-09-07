<?php

declare(strict_types=1);

namespace Naneau\Obfuscator\Tests\Behavior;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * A private member may share its name with a public member of a DIFFERENT
 * class that is reached through the same file -- e.g. a private Packer::setBoxes
 * next to $this->inner->setBoxes() where the interface method setBoxes() is
 * public. The obfuscator has no type information, so it must key on the only
 * syntactic signal that a call/fetch targets the declaring class itself: a
 * $this receiver. Anything else is left readable.
 *
 * These fixtures are minimised from a real shipped break -- a Packer/BulkPacker
 * setBoxes collision that fataled on activation with
 * "Call to undefined method BulkPacker::sp...()".
 */
final class CollisionTest extends TestCase
{
    /** @dataProvider fixtures */
    public function testCollidingNamesDoNotBreakBehaviour(string $fixture, string $expected): void
    {
        $work = sys_get_temp_dir() . '/obf-collide-' . bin2hex(random_bytes(4));
        mkdir($work . '/src', 0777, true);
        copy(__DIR__ . '/../Fixtures/' . $fixture, $work . '/src/' . $fixture);

        (new Process([
            PHP_BINARY, __DIR__ . '/../../bin/obfuscate', 'obfuscate',
            $work . '/src', $work . '/out',
            '--config=' . __DIR__ . '/config/private-only.yml',
        ]))->mustRun();

        $out = new Process([PHP_BINARY, __DIR__ . '/drivers/' . str_replace('.php', '.driver.php', $fixture), $work . '/out/' . $fixture]);
        $out->mustRun();

        self::assertSame($expected, trim($out->getOutput()));

        $this->rmdir($work);
    }

    public static function fixtures(): array
    {
        return [
            'private method colliding with a public interface method' => ['Packing.php', 'A,B'],
            'private property colliding with a public property' => ['Props.php', '3:7'],
        ];
    }

    /**
     * A private method declared in a trait is called from the using class in a
     * different file. Per-file obfuscation cannot rewrite that cross-file call,
     * so the trait's private members must be left readable rather than renamed
     * into a dangling call.
     */
    public function testTraitPrivateMethodCalledFromUsingClassSurvives(): void
    {
        $work = sys_get_temp_dir() . '/obf-trait-' . bin2hex(random_bytes(4));
        mkdir($work . '/src', 0777, true);
        copy(__DIR__ . '/../Fixtures/TraitHelper.php', $work . '/src/TraitHelper.php');
        copy(__DIR__ . '/../Fixtures/TraitUser.php', $work . '/src/TraitUser.php');

        (new Process([
            PHP_BINARY, __DIR__ . '/../../bin/obfuscate', 'obfuscate',
            $work . '/src', $work . '/out',
            '--config=' . __DIR__ . '/config/private-only.yml',
        ]))->mustRun();

        $out = new Process([PHP_BINARY, __DIR__ . '/drivers/TraitUser.driver.php', $work . '/out/TraitUser.php']);
        $out->mustRun();

        self::assertSame('50', trim($out->getOutput()));

        $this->rmdir($work);
    }

    private function rmdir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir((string) $f) : unlink((string) $f);
        }
        rmdir($dir);
    }
}
