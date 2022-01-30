<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

use Garden\Git\File\AbstractFile;
use Garden\Git\File\AddedFile;
use Garden\Git\File\DeletedFile;
use Garden\Git\File\IgnoredFile;
use Garden\Git\File\ModifiedFile;
use Garden\Git\File\RenamedFile;

/**
 * Used to parse a git status into a class based data structure.
 *
 * Notable limitations:
 * - Files with 2 different staged/unstaged statuses will only have their staged status reflected.
 */
class Status {

    private const CHAR_ADDED = "A";
    private const CHAR_ADD_UNSTAGED = "?";
    private const CHAR_IGNORED = "!";
    private const CHAR_MODIFIED = "M";
    private const CHAR_DELETED = "D";
    private const CHAR_RENAMED = "R";

    /** @var string */
    private $statusText;

    /** @var AddedFile[] */
    private $added = [];

    /** @var DeletedFile[] */
    private $deleted = [];

    /** @var RenamedFile[] */
    private $renamed = [];

    /** @var ModifiedFile[] */
    private $modified = [];

    /** @var IgnoredFile[] */
    private $ignored = [];

    /**
     * Constructor.
     *
     * @param string $gitStatusOutput A string that is the output of `git status --porcelain`.
     */
    public function __construct(string $gitStatusOutput) {
        $this->statusText = $gitStatusOutput;
        $statusLines = array_filter(explode("\n", $gitStatusOutput));
        foreach ($statusLines as $statusLine) {
            $this->applyStatusLine($statusLine);
        }
    }

    ///
    /// Public interface
    ///

    /**
     * Check if we have any unstaged files.
     *
     * @return bool
     */
    public function hasUnstagedChanges(): bool {
        return count($this->getUnstagedFiles()) > 0;
    }

    /**
     * Check if our repo has changes.
     *
     * @return bool
     */
    public function hasChanges(): bool {
        return count($this->getFiles()) > 0;
    }

    /**
     * Get all the status files except the ignored files.
     *
     * @return AbstractFile[]
     */
    public function getFiles(): array {
        return array_merge($this->added, $this->deleted, $this->renamed, $this->modified);
    }

    /**
     * Get all the status files that have staged changes except the ignored files.
     *
     * @return AbstractFile[]
     */
    public function getStagedFiles(): array {
        $files = $this->getFiles();
        $stagedFiles = [];
        foreach ($files as $file) {
            if ($file->hasStaged()) {
                $stagedFiles[] = $file;
            }
        }
        return $stagedFiles;
    }


    /**
     * Get all the status files that have unstaged changes except the ignored files.
     *
     * @return AbstractFile[]
     */
    public function getUnstagedFiles(): array {
        $files = $this->getFiles();
        $stagedFiles = [];
        foreach ($files as $file) {
            if ($file->hasUnstaged()) {
                $stagedFiles[] = $file;
            }
        }
        return $stagedFiles;
    }

    /**
     * @return AddedFile[]
     */
    public function getAdded(): array {
        return $this->added;
    }

    /**
     * @return DeletedFile[]
     */
    public function getDeleted(): array {
        return $this->deleted;
    }

    /**
     * @return RenamedFile[]
     */
    public function getRenamed(): array {
        return $this->renamed;
    }

    /**
     * @return ModifiedFile[]
     */
    public function getModified(): array {
        return $this->modified;
    }

    /**
     * @return IgnoredFile[]
     */
    public function getIgnored(): array {
        return $this->ignored;
    }

    /**
     * @return string
     */
    public function getStatusText(): string {
        return $this->statusText;
    }

    ///
    /// Private utilities
    ///

    /**
     * @param string $statusLine
     * @return void
     */
    private function applyStatusLine(string $statusLine): void {
        $firstChar = substr($statusLine, 0, 1);
        $secondChar = substr($statusLine, 1, 1);
        $filePath = substr($statusLine, 3);

        $statusChar = trim($firstChar) ?: $secondChar;
        $hasStaged = $firstChar !== " ";
        $hasUnstaged = $secondChar !== " ";

        switch ($statusChar) {
            case self::CHAR_ADDED:
                $this->added[] = new AddedFile($filePath, $hasStaged, $hasUnstaged);
                break;
            case self::CHAR_ADD_UNSTAGED:
                $this->added[] = new AddedFile($filePath, false, $hasUnstaged);
                break;
            case self::CHAR_DELETED:
                $this->deleted[] = new DeletedFile($filePath, $hasStaged, $hasUnstaged);
                break;
            case self::CHAR_MODIFIED:
                $this->modified[] = new ModifiedFile($filePath, $hasStaged, $hasUnstaged);
                break;
            case self::CHAR_IGNORED:
                $this->ignored[] = new IgnoredFile($filePath);
                break;
            case self::CHAR_RENAMED:
                // Our path is actually 2 pieces.
                [$oldName, $newName] = explode("->", $filePath);

                $this->renamed[] = new RenamedFile($newName, $oldName);
                break;
        }
    }

}
