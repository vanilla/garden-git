<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git\File\AddedFile;
use Garden\Git\File\DeletedFile;
use Garden\Git\File\RenamedFile;
use Garden\Git\Head;
use Garden\Git\Tests\Fixtures\GitTestCase;

/**
 * Tests for staging of files.
 */
class StagingTest extends GitTestCase {

    /**
     * Test staging and unstaging files.
     */
    public function testStageFiles() {
        $this->dir()->touchDirStructure([
            'dir1' => [
                'file1',
                'file2',
            ],
            'file3',
        ]);

        $status = $this->repo()->stageFiles(['dir1']);
        $this->assertCount(2, $status->getStagedFiles());
        $this->assertCount(1, $status->getUnstagedFiles());

        $status = $this->repo()->unstageFiles(['dir1/file1']);
        $this->assertCount(1, $status->getStagedFiles());
        $this->assertCount(2, $status->getUnstagedFiles());

        $status = $this->repo()->stageFiles();
        $this->assertCount(3, $status->getStagedFiles());
        $this->assertCount(0, $status->getUnstagedFiles());

        $status = $this->repo()->resetFiles();
        $this->assertCount(0, $status->getFiles());
        $this->dir()->assertFileNotExists('dir1/file1');
        $this->dir()->assertFileNotExists('dir1/file2');
        $this->dir()->assertFileNotExists('file3');
    }

    /**
     * Make sure we check our structure first.
     */
    public function testRestoreChecksCleanStagingArea() {
        $this->dir()->touchDirStructure(['file1']);
        $this->expectExceptionMessage("Cannot restore when their are uncommitted changes");
        $this->repo()->restoreFiles(["."], new Head());
    }

    /**
     * Test a restore of a complex directory structure.
     */
    public function testRestore() {
        $this->dir()->touchDirStructure([
            'file1',
            'file2',
            'dir1' => [
                'toremove' => [
                    'file3',
                ],
                'file4',
            ],
        ]);
        $this->repo()->stageFiles();
        $commit1 = $this->repo()->commit("Commit 1");
        $masterBranch = $this->repo()->currentBranch();

        // Let's make a new branch.
        $branch1 = $this->repo()->createBranch("branch1", $commit1);
        $this->repo()->switchBranch($branch1);
        $this->dir()->removeFile("dir1/toremove");
        $this->dir()->removeFile("file2");
        $this->dir()->touchDirStructure([
            'dir1' => [
                'added' => [
                    'file5',
                ],
                'file6'
            ],
            'not-in-path-spec'
        ]);
        $this->repo()->git(["mv", "dir1/file4", "dir1/File4"]);
        $this->repo()->stageFiles();
        $this->repo()->commit("changing files");

        // Now switch back to master and remove the files.
        $this->repo()->switchBranch($masterBranch);
        // Just part of the path spec.
        $status = $this->repo()->restoreFiles([
            'file1',
            'file2',
            'dir1/',
        ], $branch1);
        $this->dir()->assertFileNotExists('not-in-path-spec');
        $this->assertEquals([
            new AddedFile('dir1/added/file5', true, false),
            new AddedFile('dir1/file6', true, false),
            new DeletedFile("dir1/toremove/file3", true, false),
            new DeletedFile("file2", true, false),
            new RenamedFile('dir1/File4', 'dir1/file4')
        ], $status->getFiles());

        // Now run without the pathspec.
        $this->repo()->commit("Copy over");
        $status = $this->repo()->restoreFiles(["."], $branch1);
        $this->dir()->assertFileExists('not-in-path-spec');
        $this->assertEquals([
            new AddedFile('not-in-path-spec', true, false),
        ], $status->getFiles());
    }
}
