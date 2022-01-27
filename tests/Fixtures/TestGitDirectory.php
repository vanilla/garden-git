<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests\Fixtures;

use Garden\Git;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class TestGitDirectory {

    public const USER_NAME = "Test Name";
    public const USER_EMAIL = "test@example.com";

    /** @var string */
    private $cwd;

    /** @var Filesystem */
    private $fs;

    /** @var Git\Repository */
    private $repository;

    public function __construct() {
        $this->fs = new Filesystem();
        $this->cwd = Path::normalize(sys_get_temp_dir() . '/' . random_int(0, 100000));
        $this->fs->mkdir($this->cwd);
        $process = new Process(["git", "init"], $this->cwd);
        $process->mustRun();
        $this->repository = new Git\Repository($this->cwd);
        $this->configureAuthor($this->getAuthor());
    }

    public function getAuthor(): Git\Author {
        return new Git\Author("Test Name", "test@example.com");
    }

    public function getAltAuthor(): Git\Author {
        return new Git\Author("Alt Name", "alt@example.com");
    }

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

    public function addAndCommitAll(string $commitMsg): Git\PartialCommit {
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

    public function modifyFile(string $path, string $toAppend): string {
        $filePath = Path::join($this->cwd, $path);
        if (!$this->fs->exists($path)) {
            throw new Git\Exception\NotFoundException('File', $filePath);
        }

        $this->fs->appendToFile($path, $toAppend);
        return $filePath;
    }

    public function deleteFile(string $path): string {
        $filePath = Path::join($this->cwd, $path);
        if (!$this->fs->exists($path)) {
            throw new Git\Exception\NotFoundException('File', $filePath);
        }

        $this->fs->remove($filePath);
        return $filePath;
    }
}
