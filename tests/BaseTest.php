<?php

use PHPUnit\Framework\TestCase;


class BaseTest extends TestCase {
	
	public function testBase()
    {
        $this->assertEmpty('');
        $this->assertTrue(true);
    }

}