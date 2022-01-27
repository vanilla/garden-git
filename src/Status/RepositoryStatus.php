<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Status;

/**
 * Used to parse a git status into a class based data structure.
 *
 * Notable limitations:
 * - Files with 2 different staged/unstaged statuses will only have their staged status reflected.
 */
class RepositoryStatus {

    private const CHAR_ADDED = "A";
    private const CHAR_ADD_UNSTAGED = "?";
    private const CHAR_MODIFIED = "M";
    private const CHAR_DELETED = "D";
    private const CHAR_RENAMED = "R";

    /** @var AddedFile[] */
    private $added = [];

    /** @var DeletedFile[] */
    private $deleted = [];

    /** @var RenamedFile[] */
    private $renamed = [];

    /** @var ModifiedFile[] */
    private $modified = [];

    /**
     * Constructor.
     *
     * @param string $gitStatusOutput A string that is the output of `git status --porcelain`.
     */
    public function __construct(string $gitStatusOutput) {
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
        return $this->hasAnyChanges([
            $this->added,
            $this->deleted,
            $this->renamed,
            $this->modified,
        ], function (AbstractFile $file) {
            return $file->hasUnstaged();
        });
    }

    /**
     * Check if our repo has changes.
     *
     * @return bool
     */
    public function hasChanges(): bool {
        return $this->hasAnyChanges([
            $this->added,
            $this->deleted,
            $this->renamed,
            $this->modified,
        ]);
    }

    /**
     * Get all of the statuses files.
     *
     * @return AbstractFile[]
     */
    public function getFiles(): array {
        return array_merge($this->added, $this->deleted, $this->renamed, $this->modified);
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


    ///
    /// Private utilities
    ///

    /**
     * Check if one of a few sets of files has any changes.
     *
     * @param AbstractFile[][] $fileSets Sets of files.
     * @param callable|null $filter Optionally filter each fileset.
     *
     * @return bool
     */
    private function hasAnyChanges(array $fileSets, callable $filter = null): bool {
        foreach ($fileSets as $fileSet) {
            if ($filter) {
                $fileSet = array_filter($fileSet, $filter);
            }

            if (count($fileSet) > 0) {
                return true;
            }
        }

        return false;
    }

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
            case self::CHAR_RENAMED:
                // Our path is actually 2 pieces.
                [$oldName, $newName] = explode("->", $filePath);

                $this->renamed[] = new RenamedFile($newName, $oldName);
                break;
        }
    }

}
