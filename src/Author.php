<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git;

class Author {

    /** @var string */
    private $name;

    /** @var string */
    private $email;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $email
     */
    public function __construct(string $name, string $email) {
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getEmail(): string {
        return $this->email;
    }
}
