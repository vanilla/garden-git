<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Git\Exception\GitException;
use Garden\Git\Status\RepositoryStatus;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Class representing a local git repository.
 */
class Repository {

    /** @var Filesystem */
    private $fileSystem;

    /** @var string */
    private $dir;

    /** @var string */
    private $gitPath;

    /**
     * Constructor.
     *
     * @param string $dir
     *
     * @throws GitException If the repository does not exist.
     */
    public function __construct(string $dir) {
        $executableFinder = new ExecutableFinder();
        $gitPath = $executableFinder->find('git');
        if ($gitPath === null) {
            throw new GitException("Could not locate a git binary on the system.");
        }
        $this->gitPath = $gitPath;
        $this->fileSystem = new Filesystem();
        $this->dir = $dir;

        $this->validateRepositoryExists();
    }

    ///
    /// Public interface.
    ///

    /**
     * Get a full file path relative within the repository.
     *
     * @param string ...$pieces Multiple path pieces.
     *
     * @return string The full absolute file path.
     */
    public function absolutePath(string ...$pieces): string {
        return Path::canonicalize(Path::join($this->dir, ...$pieces));
    }

    /**
     * Run an arbitrary git command within the repository.
     *
     * @param string[] $args The git subcommand and arguments.
     *
     * @return string The successful output of the command.
     * @throws GitException If the command did not execute successfully.
     */
    public function git(array $args): string {
        $process = new Process(array_merge([$this->gitPath], $args), $this->dir);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new GitException($e->getMessage(), 500, $e);
        }
        return $process->getOutput();
    }

    /**
     * Get the structured status of a repository.
     *
     * @return RepositoryStatus The status.
     *
     * @throws GitException If the command did not execute successfully.
     */
    public function getStatus(): RepositoryStatus {
        $result = $this->git(["status", "--porcelain"]);
        $status = new RepositoryStatus($result);
        return $status;
    }

    /**
     * Get tags reachable on a current branch.
     *
     * @param CommitishInterace|null $commitish Only output tags reachable from the HEAD of this branch.
     * @param int|null $limit The limit of tags to fetch.
     *
     * @return Tag[]
     * @throws GitException
     */
    public function getTags(CommitishInterace $commitish = null, string $sort = Tag::SORT_NEWEST_COMMIT, int $limit = null): array {
        $args = [
            // Notably the `git log` command is considered a "porcelain" command
            // and can be influenced by a user's .gitconfig.
            // As a result the safer API (and more flexible one) in any case is `git for-each-ref`.
            "for-each-ref",
            "--sort=$sort",
            '--format',
            Tag::getFormat(),
        ];
        if ($commitish !== null) {
            $args[] = "--merged";
            $args[] = $commitish->getCommitHash();
        }

        if ($limit !== null) {
            $args[] = '--count';
            $args[] = $limit;
        }

        $args[] = 'refs/tags';

        $result = $this->git($args);
        if (empty($result)) {
            return [];
        }
        $tagSections = explode("\ntag:", $result);
        $tags = [];
        foreach ($tagSections as $tagSection) {
            if (!str_starts_with($tagSection, "tag: ")) {
                $tagSection = "tag: " . $tagSection;
            }
            $tags[] = Tag::fromFormatOutput($tagSection);
        }
        return $tags;
    }

    /**
     * Commit everything in the working tree.
     *
     * @param string $msg The commit message.
     *
     * @return PartialCommit
     * @throws GitException If the commit could not be completed.
     */
    public function commit(string $msg): PartialCommit {
        $result = $this->git(["commit", "-m", $msg]);
        /**
         * Output ends up looking like this
         *
         * @example
         * [master (root-commit) 49c28cc] File 5
         * 1 file changed, 0 insertions(+), 0 deletions(-)
         * create mode 100644 file5
         */

        preg_match('/\s([^)]*)]/', $result, $m);
        $hash = $m[1] ?? null;
        if (empty($hash)) {
            throw new GitException("Could not find commit-hash in commit result:\n" . $result);
        }
        return new PartialCommit($hash);
    }

    /**
     * @param string $tagName
     * @param string $message
     * @param CommitishInterace $from
     * @return void
     * @throws GitException
     */
    public function tagCommit(CommitishInterace $from, string $tagName, string $message = "") {
        $messageArgs = [
            "-m",
            $message, // Must pass a message our git will try to interactively open a terminal for it.
        ];
        $this->git(array_merge(
            ["tag", "-a"],
            $messageArgs,
            [$tagName, $from->getCommitHash()]
        ));
    }

    public function getRemotes() {}

    public function addRemote() {}

    public function getBranches(): array { }

    public function createBranch(string $branchName, string $from){}

    public function pushBranch() {}

    ///
    /// Private utilities
    ///

    /**
     * @throws GitException If the repository does not exist.
     */
    private function validateRepositoryExists() {
        // Validate the directory.
        if (!$this->fileSystem->exists($this->dir)) {
            throw new GitException("Directory {$this->dir} does not exist.");
        }

        if (!$this->fileSystem->exists($this->absolutePath(".git"))) {
            throw new GitException("{$this->dir} is not the root of a git repository.");
        }
    }
}
