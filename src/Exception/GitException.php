<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Exception;

/**
 * Exception thrown by some git process.
 */
class GitException extends \Exception {

    /**
     * @inheritdoc
     */
    public function __construct($message = "", $code = 500, \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
