<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git;
use Garden\Git\Status\AddedFile;
use Garden\Git\Status\DeletedFile;
use Garden\Git\Status\ModifiedFile;
use Garden\Git\Status\RenamedFile;

/**
 * Tests for fetching statuses.
 */
class StatusTest extends LocalGitTestCase {

    /**
     * Test actual statuses on a real repo.
     *
     * @return void
     */
    public function testStatusE2E() {
        $this->dir()->touchFile("test1");
        $this->repo()->git(["add", "."]);
        $this->dir()->touchFile("test2");
        $status = $this->repo()->getStatus();
        $this->assertEquals(true, $status->hasChanges());
        $this->assertEquals(
            [
                new Git\Status\AddedFile('test1', true, false),
                new Git\Status\AddedFile('test2', false, true),
            ],
            $status->getAdded()
        );
    }


    /**
     * Test top level change detection.
     */
    public function testHasChanges() {
        // No changes.
        $status = new Git\Status\RepositoryStatus("");
        $this->assertEquals(false, $status->hasChanges());
        $this->assertEquals(false, $status->hasUnstagedChanges());

        // We add a staged change.
        $status = new Git\Status\RepositoryStatus("M  myfile.txt");
        $this->assertEquals(true, $status->hasChanges());
        $this->assertEquals(false, $status->hasUnstagedChanges());
        $this->assertTrue(
            $status->getModified()[0]->hasStaged()
        );

        // We add unstaged changes.
        $status = new Git\Status\RepositoryStatus(" M myfile.txt");
        $this->assertEquals(true, $status->hasChanges());
        $this->assertEquals(true, $status->hasUnstagedChanges());
    }

    /**
     * Test that we parse files properly.
     */
    public function testParsing() {
        $statusText = <<<TXT
A  added.txt
AM added-modified.txt
?? unstaged/added.txt
 D unstaged/deleted.txt
D  staged/deleted.txt
R  old-name -> new-name
M  staged/modified.txt
 M unstaged/modified.txt
MM modified-both.txt
TXT;

        $status = new Git\Status\RepositoryStatus($statusText);
        $this->assertCount(9, $status->getFiles());
        $this->assertEquals([
            new AddedFile("added.txt", true, false),
            new AddedFile("added-modified.txt", true, true),
            new AddedFile("unstaged/added.txt", false, true)
        ], $status->getAdded());

        $this->assertEquals([
            new DeletedFile("unstaged/deleted.txt", false, true),
            new DeletedFile("staged/deleted.txt", true, false),
        ], $status->getDeleted());

        $this->assertEquals([
            new RenamedFile("new-name", "old-name"),
        ], $status->getRenamed());
        $renamed = $status->getRenamed()[0];
        $this->assertEquals('old-name', $renamed->getOldPath());
        $this->assertEquals('new-name', $renamed->getPath());


        $this->assertEquals([
            new ModifiedFile("staged/modified.txt", true, false),
            new ModifiedFile("unstaged/modified.txt", false, true),
            new ModifiedFile("modified-both.txt", true, true),
        ], $status->getModified());
    }
}
