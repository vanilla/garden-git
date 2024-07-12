<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Git\Exception\GitException;
use Garden\Git\Exception\NotFoundException;
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
     * @param string|null $gitPath
     *
     * @throws GitException If the repository does not exist.
     */
    public function __construct(string $dir, ?string $gitPath = null) {
        $executableFinder = new ExecutableFinder();
        $gitPath = $gitPath ?? $executableFinder->find('git');
        if ($gitPath === null) {
            // Since CI always has git installed we don't really have a way to test this.
            // @codeCoverageIgnoreStart
            throw new GitException("Could not locate a git binary on the system.");
            // @codeCoverageIgnoreEnd
        }
        $this->gitPath = $gitPath;
        $this->fileSystem = new Filesystem();
        $this->dir = $dir;

        $this->validateRepositoryExists();
    }

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
     *
     * @throws GitException If the command did not execute successfully.
     */
    public function git(array $args): string {
        $gitPath = explode(" ", $this->gitPath);
        $process = new Process(array_merge($gitPath, $args), $this->dir);
        $process->setTimeout(null);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new GitException($e->getMessage(), 500, $e);
        }

        $output = $process->getOutput();
        return $output;
    }

    /**
     * Run an arbitrary git command within the repository.
     *
     * @param string[] $args The git subcommand and arguments.
     *
     * @return \Generator A generator the returns the successful output of the command.
     *
     * @throws GitException If the command did not execute successfully.
     */
    public function gitIterator(array $args): \Generator {
        $gitPath = explode(" ", $this->gitPath);
        $process = new Process(array_merge($gitPath, $args), $this->dir);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->start();
        yield from $process->getIterator(Process::ITER_KEEP_OUTPUT);

        if (!$process->isSuccessful()) {
            throw new GitException($process->getErrorOutput(), 500, new ProcessFailedException($process));
        }

        return $process->getOutput();
    }

    /**
     * Get the structured status of a repository.
     *
     * @return Status The status.
     *
     * @throws GitException If the command did not execute successfully.
     */
    public function getStatus(): Status {
        $result = $this->git(["status", "--porcelain", "--ignored"]);
        $status = new Status($result);
        return $status;
    }

    /**
     * Locate a tag by it's name.
     *
     * @param string $tagName
     *
     * @return Tag|null
     *
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
     *
     * @return Tag
     *
     * @throws GitException
     * @throws NotFoundException
     */
    public function getTag(string $tagName): Tag {
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
     *
     * @return Tag[]
     *
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

    ///
    /// Staging
    ///


    /**
     * Stage files into the git staging areas.
     *
     * @param string[] $paths An array of git file patterns. Use [.] For all files.
     *
     * @return Status
     *
     * @throws GitException
     */
    public function stageFiles(array $paths = ['.']): Status {
        $this->git(array_merge(
            ['add', '--'],
            $paths
        ));

        return $this->getStatus();
    }

    /**
     * Unstage files from the git staging area.
     *
     * @param string[] $paths An array of git file patterns. Use [.] For all files.
     *
     * @return Status
     *
     * @throws GitException
     */
    public function unstageFiles(array $paths): Status {
        $this->git(array_merge(
            ['reset', '--'],
            $paths
        ));

        return $this->getStatus();
    }

    /**
     * Clear staged and unstaged stages so the repo state matches HEAD.
     *
     * @return Status
     *
     * @throws GitException
     */
    public function resetFiles(): Status {
        $this->stageFiles();
        $this->git([
            "reset",
            "--hard",
        ]);
        return $this->getStatus();
    }

    /**
     * Restore paths from a particular commit or branch.
     *
     * - Files not present in that branch will be deleted.
     * - Only files in directories matching $paths will be restored.
     *
     * @param string[] $paths An array of git file patterns. Use [.] For all files.
     * @param CommitishInterace $commitish
     *
     * @return Status
     * @throws GitException
     */
    public function restoreFiles(array $paths, CommitishInterace $commitish): Status {
        $statusBefore = $this->getStatus();
        if ($statusBefore->hasChanges()) {
            throw new GitException('Cannot restore when their are uncommitted changes.');
        }
        $this->git(array_merge(
            [
                '-c', 'core.ignorecase=true',
                'checkout',
                '--no-overlay',
                $commitish->getCommitish(),
                '--',
            ],
            $paths
        ));

        // If there are case-sensitively renamed files on a renamed file system
        // They get really screwed up if we don't do this.
        // Here's an example out of doing the previous command and selecting
        // A case-sensitively renamed file on macos.

        // On branch main
        // Changes to be committed:
	    //     renamed:    dir1/file4 -> dir1/File4
        // Changes not staged for commit:
	    //     deleted:    dir1/File4

        // If we don't do any further steps than the file will be deleted entirely
        // Even though it was supposed to be renamed.
        $this->git([
            '-c', 'core.ignorecase=true',
            "checkout",
            '--',
            '.'
        ]);

        $this->stageFiles();
        return $this->getStatus();
    }

    ///
    /// Commits
    ///

    /**
     * Commit everything in the working tree.
     *
     * @param string $msg The commit message.
     *
     * @return Commit
     *
     * @throws GitException If the commit could not be completed.
     */
    public function commit(string $msg): Commit {
        $result = $this->git(["commit", "-m", $msg]);
        /**
         * Output ends up looking like this
         *
         * @example
         * [main (root-commit) 49c28cc] File 5
         * 1 file changed, 0 insertions(+), 0 deletions(-)
         * create mode 100644 file5
         */

        preg_match('/\s([^)]*)]/', $result, $m);
        $hash = $m[1] ?? null;
        if (empty($hash)) {
            // This codepath really exists in case we are incompatible with some future git output format.
            // @codeCoverageIgnoreStart
            throw new GitException("Could not find commit-hash in commit result:\n" . $result);
            // @codeCoverageIgnoreEnd
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
     * Create an annotated tag for a commit.
     *
     * @param string $tagName
     * @param string $message
     * @param CommitishInterace $from
     *
     * @return Tag
     *
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

    /**
     * Delete a tag from the remote.
     *
     * @param Tag $tag
     *
     * @throws NotFoundException
     * @throws GitException
     */
    public function deleteTag(Tag $tag): void {
        $tag = $this->getTag($tag->getName());
        $this->git([
            'tag',
            '--delete',
            $tag->getName(),
        ]);
    }

    ///
    /// Remotes
    ///

    /**
     * Get the current remotes for this repo.
     *
     * @return Remote[]
     *
     * @throws GitException
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
     * @throws GitException
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
     *
     * @throws NotFoundException
     * @throws GitException
     */
    public function getRemote(string $name): Remote {
        $remote = $this->findRemote($name);
        if ($remote === null) {
            throw new NotFoundException("Remote", $name);
        }
        return $remote;
    }

    /**
     * Fetch everything from a remote.
     *
     * @param Remote $remote
     *
     * @throws GitException
     */
    public function fetchFromRemote(Remote $remote): void {
        foreach ($this->fetchFromRemoteIterator($remote) as $_) {
            // Not doing anything with the output here.
        }
    }

    /**
     * Fetch everything from a remote in an iterator.
     *
     * @param Remote $remote
     *
     * @return \Generator
     *
     * @throws GitException
     */
    public function fetchFromRemoteIterator(Remote $remote): \Generator {
        return $this->gitIterator(['fetch', $remote->getName(), '--progress']);
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
        return $newRemote;
    }

    /**
     * Remove a remote from the repo.
     *
     * @param Remote $remote
     *
     * @throws GitException
     */
    public function removeRemote(Remote $remote): void {
        $this->git([
            'remote',
            'remove',
            $remote->getName()
        ]);
    }

    ///
    /// Branches
    ///

    /**
     * Switch branches.
     *
     * @param Branch $branch
     *
     * @throws GitException
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
     * Lookup a branch.
     *
     * @param string|PartialBranch $branchName
     *
     * @return Branch|null
     *
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

    /**
     * Create a branch from a commit, tag, or other branch.
     *
     * @param string $branchName
     * @param CommitishInterace|null $startPoint
     *
     * @return Branch
     *
     * @throws GitException
     * @throws NotFoundException
     */
    public function createBranch(string $branchName, CommitishInterace $startPoint = null) {
        $existingBranch = $this->findBranch($branchName);
        if ($existingBranch !== null) {
            throw new GitException('Branch already exists: ' . $branchName);
        }
        $startPoint = $startPoint ?? new Head();
        $this->git(["branch", $branchName, $startPoint->getCommitish()]);
        return $this->getBranch($branchName);
    }

    /**
     * Delete a branch.
     *
     * @param Branch $branch The branch to delete.
     * @param bool $pushDeleteToRemote If true, also delete the branch on its configured remote.
     *
     * @throws GitException
     * @throws NotFoundException
     */
    public function deleteBranch(Branch $branch, bool $pushDeleteToRemote = false): void {
        $branch = $this->getBranch($branch->getName());
        $currentBranch = $this->currentBranch();
        if ($currentBranch->getName() === $branch->getName()) {
            throw new GitException('Cannot delete the checked out branch: ' . $branch->getName());
        }

        $this->git([
            'branch',
            '--delete',
            $branch->getName(),
        ]);

        if ($pushDeleteToRemote && $branch->getRemoteName() !== null && $branch->getRemoteBranchName() !== null) {
            $this->git([
                'push',
                $branch->getRemoteName(),
                '--delete',
                // Be specific that it's a branch
                // Otherwise we could have mismatch between tags and branches.
                'refs/heads/' . $branch->getRemoteBranchName(),
            ]);
        }
    }

    /**
     * Create a branch from a remote.
     *
     * @param string|PartialBranch $branch The name of the new local branch.
     * @param Remote $remote The remote to pull from.
     * @param string $remoteBranchName The name of the remote branch.
     *
     * @return Branch The new branch.
     *
     * @throws GitException
     * @throws NotFoundException
     */
    public function createBranchFromRemote(PartialBranch $branch, Remote $remote, string $remoteBranchName): Branch {
        $existingBranch = $this->findBranch($branch);
        if ($existingBranch) {
            throw new GitException("Branch already exists: {$branch->getName()}");
        }

        $remoteBranchName = $remoteBranchName ?? $existingBranch->getName();

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
     *
     * @throws NotFoundException
     * @throws GitException
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
     * Get all branches.
     *
     * @return Branch[]
     *
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

    /**
     * Get the current branch.
     *
     * @return Branch
     *
     * @throws GitException
     * @throws NotFoundException
     */
    public function currentBranch(): Branch {
        $gitOutput = $this->git([
            'rev-parse',
            '--abbrev-ref',
            'HEAD',
        ]);
        return $this->getBranch(trim($gitOutput));
    }

    /**
     * Push a branch to a remote.
     *
     * @param Branch $branch
     * @param Remote $remote
     *
     * @return Branch
     *
     * @throws GitException
     * @throws NotFoundException
     */
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
