<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Exception;

/**
 * Exception thrown when something couldn't be found.
 */
class NotFoundException extends GitException {

    /**
     * Constructor.
     *
     * @param string $type The type of item that could not be found.
     * @param string $reference A refernce to identify the item that couldn't be found.
     * @param \Throwable|null $previous
     */
    public function __construct(string $type, string $reference, \Throwable $previous = null) {
        $message = sprintf("%s Not Found: '%s'", ucfirst($type), $reference);
        parent::__construct($message, 404, $previous);
    }
}
