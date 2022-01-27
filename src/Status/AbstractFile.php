<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Status;

abstract class AbstractFile {

    /** @var string */
    private $path;

    /** @var bool */
    private $hasStaged;

    /** @var bool */
    private $hasUnstaged;

    /**
     * Constructor.
     *
     * @param string $path
     * @param bool $hasStaged
     * @param bool $hasUnstaged
     */
    public function __construct(string $path, bool $hasStaged, bool $hasUnstaged) {
        $this->path = trim($path);
        $this->hasStaged = $hasStaged;
        $this->hasUnstaged = $hasUnstaged;
    }

    /**
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * @return bool
     */
    public function hasStaged(): bool {
        return $this->hasStaged;
    }

    /**
     * @return bool
     */
    public function hasUnstaged(): bool {
        return $this->hasUnstaged;
    }
}
