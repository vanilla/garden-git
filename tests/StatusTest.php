<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests;

use Garden\Git;
use Garden\Git\File\AddedFile;
use Garden\Git\File\DeletedFile;
use Garden\Git\File\ModifiedFile;
use Garden\Git\File\RenamedFile;
use Garden\Git\Tests\Fixtures\GitTestCase;

/**
 * Tests for fetching statuses.
 */
class StatusTest extends GitTestCase {

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
                new Git\File\AddedFile('test1', true, false),
                new Git\File\AddedFile('test2', false, true),
            ],
            $status->getAdded()
        );
    }


    /**
     * Test top level change detection.
     */
    public function testHasChanges() {
        // No changes.
        $status = new Git\Status("");
        $this->assertEquals(false, $status->hasChanges());
        $this->assertEquals(false, $status->hasUnstagedChanges());

        // We add a staged change.
        $status = new Git\Status("M  myfile.txt");
        $this->assertEquals(true, $status->hasChanges());
        $this->assertEquals(false, $status->hasUnstagedChanges());
        $this->assertTrue(
            $status->getModified()[0]->hasStaged()
        );

        // We add unstaged changes.
        $status = new Git\Status(" M myfile.txt");
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
!! ignored/
TXT;

        $status = new Git\Status($statusText);
        $this->assertEquals($statusText, $status->getStatusText());
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

        $this->assertEquals([
            new Git\File\IgnoredFile("ignored/"),
        ], $status->getIgnored());
    }
}
