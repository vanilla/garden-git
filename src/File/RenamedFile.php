<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\File;

/**
 * Represents a renamed file.
 */
class RenamedFile extends AbstractFile {

    /** @var string */
    private $oldPath;

    public function __construct(string $newPath, string $oldPath) {
        parent::__construct(
            $newPath,
            // For git to recognize a rename, it must be staged.
            // Otherwise it is a delete and an add.
            true,
            false
        );
        $this->oldPath = trim($oldPath);
    }

    /**
     * @return string
     */
    public function getOldPath(): string {
        return $this->oldPath;
    }
}
