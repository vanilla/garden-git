<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

/**
 * Interface representing a git object that can be resolved to some commit object.
 */
interface CommitishInterace {

    /**
     * @return string
     */
    public function getCommitHash(): string;
}
