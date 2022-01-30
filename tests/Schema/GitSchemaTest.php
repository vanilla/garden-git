<?php
/**
 * @author Adam Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2022 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Git\Tests\Schema;

use Garden\Git;
use PHPUnit\Framework\TestCase;

/**
 * Test for the GitSchema.
 */
class GitSchemaTest extends TestCase {

    /**
     * Test that our schema throws git exceptions.
     */
    public function testExceptionCoercion() {
        $schema1 = Git\Schema\GitSchema::parse(['field1:s']);
        $schema2 = Git\Schema\GitSchema::parse(['field1:s']);
        $schema = $schema1->merge($schema2);
        $this->expectException(Git\Exception\GitException::class);
        $schema->validate(['field' => null]);
    }
}
