<?php
/**
 * This file is part of Aegis\JSON, a simple library to handle JSON data
 */

namespace Aegis\JSON;

/**
 * Class for Aegis\JSON\Json encode method.
 *
 * This class simply holds a string with a native Javascript Expression,
 * so objects | arrays to be encoded with Aegis\JSON\Json can contain native
 * Javascript Expressions.
 *
 * Example:
 * <code>
 * $foo = array(
 *     'integer'  => 9,
 *     'string'   => 'test string',
 *     'function' => Aegis\JSON\Expr(
 *         'function () { window.alert("javascript function encoded by Aegis\JSON\Json") }'
 *     ),
 * );
 *
 * Aegis\JSON\JSON::encode($foo, false, array('enableJsonExprFinder' => true));
 * // it will returns json encoded string:
 * // {"integer":9,"string":"test string","function":function () {window.alert("javascript function encoded by Aegis\JSON\Json")}}
 * </code>
 */
class Expr
{
    /**
     * Storage for javascript expression.
     *
     * @var string
     */
    protected $expression;

    /**
     * Constructor
     *
     * @param  string $expression the expression to hold.
     */
    public function __construct($expression)
    {
        $this->expression = (string) $expression;
    }

    /**
     * Cast to string
     *
     * @return string holded javascript expression.
     */
    public function __toString()
    {
        return $this->expression;
    }
}
