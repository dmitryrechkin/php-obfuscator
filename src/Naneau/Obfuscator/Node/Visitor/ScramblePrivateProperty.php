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
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;

use PhpParser\Node\Expr\Variable;
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\VarLikeIdentifier;
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
     * Names of private properties that must NOT be renamed because the file
     * reads or writes a property of that name on a bare local variable other
     * than $this ($clone->prop = ..., $other->prop). PHP visibility is
     * class-level, so a SIBLING instance legally reaches a private property
     * that way -- but the receiver rule in enterNode() leaves that fetch
     * readable. Renaming only the declaration then silently splits the object:
     * $clone->logger writes a brand-new dynamic property while every
     * $this->sp...() read keeps seeing the pre-clone value (and PHP 8.2+ warns
     * about the dynamic property). Same rule and reason as the private-method
     * scrambler's sibling case: correctness over coverage.
     *
     * @var array<string,bool>
     **/
    private $unsafeNames = [];

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
        $this->resetRenamed();

        $this->unsafeNames = [];
        $this->collectUnsafeNames($nodes);

        $this->scanPropertyDefinitions($nodes);

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

        // A private STATIC property is reached as self::$prop / static::$prop.
        // Without this arm the declaration is renamed and every fetch is left
        // behind -- "Access to undeclared static property" on the first call,
        // which is how PublicUrlHelper::configure() took a live site down.
        // Any other class name (Other::$prop, parent::$prop) targets a
        // different class and is left alone, as with $foo->prop above.
        if ($node instanceof StaticPropertyFetch) {
            if (!($node->class instanceof Name)
                || !in_array($node->class->toLowerString(), ['self', 'static'], true)
            ) {
                return;
            }

            if (!($node->name instanceof VarLikeIdentifier)) {
                return;
            }

            if ($this->isRenamed((string) $node->name)) {
                $node->name = new VarLikeIdentifier($this->getNewName((string) $node->name));
                return $node;
            }
        }
    }

    /**
     * Recursively record the names of properties fetched on a bare local
     * variable other than $this ($clone->prop, $other->prop): the shape
     * through which a sibling instance of the SAME class reaches a private
     * property. See $unsafeNames.
     *
     * @param  Node[] $nodes
     * @return void
     **/
    private function collectUnsafeNames(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof PropertyFetch
                && $node->name instanceof Identifier
                && $node->var instanceof Variable
                && $node->var->name !== 'this'
            ) {
                $this->unsafeNames[$node->name->toString()] = true;
            }

            // A string literal equal to a property name may be a dynamic
            // $this->{$name} / property_exists() reference: keep it readable.
            if ($node instanceof Node\Scalar\String_) {
                $this->unsafeNames[$node->value] = true;
            }

            foreach ($node->getSubNodeNames() as $subName) {
                $child = $node->$subName ?? null;
                if ($child instanceof Node) {
                    $this->collectUnsafeNames([$child]);
                } elseif (is_array($child)) {
                    $this->collectUnsafeNames(array_filter(
                        $child,
                        static function ($candidate) {
                            return $candidate instanceof Node;
                        }
                    ));
                }
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

                    // Reached through a sibling instance: leave readable.
                    if (isset($this->unsafeNames[(string) $property->name])) {
                        continue;
                    }

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
