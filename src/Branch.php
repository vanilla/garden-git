<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Git\Exception\GitException;

/**
 * Represents a git branch.
 */
class Branch extends PartialBranch {

    /** @var string */
    private $commitHash;

    /** @var string|null */
    private $remoteName;

    /** @var string|null */
    private $remoteBranchName;

    /**
     * Constructor.
     *
     * @param string $name The name of the branch.
     * @param string|CommitishInterace $commitHash A commit hash (or something pointing to one).
     */
    public function __construct(string $name, $commitHash) {
        parent::__construct($name);
        $this->commitHash = $commitHash instanceof CommitishInterace ? $commitHash->getCommitish() : $commitHash;
    }

    /**
     * Create a branch object from a line of git output.
     * Line should be generated with git for-each-ref with the format from gitFormat().
     *
     * @param string $outputLine A single line of the output.
     *
     * @return Branch The branch data.
     * @throws GitException If the line couldn't be parsed properly.
     */
    public static function fromGitOutputLine(string $outputLine): Branch {
        $pieces = explode("|", $outputLine);
        if (count($pieces) !== 3) {
            throw new GitException("Failed to parse branch output. Expected 3 parts:\n" . $outputLine);
        }

        $branchName = trim($pieces[0]);
        $commitHash = trim($pieces[1]);
        $upstream = trim($pieces[2]);
        $branch = new Branch($branchName, $commitHash);

        // Breakup the upstream
        if (!empty($upstream)) {
            // Upstream should be a remote.
            if (!str_starts_with($upstream, "refs/remotes/")) {
                throw new GitException("Only parsing of remote upstreams is implemented.");
            }

            $fullRemotePath = preg_replace('/^refs\/remotes\//', '', $upstream);
            $remotePathPieces = explode("/", $fullRemotePath);
            if (count($remotePathPieces) < 2) {
                throw new GitException('Could not find a remote name in remote upstream ref: ' . $fullRemotePath);
            }

            $branch->remoteName = $remotePathPieces[0];
            $branch->remoteBranchName = implode("/", array_slice($remotePathPieces, 1));
        }
        return $branch;
    }

    /**
     * Git format for a branch that we can parse using `git for-each-ref`.
     *
     * @return string
     */
    public static function gitFormat(): string {
        return <<<FORMAT
%(refname:short) | %(objectname:short) | %(upstream)
FORMAT;
    }

    /**
     * @return string
     */
    public function getCommitHash(): string {
        return $this->commitHash;
    }

    /**
     * @return string|null
     */
    public function getRemoteName(): ?string {
        return $this->remoteName;
    }

    /**
     * @return string|null
     */
    public function getRemoteBranchName(): ?string {
        return $this->remoteBranchName;
    }

    /**
     * @return string
     */
    public function getCommitish(): string {
        return $this->name;
    }
}
