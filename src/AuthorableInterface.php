<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

interface AuthorableInterface {

    public function getAuthor(): ?Author;

    public function getDate(): ?\DateTimeInterface;
}
