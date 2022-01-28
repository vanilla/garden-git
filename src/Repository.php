<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Git\Exception\GitException;
use Garden\Git\Exception\NotFoundException;
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
    protected $dir;

    /** @var string */
    protected $gitPath;

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

        $output = $process->getOutput();
        return $output;
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
     * Locate a tag by it's name.
     *
     * @param string $tagName
     * @return Tag|null
     * @throws GitException
     */
    public function findTag(string $tagName): ?Tag {
        $tags = $this->getTags();
        foreach ($tags as $tag) {
            if ($tag->getName() === $tagName) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Locate a tag by it's name.
     *
     * @param string $tagName
     * @return Tag|null
     * @throws GitException
     * @throws NotFoundException
     */
    public function getTag(string $tagName): ?Tag {
        $tag = $this->findTag($tagName);
        if ($tag === null) {
            throw new NotFoundException('Tag', $tagName);
        }
        return $tag;
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
    public function getTags(CommitishInterace $commitish = null, string $sort = Tag::SORT_NEWEST_COMMIT): array {
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
            $args[] = $commitish->getCommitish();
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
            $tags[] = Tag::fromGitOutputLine($tagSection);
        }
        return $tags;
    }

    /**
     * Commit everything in the working tree.
     *
     * @param string $msg The commit message.
     *
     * @return Commit
     * @throws GitException If the commit could not be completed.
     */
    public function commit(string $msg): Commit {
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

        $commit = $this->getCommit($hash);
        return $commit;
    }

    /**
     * Get a commit by its hash.
     *
     * @param string $commitHash
     *
     * @return Commit
     *
     * @throws NotFoundException
     * @throws GitException
     */
    public function getCommit(string $commitHash): Commit {
        try {
            $gitOutput = $this->git([
                'log',
                '--max-count=1',
                '--format=' .  Commit::gitLogFormat(),
                $commitHash,
            ]);
        } catch (GitException $e) {
            if (str_contains($e->getMessage(), 'unknown revision')) {
                throw new NotFoundException('Commit', $commitHash);
            } else {
                throw $e;
            }
        }

        $commit = Commit::fromGitOutput($gitOutput);
        return $commit;
    }

    /**
     * @param string $tagName
     * @param string $message
     * @param CommitishInterace $from
     * @return void
     * @throws GitException
     */
    public function tagCommit(CommitishInterace $from, string $tagName, string $message = ""): Tag {
        $messageArgs = [
            "-m",
            $message, // Must pass a message our git will try to interactively open a terminal for it.
        ];
        $this->git(array_merge(
            ["tag", "-a"],
            $messageArgs,
            [$tagName, $from->getCommitish()]
        ));
        return $this->getTag($tagName);
    }

    ///
    /// Remotes
    ///

    /**
     * Get the current remotes for this repo.
     *
     * @return Remote[]
     */
    public function getRemotes(): array {
        $gitOutput = $this->git(["remote", "-v"]);
        if (empty($gitOutput)) {
            return [];
        }
        $remotes = Remote::fromGitOutput($gitOutput);
        return $remotes;
    }

    /**
     * Try to find an existing remote, otherwise return null.
     *
     * @param string $remoteName The name of the remote to find.
     *
     * @return Remote|null
     */
    public function findRemote(string $remoteName): ?Remote {
        $remotes = $this->getRemotes();
        foreach ($remotes as $remote) {
            if ($remote->getName() === $remoteName) {
                return $remote;
            }
        }
        return null;
    }

    /**
     * Lookup a remote.
     *
     * @param string $name The remote.
     *
     * @return Remote
     * @throws NotFoundException
     */
    public function getRemote(string $name): Remote {
        $remote = $this->findRemote($name);
        if ($remote === null) {
            throw new NotFoundException("Remote", $name);
        }
        return $remote;
    }

    public function fetchFromRemote(Remote $remote): void {
        // Make sure the remote still exists.
        $remote = $this->getRemote($remote->getName());
        if (!$remote->canFetch()) {
            throw new GitException("Fetch is not configured for remote {$remote->getName()}");
        }

        $this->git(['fetch', $remote->getName()]);
    }

    /**
     * Add a remote to the repository.
     *
     * @param Remote $remote Info about the remote to create.
     *
     * @return Remote The newly created remote.
     *
     * @throws GitException If the remote already exists.
     * @throws NotFoundException If the remote couldn't be located after creation.
     */
    public function addRemote(Remote $remote): Remote {
        $existingRemote = $this->findRemote($remote->getName());
        if ($existingRemote) {
            throw new GitException("Remote already exists: " . $remote->getName());
        }
        $this->git(["remote", "add", $remote->getName(), $remote->getUri()]);

        $newRemote = $this->getRemote($remote->getName());
        if ($newRemote->canFetch()) {
            $this->fetchFromRemote($newRemote);
        }
        return $newRemote;
    }

    ///
    /// Branches
    ///

    /**
     * Switch branches.
     *
     * @param Branch $branch
     */
    public function switchBranch(Branch $branch): void {
        $existingBranch = $this->getBranch($branch->getName());
        $this->git([
            'switch',
            '--no-guess',
            $existingBranch->getName(),
        ]);
    }

    /**
     * @param string|PartialBranch $branchName
     * @return Branch|null
     * @throws GitException
     */
    public function findBranch($branchName): ?Branch {
        $branchName = $branchName instanceof PartialBranch ? $branchName->getName() : $branchName;
        $branches = $this->getBranches();
        foreach ($branches as $branch) {
            if ($branch->getName() ===  $branchName) {
                return $branch;
            }
        }
        return null;
    }

    public function createBranch(string $branchName, CommitishInterace $startPoint = null) {
        $existingBranch = $this->findBranch($branchName);
        if ($existingBranch !== null) {
            throw new GitException('Branch already exists: ' . $branchName);
        }
        $startPoint = $startPoint ?? new Head();
        $this->git(["branch", $branchName, $startPoint->getCommitish()]);
        return $this->getBranch($branchName);
    }

    public function createBranchFromRemote(PartialBranch $branch, Remote $remote, string $remoteBranchName): Branch {
        $existingBranch = $this->findBranch($branch);
        if ($existingBranch) {
            throw new GitException("Branch already exists: {$branch->getName()}");
        }

        $upstreamName = $remote->getName() . '/' . $remoteBranchName;

        $this->git([
            'branch',
            '--track',
            $branch->getName(),
            $upstreamName,
        ]);

        return $this->getBranch($branch->getName());
    }

    /**
     * Lookup a branch.
     *
     * @param string|PartialBranch $branchName The remote.
     *
     * @return Branch
     * @throws NotFoundException
     */
    public function getBranch($branchName): Branch {
        $branchName = $branchName instanceof PartialBranch ? $branchName->getName() : $branchName;
        $branch = $this->findBranch($branchName);
        if ($branch === null) {
            throw new NotFoundException("Branch", $branchName);
        }
        return $branch;
    }

    /**
     * @return Branch[]
     * @throws GitException
     */
    public function getBranches(): array {
        $gitOutput = $this->git([
            "for-each-ref",
            "--format",
            Branch::gitFormat(),
            'refs/heads/**'
        ]);
        $gitOutput = trim($gitOutput);
        if (empty($gitOutput)) {
            return [];
        }
        $branches = [];
        $outputLines = explode("\n", $gitOutput);
        foreach ($outputLines as $line) {
            $branches[] = Branch::fromGitOutputLine($line);
        }
        return $branches;
    }

    public function currentBranch(): Branch {
        $gitOutput = $this->git([
            'rev-parse',
            '--abbrev-ref',
            'HEAD',
        ]);
        return $this->getBranch(trim($gitOutput));
    }

    public function pushBranch(Branch $branch, Remote $remote): Branch {
        $branchName = $branch->getName();
        $remoteBranchName = $branch->getRemoteBranchName() ?: $branchName;

        $this->git([
            'push',
            '--force-with-lease',
            '--set-upstream',
            $remote->getName(),
            "{$branchName}:{$remoteBranchName}"
        ]);
        return $this->getBranch($branchName);
    }

    ///
    /// Private utilities
    ///

    /**
     * @throws GitException If the repository does not exist.
     */
    private function validateRepositoryExists() {
        // Validate the directory.
        if (!$this->fileSystem->exists($this->dir)) {
            throw new NotFoundException('Directory', $this->dir);
        }

        if (!$this->fileSystem->exists($this->absolutePath(".git"))) {
            throw new NotFoundException('Git Root', $this->dir);
        }
    }
}
