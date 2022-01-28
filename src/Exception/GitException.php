<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Exception;

use Garden\Git\Tests\Fixtures\TestGitRepository;

/**
 * Exception thrown by some git process.
 */
class GitException extends \Exception {

    /**
     * @inheritdoc
     */
    public function __construct($message = "", $code = 500, \Throwable $previous = null) {
        if (class_exists(TestGitRepository::class, false)) {
            $message .= TestGitRepository::getExceptionDebugMessage();
        }
        parent::__construct($message, $code, $previous);
    }
}
