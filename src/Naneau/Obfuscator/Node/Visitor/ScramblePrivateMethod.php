<?php
/**
 * ScramblePrivateMethod.php
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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Modifiers;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Trait_ as TraitNode;

/**
 * ScramblePrivateMethod
 *
 * Renames private methods
 *
 * WARNING
 *
 * This method is not foolproof. This visitor scans for all private method
 * declarations and renames them. It then finds *all* method calls in the
 * class, and renames them if they match the name of a renamed method. If your
 * class calls a method of *another* class that happens to match one of the
 * renamed private methods, this visitor will rename it.
 *
 * @category        Naneau
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */
class ScramblePrivateMethod extends ScramblerVisitor
{
    use TrackingRenamerTrait;
    use SkipTrait;

    /**
     * Lower-cased names of private methods that must NOT be renamed because the
     * file invokes a method of that name on a bare local variable other than
     * $this ($clone->m(), $other->m()) or via parent::. PHP visibility is
     * class-level, not instance-level, so $other->m() where $other is a SIBLING
     * instance of the declaring class legally reaches its private m() -- yet the
     * receiver rule in enterNode() leaves that call readable. Renaming only the
     * declaration would then fatal at runtime with "Call to undefined method
     * C::sp...()" (the AbilityDefinition::withLogger() clone-and-rebuild break
     * that took a live store down). Having no type information, we cannot prove
     * the variable is-a the declaring class, so such names are kept readable:
     * correctness over coverage, exactly as with trait-declared members.
     *
     * @var array<string,bool>
     **/
    private $unsafeNames = [];

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
            ->skip($this->variableMethodCallsUsed($nodes));

        $this->unsafeNames = [];
        $this->collectUnsafeNames($nodes);

        $this->scanMethodDefinitions($nodes);

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
        if ($this->shouldSkip()) {
            return;
        }

        // Scramble calls -- but only when the receiver is unambiguously the
        // class itself. A private method can only be invoked on $this, self::
        // or static:: from inside the declaring class, so those are the only
        // call shapes that can target the method we renamed. A call on any
        // other receiver ($this->other->name(), $foo->name(), Bar::name())
        // reaches a DIFFERENT object -- whose same-named method may be public
        // contract -- and renaming it would break the call. The obfuscator has
        // no type information, so the syntactic receiver is the only safe
        // signal: when it is not $this / self / static, leave the call alone.
        if ($node instanceof MethodCall || $node instanceof StaticCall) {

            // Node wasn't renamed
            if (!$this->isRenamed($node->name)) {
                return;
            }

            if (!$this->receiverIsSameClass($node)) {
                return;
            }

            // Scramble usage
            return $this->scramble($node);
        }
    }

    /**
     * Recursively scan for method calls and see if variables are used
     *
     * @param  Node[] $nodes
     * @return void
     **/
    private function variableMethodCallsUsed(array $nodes)
    {
        foreach ($nodes as $node) {
            if ($node instanceof MethodCall && $node->name instanceof Variable) {
                // A method call uses a Variable as its name
                return true;
            }

            // Recurse over child nodes
            if (isset($node->stmts) && is_array($node->stmts)) {
                $used = $this->variableMethodCallsUsed($node->stmts);

                if ($used) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recursively scan for private method definitions and rename them
     *
     * @param  Node[] $nodes
     * @return void
     **/

    /**
     * Whether a call's receiver is unambiguously the declaring class, i.e. the
     * only shapes through which a private method can be reached: $this->m(),
     * self::m(), static::m(). Anything else targets a different object and must
     * not be renamed.
     *
     * @param  MethodCall|StaticCall $node
     * @return bool
     **/
    /**
     * Recursively record the lower-cased names of methods invoked on a bare
     * local variable other than $this ($clone->m(), $other->m()) or via
     * parent::. These are the shapes through which a sibling instance of the
     * SAME class can reach a private method; the syntactic receiver rule cannot
     * tell them from a collaborator call, so the matching private declaration is
     * left readable (see $unsafeNames) rather than renamed into a dangling call.
     *
     * A $this->prop->m() collaborator call is deliberately NOT collected: its
     * receiver is a property, which the receiver rule already leaves alone, and
     * the same-named private declaration stays safely scrambleable.
     *
     * @param  Node[] $nodes
     * @return void
     **/
    private function collectUnsafeNames(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof MethodCall
                && $node->name instanceof Node\Identifier
                && $node->var instanceof Variable
                && $node->var->name !== 'this'
            ) {
                $this->unsafeNames[strtolower($node->name->toString())] = true;
            }

            if ($node instanceof StaticCall
                && $node->name instanceof Node\Identifier
                && $node->class instanceof Name
                && $node->class->toLowerString() === 'parent'
            ) {
                $this->unsafeNames[strtolower($node->name->toString())] = true;
            }

            // A string literal equal to a member name is a callable / reflection
            // reference the renamer cannot follow: [$this, 'formatItem'],
            // method_exists($this, 'x'), call_user_func(...). Keep such names.
            if ($node instanceof Node\Scalar\String_) {
                $this->unsafeNames[strtolower($node->value)] = true;
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

    private function receiverIsSameClass(Node $node): bool
    {
        if ($node instanceof MethodCall) {
            return $node->var instanceof Variable && $node->var->name === 'this';
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            return in_array($node->class->toLowerString(), ['self', 'static'], true);
        }

        return false;
    }

    private function scanMethodDefinitions(array $nodes)
    {
        foreach ($nodes as $node) {
            // Scramble the private method definitions
            if ($node instanceof ClassMethod && ($node->flags & Modifiers::PRIVATE)) {

                // Leave readable any private method reached through a sibling
                // instance of the same class -- renaming only the declaration
                // would dangle the un-renamed $other->m() call site.
                if (isset($this->unsafeNames[strtolower((string) $node->name)])) {
                    if (isset($node->stmts) && is_array($node->stmts)) {
                        $this->scanMethodDefinitions($node->stmts);
                    }
                    continue;
                }

                // Record original name and scramble it
                $originalName = $node->name;
                $this->scramble($node);

                // Record renaming
                $this->renamed($originalName, $node->name);
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
                $this->scanMethodDefinitions($node->stmts);
            }
        }
    }
}
