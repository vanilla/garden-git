<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

/**
 * Use this in place of a `Commit` if you just have the hash.
 */
final class PartialCommit implements CommitishInterace {

    /** @var string */
    private $commitHash;

    /**
     * Constructor.
     *
     * @param string $commitHash
     */
    public function __construct(string $commitHash) {
        $this->commitHash = $commitHash;
    }

    /**
     * @return string
     */
    public function getCommitHash(): string {
        return $this->commitHash;
    }
}
