<?php
/**
 * This file is part of Aegis\JSON, a simple library to handle JSON data
 */

namespace Aegis\JSON;

use SimpleXMLElement;
use Aegis\JSON\Exception\RecursionException;
use Aegis\JSON\Exception\RuntimeException;

/**
 * Class for encoding to and decoding from JSON.
 */
class JSON
{
    /**
     * How objects should be encoded -- arrays or as stdClass. TYPE_ARRAY is 1
     * so that it is a boolean true value, allowing it to be used with
     * ext/json's functions.
     */
    const TYPE_ARRAY  = 1;
    const TYPE_OBJECT = 0;

    /**
     * @var bool
     */
    public static $useBuiltinEncoderDecoder = false;

    /**
     * Decodes the given $encodedValue string which is
     * encoded in the JSON format
     *
     * Uses ext/json's json_decode if available.
     *
     * @param string $encodedValue Encoded in JSON format
     * @param int $objectDecodeType Optional; flag indicating how to decode
     * objects. See {@link Aegis\JSON\Decoder::decode()} for details.
     * @return mixed
     * @throws RuntimeException
     */
    public static function decode($encodedValue, $objectDecodeType = self::TYPE_OBJECT)
    {
        $encodedValue = (string) $encodedValue;
        if (function_exists('json_decode') && static::$useBuiltinEncoderDecoder !== true) {
            $decode = json_decode($encodedValue, $objectDecodeType);

            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    break;
                case JSON_ERROR_DEPTH:
                    throw new RuntimeException('Decoding failed: Maximum stack depth exceeded');
                case JSON_ERROR_CTRL_CHAR:
                    throw new RuntimeException('Decoding failed: Unexpected control character found');
                case JSON_ERROR_SYNTAX:
                    throw new RuntimeException('Decoding failed: Syntax error');
                default:
                    throw new RuntimeException('Decoding failed');
            }

            return $decode;
        }

        return Decoder::decode($encodedValue, $objectDecodeType);
    }

    /**
     * Encode the mixed $valueToEncode into the JSON format
     *
     * Encodes using ext/json's json_encode() if available.
     *
     * NOTE: Object should not contain cycles; the JSON format
     * does not allow object reference.
     *
     * NOTE: Only public variables will be encoded
     *
     * NOTE: Encoding native javascript expressions are possible using Aegis\JSON\Expr.
     *       You can enable this by setting $options['enableJsonExprFinder'] = true
     *
     * @see Aegis\JSON\Expr
     *
     * @param  mixed $valueToEncode
     * @param  bool $cycleCheck Optional; whether or not to check for object recursion; off by default
     * @param  array $options Additional options used during encoding
     * @return string JSON encoded object
     */
    public static function encode($valueToEncode, $cycleCheck = false, $options = array())
    {
        if (is_object($valueToEncode)) {
            if (method_exists($valueToEncode, 'toJson')) {
                return $valueToEncode->toJson();
            } elseif (method_exists($valueToEncode, 'toArray')) {
                return static::encode($valueToEncode->toArray(), $cycleCheck, $options);
            }
        }

        // Pre-encoding look for Aegis\JSON\Expr objects and replacing by tmp ids
        $javascriptExpressions = array();
        if (isset($options['enableJsonExprFinder'])
           && ($options['enableJsonExprFinder'] == true)
        ) {
            $valueToEncode = static::_recursiveJsonExprFinder($valueToEncode, $javascriptExpressions);
        }

        $prettyPrint = (isset($options['prettyPrint']) && ($options['prettyPrint'] == true));

        // Encoding
        if (function_exists('json_encode') && static::$useBuiltinEncoderDecoder !== true) {
            $encodeOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

            if ($prettyPrint && defined('JSON_PRETTY_PRINT')) {
                $encodeOptions |= JSON_PRETTY_PRINT;
                $prettyPrint = false;
            }

            $encodedResult = json_encode(
                $valueToEncode,
                $encodeOptions
            );
        } else {
            $encodedResult = Encoder::encode($valueToEncode, $cycleCheck, $options);
        }

        if ($prettyPrint) {
            $encodedResult = self::prettyPrint($encodedResult, array("intent" => "    "));
        }

        //only do post-processing to revert back the Aegis\JSON\Expr if any.
        if (count($javascriptExpressions) > 0) {
            $count = count($javascriptExpressions);
            for ($i = 0; $i < $count; $i++) {
                $magicKey = $javascriptExpressions[$i]['magicKey'];
                $value    = $javascriptExpressions[$i]['value'];

                $encodedResult = str_replace(
                    //instead of replacing "key:magicKey", we replace directly magicKey by value because "key" never changes.
                    '"' . $magicKey . '"',
                    $value,
                    $encodedResult
                );
            }
        }

        return $encodedResult;
    }

    /**
     * Check & Replace Aegis\JSON\Expr for tmp ids in the valueToEncode
     *
     * Check if the value is a Aegis\JSON\Expr, and if replace its value
     * with a magic key and save the javascript expression in an array.
     *
     * NOTE this method is recursive.
     *
     * NOTE: This method is used internally by the encode method.
     *
     * @see encode
     * @param mixed $value a string - object property to be encoded
     * @param array $javascriptExpressions
     * @param null|string|int $currentKey
     * @return mixed
     */
    protected static function _recursiveJsonExprFinder(
        &$value,
        array &$javascriptExpressions,
        $currentKey = null
    ) {
        if ($value instanceof Expr) {
            // TODO: Optimize with ascii keys, if performance is bad
            $magicKey = "____" . $currentKey . "_" . (count($javascriptExpressions));
            $javascriptExpressions[] = array(

                //if currentKey is integer, encodeUnicodeString call is not required.
                "magicKey" => (is_int($currentKey)) ? $magicKey : Encoder::encodeUnicodeString($magicKey),
                "value"    => $value->__toString(),
            );
            $value = $magicKey;
        } elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = static::_recursiveJsonExprFinder($value[$k], $javascriptExpressions, $k);
            }
        } elseif (is_object($value)) {
            foreach ($value as $k => $v) {
                $value->$k = static::_recursiveJsonExprFinder($value->$k, $javascriptExpressions, $k);
            }
        }
        return $value;
    }

    /**
     * Pretty-print JSON string
     *
     * Use 'indent' option to select indentation string - by default it's a tab
     *
     * @param string $json Original JSON string
     * @param array $options Encoding options
     * @return string
     */
    public static function prettyPrint($json, $options = array())
    {
        $tokens = preg_split('|([\{\}\]\[,])|', $json, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = "";
        $indent = 0;

        $ind = "    ";
        if (isset($options['indent'])) {
            $ind = $options['indent'];
        }

        $inLiteral = false;
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token == "") {
                continue;
            }

            if (preg_match('/^("(?:.*)"):[ ]?(.*)$/', $token, $matches)) {
                $token = $matches[1] . ': ' . $matches[2];
            }

            $prefix = str_repeat($ind, $indent);
            if (!$inLiteral && ($token == "{" || $token == "[")) {
                $indent++;
                if ($result != "" && $result[strlen($result)-1] == "\n") {
                    $result .= $prefix;
                }
                $result .= "$token\n";
            } elseif (!$inLiteral && ($token == "}" || $token == "]")) {
                $indent--;
                $prefix = str_repeat($ind, $indent);
                $result .= "\n$prefix$token";
            } elseif (!$inLiteral && $token == ",") {
                $result .= "$token\n";
            } else {
                $result .= ($inLiteral ?  '' : $prefix) . $token;

                //remove escaped backslash sequences causing false positives in next check
                $token = str_replace('\\', '', $token);
                // Count # of unescaped double-quotes in token, subtract # of
                // escaped double-quotes and if the result is odd then we are
                // inside a string literal
                if ((substr_count($token, '"')-substr_count($token, '\\"')) % 2 != 0) {
                    $inLiteral = !$inLiteral;
                }
            }
        }
        return $result;
    }
}
