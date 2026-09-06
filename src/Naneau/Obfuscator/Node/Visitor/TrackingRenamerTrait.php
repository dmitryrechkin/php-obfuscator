<?php
/**
 * SkipTrait.php
 *
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */

namespace Naneau\Obfuscator\Node\Visitor;

/**
 * SkipTrait
 *
 * Renaming trait, for renaming things that require tracking
 *
 * @category        Naneau
 * @package         Obfuscator
 * @subpackage      NodeVisitor
 */
trait TrackingRenamerTrait
{
    /**
     * Renamed variables
     *
     * @var string[]
     **/
    private $renamed = array();

    /**
     * Record renaming of method
     *
     * @param  string    $method
     * @param  string    $newName
     * @return SkipTrait
     **/
    protected function renamed($method, $newName)
    {
        // php-parser 5 passes Identifier objects; key the map by the name.
        $this->renamed[(string) $method] = (string) $newName;

        return $this;
    }

    /**
     * Has a method been renamed?
     *
     * @param  string $method
     * @return bool
     **/
    protected function isRenamed($method)
    {
        if (empty($method)) {
            return false;
        }

        // php-parser 5 passes Identifier objects here; a Variable-typed
        // method name (variable method call) has no scalar name and is skipped.
        if (is_object($method) && !method_exists($method, '__toString')) {
            return false;
        }
        $method = (string) $method;
        if ($method === '') {
            return false;
        }

        return isset($this->renamed[$method]);
    }

    /**
     * Get new name of a method
     *
     * @param  string $method
     * @return string
     **/
    protected function getNewName($method)
    {
        if (!$this->isRenamed($method)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" was not renamed',
                (string) $method
            ));
        }

        return $this->renamed[$method];
    }

    /**
     * Reset renamed list
     *
     * @return SkipTrait
     **/
    protected function resetRenamed()
    {
        $this->renamed = array();

        return $this;
    }
}
