<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

/**
 * Pointer to the current HEAD commit.
 */
class Head implements CommitishInterace {

    /**
     * @return string
     */
    public function getCommitish(): string {
        return 'HEAD';
    }
}
