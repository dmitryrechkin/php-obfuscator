<?php
/**
 * ScrambleVariable.php
 *
 * @category        Naneau
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */

namespace Naneau\Obfuscator\Node\Visitor;

use Naneau\Obfuscator\Node\Visitor\Scrambler as ScramblerVisitor;
use Naneau\Obfuscator\StringScrambler;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Param;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Catch_ as CatchStatement;
use PhpParser\Node\Expr\ClosureUse;

use \InvalidArgumentException;

/**
 * ScrambleVariable
 *
 * Renames parameters
 *
 * @category        Naneau
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */
class ScrambleVariable extends ScramblerVisitor
{
    /**
     * Constructor
     *
     * @param  StringScrambler $scrambler
     * @return void
     **/
    public function __construct(StringScrambler $scrambler)
    {
        parent::__construct($scrambler);

        $this->setIgnore(array(
            'this', '_SERVER', '_POST', '_GET', '_REQUEST', '_COOKIE',
            '_SESSION', '_ENV', '_FILES'
        ));
    }

    /**
     * Check all variable nodes
     *
     * @param  Node $node
     * @return void
     **/
    public function enterNode(Node $node)
    {
        // Variable use. In php-parser 5 a function parameter's name and a
        // `static $x` name both live on an inner Variable node ($node->var),
        // which the traverser visits on its own -- so Param and StaticVar need
        // no handling here (StaticVar has no ->name; reading it warned).
        if ($node instanceof Variable) {
            return $this->scramble($node);
        }

        // try {} catch () {}
        if ($node instanceof CatchStatement) {
            return $this->scramble($node, 'var');
        }

        // Function() use ($x, $y) {}
        if ($node instanceof ClosureUse) {
            return $this->scramble($node, 'var');
        }
    }
}
