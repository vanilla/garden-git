<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests\Fixtures;

use Garden\Git;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Class that sets up git in a temporary directory.
 */
class TestGitDirectory {

    /** @var string */
    private $cwd;

    /** @var Filesystem */
    private $fs;

    /** @var Git\Repository */
    private $repository;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->fs = new Filesystem();
        $this->cwd = Path::normalize(sys_get_temp_dir() . '/' . random_int(0, 100000));
        $this->fs->mkdir($this->cwd);
        $process = new Process(["git", "init"], $this->cwd);
        $process->mustRun();
        $this->repository = new TestGitRepository($this->cwd);
        $this->configureAuthor($this->getAuthor());
    }

    /**
     * @return Filesystem
     */
    public function getFs(): Filesystem {
        return $this->fs;
    }

    /**
     * Get the primary author we are using.
     *
     * @return Git\Author
     */
    public function getAuthor(): Git\Author {
        return new Git\Author("Test Name", "test@example.com");
    }

    /**
     * Get an alternative author.
     *
     * @return Git\Author
     */
    public function getAltAuthor(): Git\Author {
        return new Git\Author("Alt Name", "alt@example.com");
    }

    /**
     * Configure the current author.
     *
     * @param Git\Author $author
     */
    public function configureAuthor(Git\Author $author) {
        $this->repository->git(["config", "user.name", $author->getName()]);
        $this->repository->git(["config", "user.email", $author->getEmail()]);
    }

    /**
     * @return string
     */
    public function getCwd(): string {
        return $this->cwd;
    }

    /**
     * @return Git\Repository
     */
    public function getRepository(): Git\Repository {
        return $this->repository;
    }

    /**
     * Add and commit all files in the directory.
     *
     * @param string $commitMsg
     *
     * @return Git\Commit
     */
    public function addAndCommitAll(string $commitMsg): Git\Commit {
        $this->repository->git(["add", "."]);
        return $this->repository->commit($commitMsg);
    }

    /**
     * Add a file to the git directory.
     *
     * @param string $path
     *
     * @return string
     */
    public function touchFile(string $path): string {
        $filePath = Path::join($this->cwd, $path);
        $this->fs->touch($filePath);
        return $filePath;
    }

    /**
     * Remove a file.
     *
     * @param string $path
     */
    public function removeFile(string $path): void {
        $filePath = Path::join($this->cwd, $path);
        $this->fs->remove($filePath);
    }

    /**
     * Create a directory structure based on nested arrays.
     *
     * @param array $structure
     * @example
     * [
     *    'dir1' => [
     *        'nested' => [
     *             'file1',
     *        ],
     *        'file2',
     *        'file3',
     *    ],
     *    'file4',
     * ]
     * @param string $basePath Used for recursion.
     *
     * @return string[] The file paths created.
     */
    public function touchDirStructure(array $structure, string $basePath = ''): array {
        $createdPaths = [];
        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $dirPath = Path::join($basePath, $key);
                $this->fs->mkdir(Path::join($this->cwd, $dirPath));
                $recursivePaths = $this->touchDirStructure($value, $dirPath);
                $createdPaths = array_merge($createdPaths, $recursivePaths);
            } else {
                $filePath = Path::join($basePath, $value);
                $createdPaths[] = $filePath;
                $realPath = Path::join($this->getCwd(), $filePath);
                $fileAlreadyExists = $this->fs->exists($realPath);
                $this->fs->touch($realPath);
                if (!$fileAlreadyExists) {
                    $this->fs->appendToFile($realPath, random_int(0, 99999999));
                }
            }
        }
        return $createdPaths;
    }

    /**
     * Assert that a file exists in the repo.
     *
     * @param string $path
     */
    public function assertFileExists(string $path) {
        TestCase::assertTrue($this->fileExists($path), "Expected file $path to exist.");
    }

    /**
     * Assert that a file does not exist in the repo.
     *
     * @param string $path
     */
    public function assertFileNotExists(string $path) {
        TestCase::assertFalse($this->fileExists($path), "Expected file $path not to exist.");
    }

    /**
     * Check if a file exists in the repo.
     *
     * @param string $path
     *
     * @return bool
     */
    private function fileExists(string $path) {
        return $this->fs->exists($this->getRepository()->absolutePath($path));
    }
}
