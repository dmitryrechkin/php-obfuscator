<?php
/**
 * ScramblePrivateProperty.php
 *
 * @category        Naneau
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */

namespace Naneau\Obfuscator\Node\Visitor;

use Naneau\Obfuscator\Node\Visitor\TrackingRenamerTrait;
use Naneau\Obfuscator\Node\Visitor\SkipTrait;

use Naneau\Obfuscator\Node\Visitor\Scrambler as ScramblerVisitor;
use Naneau\Obfuscator\StringScrambler;

use PhpParser\Node;

use PhpParser\Node\Stmt\Class_ as ClassNode;
use PhpParser\Node\Stmt\Property;

use PhpParser\Node\Expr\PropertyFetch;

use PhpParser\Node\Expr\Variable;
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Trait_ as TraitNode;

/**
 * ScramblePrivateProperty
 *
 * Renames private properties
 *
 * WARNING
 *
 * See warning for private method scrambler
 *
 * @category        Naneau
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */
class ScramblePrivateProperty extends ScramblerVisitor
{
    use TrackingRenamerTrait;
    use SkipTrait;

    /**
     * Constructor
     *
     * @param  StringScrambler $scrambler
     * @return void
     **/
    public function __construct(StringScrambler $scrambler)
    {
        parent::__construct($scrambler);
    }

    /**
     * Before node traversal
     *
     * @param  Node[] $nodes
     * @return array
     **/
    public function beforeTraverse(array $nodes)
    {
        $this
            ->resetRenamed()
            ->scanPropertyDefinitions($nodes);

        return $nodes;
    }

    /**
     * Check all variable nodes
     *
     * @param  Node $node
     * @return void
     **/
    public function enterNode(Node $node)
    {
        if ($node instanceof PropertyFetch) {
            // A private property is only ever accessed as $this->prop from
            // inside the declaring class; $this->other->prop reaches a
            // different object whose same-named property may be public, so it
            // must not be renamed. (In php-parser 5 the name is an Identifier,
            // not a string -- the old is_string() guard silently skipped every
            // fetch.)
            if (!($node->var instanceof Variable) || $node->var->name !== 'this') {
                return;
            }

            if (!($node->name instanceof Identifier)) {
                return;
            }

            if ($this->isRenamed((string) $node->name)) {
                $node->name = new Identifier($this->getNewName((string) $node->name));
                return $node;
            }
        }
    }

    /**
     * Recursively scan for private method definitions and rename them
     *
     * @param  Node[] $nodes
     * @return void
     **/
    private function scanPropertyDefinitions(array $nodes)
    {
        foreach ($nodes as $node) {
            // Scramble the private method definitions
            if ($node instanceof Property && ($node->flags & Modifiers::PRIVATE)) {
                foreach($node->props as $property) {

                    // Record original name and scramble it
                    $originalName = $property->name;
                    $this->scramble($property);

                    // Record renaming
                    $this->renamed($originalName, $property->name);
                }

            }

            // Do NOT descend into a trait. A private member declared in a
            // trait is reached from the USING class (a different file) as
            // $this->member; per-file obfuscation cannot rewrite that call, so
            // renaming the trait's declaration would leave a dangling call.
            // Traits are therefore left readable -- correctness over coverage.
            if ($node instanceof TraitNode) {
                continue;
            }

            // Recurse over child nodes
            if (isset($node->stmts) && is_array($node->stmts)) {
                $this->scanPropertyDefinitions($node->stmts);
            }
        }
    }
}
