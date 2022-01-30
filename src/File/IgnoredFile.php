<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\File;

/**
 * Represents a git ignored file.
 */
class IgnoredFile extends AbstractFile {

    /**
     * Overridden because ignored files can't be staged or unstaged.
     *
     * @param string $path
     */
    public function __construct(string $path) {
        parent::__construct($path, false, false);
    }
}
