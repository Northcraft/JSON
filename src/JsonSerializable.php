<?php
/**
 * This file is part of Aegis\JSON, a simple library to handle JSON data
 */

namespace Aegis\JSON;

/**
 * Alternative Interface in favor of JsonSerializable under PHP 5.4
 */
if (interface_exists("\\JsonSerializable")) {
    interface JsonSerializable extends \JsonSerializable
    {
    }
} else {
    interface JsonSerializable
    {
        /**
         * Specify data which should be serialized to JSON
         * @return mixed data which can be serialized by <b>Encoder::encode()</b>,
         * which is a value of any type other than a resource.
         */
        function jsonSerialize();
    }
}