<?php

declare(strict_types=1);

namespace Naneau\Obfuscator\Tests\Behavior;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The obfuscator must preserve behaviour and the package public contract.
 *
 * These are not cosmetic checks. The fork was pinned to nikic/php-parser 5 but
 * its visitors were written for 4, so every private-method and private-property
 * rename silently no-opped in production -- the exact reason a customer could
 * quote OrderUtils::hasVendorIntegrationSettings() by name from a shipped build.
 * A no-op obfuscator emits warnings to stderr and exits 0, so only a
 * behaviour-and-contract assertion catches it. That is what this does.
 *
 * Method: obfuscate the fixture, run the SAME driver against source and against
 * the obfuscated output, and require identical output. Then assert the contract
 * directly -- public names survive, private names do not.
 */
final class ObfuscationBehaviorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/obf-behavior-' . bin2hex(random_bytes(4));
        mkdir($this->workDir . '/src', 0777, true);
        copy(__DIR__ . '/../Fixtures/Complex.php', $this->workDir . '/src/Complex.php');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);
    }

    public function testObfuscatedCodeBehavesIdenticallyToSource(): void
    {
        $sourceOutput = $this->runDriver($this->workDir . '/src/Complex.php');

        $this->obfuscate($this->workDir . '/src', $this->workDir . '/out');
        $obfuscatedFile = $this->workDir . '/out/Complex.php';
        self::assertFileExists($obfuscatedFile, 'Obfuscation produced no output file.');

        $obfuscatedOutput = $this->runDriver($obfuscatedFile);

        self::assertSame(
            $sourceOutput,
            $obfuscatedOutput,
            'Obfuscated code did not behave identically to the source.'
        );
    }

    public function testPublicContractIsPreservedAndPrivatesAreScrambled(): void
    {
        $this->obfuscate($this->workDir . '/src', $this->workDir . '/out');
        $code = file_get_contents($this->workDir . '/out/Complex.php');

        // Public surface -- what a sibling package or WordPress references.
        foreach (['namespace OneTeamSoftware', 'function greet', 'function onDynamic', 'function register'] as $keep) {
            self::assertStringContainsString($keep, $code, "Public contract lost: {$keep}");
        }

        // Private implementation -- names must be gone.
        foreach (['function buildGreeting', 'function onInit', 'function record'] as $hidden) {
            self::assertStringNotContainsString($hidden, $code, "Private name leaked: {$hidden}");
        }
    }

    public function testObfuscationEmitsNoWarnings(): void
    {
        $process = $this->obfuscateProcess($this->workDir . '/src', $this->workDir . '/out');
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsStringIgnoringCase('warning', $process->getErrorOutput());
        self::assertStringNotContainsStringIgnoringCase('deprecated', $process->getErrorOutput());
    }

    private function obfuscate(string $in, string $out): void
    {
        $process = $this->obfuscateProcess($in, $out);
        $process->mustRun();
    }

    private function obfuscateProcess(string $in, string $out): Process
    {
        return new Process([
            PHP_BINARY,
            __DIR__ . '/../../bin/obfuscate',
            'obfuscate',
            $in,
            $out,
            '--config=' . __DIR__ . '/config/private-only.yml',
        ]);
    }

    private function runDriver(string $classFile): string
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/driver.php', $classFile]);
        $process->mustRun();

        return $process->getOutput();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir((string) $item) : unlink((string) $item);
        }
        rmdir($dir);
    }
}
