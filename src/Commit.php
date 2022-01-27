<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

/**
 * Represents a git commit.
 */
class Commit implements AuthorableInterface, CommitishInterace {

    /** @var string */
    private $hash;

    /** @var \DateTimeInterface */
    private $date;

    /** @var Author */
    private $author;

    /** @var string */
    private $message;

    /**
     * DI.
     *
     * @param string $hash
     * @param \DateTimeInterface $date
     * @param Author $author
     * @param string $message
     */
    public function __construct(string $hash, \DateTimeInterface $date, Author $author, string $message) {
        $this->hash = $hash;
        $this->date = $date;
        $this->author = $author;
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getCommitHash(): string {
        return $this->hash;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface {
        return $this->date;
    }

    /**
     * @return Author
     */
    public function getAuthor(): Author {
        return $this->author;
    }

    /**
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }
}
