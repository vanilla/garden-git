<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

class PartialBranch implements CommitishInterace {

    /** @var string */
    private $name;

    /**
     * Constructor.
     *
     * @param string $name
     */
    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getCommitHash(): string {
        // A branch name can be safely used in place of a git hash.
        // Git will resolve it to it's current HEAD commit.
        return $this->name;
    }
}
