<?php

use \TaggedCache\Dependency;
use \TaggedCache\Tag;

class TaggedCache_DependencyTest extends CTestCase
{
	/**
	 * Test if new tags have no value
	 */
	public function testUnknownTagVersionHaveNoValue()
	{
		/** @var \CCache $cache */
		$cache = \Yii::app()->cache;

		$unknownTag = new Tag(uniqid());
		$this->assertFalse($unknownTag->get(), 'Unknown tag must be FALSE');
	}

	/**
	 * Test if previously saved tags have old values
	 */
	public function testKnownTagVersionHasOldValue()
	{
		/** @var \CCache $cache */
		$cache = \Yii::app()->cache;

		$oldTagKey = uniqid();
		$oldTag = new Tag($oldTagKey);
		$oldTagVersion = microtime();
		$oldTag->set($oldTagVersion)->save();

		$unknownTag = new Tag(uniqid());
		$knownTag = new Tag($oldTagKey); // new Tag instance with the same key as $oldTag
		$this->assertFalse($unknownTag->get(), 'Unknown tag must be FALSE');
		$this->assertEquals($oldTagVersion, $knownTag->get(), 'Known tag must have the OLD value');
	}

	/**
	 * Test if tags not changed if used in several Dependency instances
	 */
	public function testCacheTagVersionsNotChangedIfSavedAgain()
	{
		/** @var \CCache $cache */
		$cache = \Yii::app()->cache;

		$key12 = 'key|'.uniqid().'|12';
		$value12 = 'value|'.uniqid().'|12';

		$key1 = 'key|'.uniqid().'|1';
		$value1 = 'value|'.uniqid().'|1';

		$key2 = 'key|'.uniqid().'|2';
		$value2 = 'value|'.uniqid().'|2';

		#1 save one tag
		$tag1 = new Tag(uniqid());
		$tag2 = new Tag(uniqid());
		$this->assertFalse($tag1->get(), 'Not saved tag must be FALSE');
		$this->assertFalse($tag2->get(), 'Not saved tag must be FALSE');

		$cache->set($key1, $value1, 20, new Dependency(array($tag1)));
		$tag1Version = $tag1->get();
		$this->assertInternalType('string', $tag1Version, 'Saved tag must have VERSION after save');
		$this->assertFalse($tag2->get(), 'Not saved tag must be FALSE');

		$cache->set($key2, $value2, 20, new Dependency(array($tag2)));
		$tag2Version = $tag2->get();
		$this->assertEquals($tag1Version, $tag1->get(), 'Version of tag must not changed if tag not deleted');
		$this->assertInternalType('string', $tag2Version, 'Saved tag must have VERSION after save');

		#2 save two tags
		$cache->set($key12, $value12, 20, new Dependency(array($tag1, $tag2)));
		$this->assertEquals($tag1Version, $tag1->get(), 'Version of tag must not changed if tag not deleted');
		$this->assertEquals($tag2Version, $tag2->get(), 'Version of tag must not changed if tag not deleted');
	}

	/**
	 * Test if tags have new value if tag deleted and used again
	 */
	public function testCacheTagVersionsMustUpdatedIfTagDeleted()
	{
		/** @var \CCache $cache */
		$cache = \Yii::app()->cache;

		$key1 = 'key|'.uniqid().'|1';
		$value1 = 'value|'.uniqid().'|1';

		#1 save tag
		$tag1 = new Tag(uniqid());
		$cache->set($key1, $value1, 20, new Dependency(array($tag1)));
		$tag1Version = $tag1->get();

		#2 delete tag
		$tag1->delete();
		$cache->set($key1, $value1, 20, new Dependency(array($tag1)));
		$this->assertInternalType('string', $tag1->get(), 'Saved tag must have VERSION after save');
		$this->assertNotEquals($tag1Version, $tag1->get(), 'Deleted tag must have NEW value if deleted and used again');
	}

	public function testDependencyChangedIfTagDeleted()
	{
		/** @var \CCache $cache */
		$cache = \Yii::app()->cache;

		$key12 = 'key|'.microtime().'|12';
		$value12 = 'value|'.microtime().'|12';

		$key1 = 'key|'.microtime().'|1';
		$value1 = 'value|'.microtime().'|1';

		$key2 = 'key|'.microtime().'|2';
		$value2 = 'value|'.microtime().'|2';

		$tag1 = new Tag(uniqid());
		$tag2 = new Tag(uniqid());

		$cache->set($key12, $value12, 20, new Dependency(array($tag1, $tag2)));
		$cache->set($key1, $value1, 20, new Dependency(array($tag1)));
		$cache->set($key2, $value2, 20, new Dependency(array($tag2)));

		#1 correct values saved
		$this->assertEquals($value12, $cache->get($key12), 'Incorrect value in cache');
		$this->assertEquals($value1, $cache->get($key1), 'Incorrect value in cache');
		$this->assertEquals($value2, $cache->get($key2), 'Incorrect value in cache');

		#2 delete tag 1
		$tag1->delete();
		$this->assertFalse($cache->get($key12), 'Cached value must become invalid if tag deleted');
		$this->assertFalse($cache->get($key1), 'Cached value must become invalid if tag deleted');
		$this->assertEquals($value2, $cache->get($key2), 'Cached value must become invalid if not used tag which was deleted');
	}
}