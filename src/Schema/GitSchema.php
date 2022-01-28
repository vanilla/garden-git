<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Schema;

use Garden\Git\Exception\GitException;
use Garden\Schema\Schema;
use Garden\Schema\ValidationException;

/**
 * Schema that throws GitExceptions instead of ValidationExceptions.
 */
class GitSchema extends Schema {

    /**
     * Overridden to throw the correct type of exception.
     *
     * @inheritDoc
     * @throws GitException
     */
    public function validate($data, $sparse = false) {
        try {
            return parent::validate($data, $sparse);
        } catch (ValidationException $exception) {
            throw new GitException($exception->getMessage(), 422, $exception);
        }
    }
}
