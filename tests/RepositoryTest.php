<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git\Exception\GitException;
use Garden\Git\Repository;
use Garden\Git\Tests\Fixtures\GitTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Tests for the repository class.
 */
class RepositoryTest extends GitTestCase {

    /**
     * Make sure we validate directories exist.
     */
    public function testBadDirectory() {
        $tempdir = $this->createTempDir();
        $this->expectExceptionMessage("Directory Not Found");
        new Repository($tempdir . '/not-real');
    }

    /**
     * Make sure we validate we are in the git root.
     */
    public function testNotGitRoot() {
        $tempdir = $this->createTempDir();
        $this->expectExceptionMessage("Git Root Not Found");
        new Repository($tempdir);
    }

    /**
     * Coverage for getCommit() rethrow parent exception codepath.
     */
    public function testGetGetCommitEmpty() {
        $this->expectException(GitException::class);
        $this->repo()->getCommit("--not-an-arg-not-a-commit");
    }

    /**
     * Create a temporary directory.
     *
     * @return string
     */
    private function createTempDir(): string {
        $fs = new Filesystem();
        $tempDir = Path::normalize(sys_get_temp_dir() . '/' . random_int(0, 100000));
        $fs->mkdir($tempDir);
        return $tempDir;
    }
}
