yii-cache-tag-dependency
========================

Verification of the cache relevance based on Dependency mechanism of Yii framework and tags, which are also stored in cache

Based on idea of Косыгин Александр < http://habrahabr.ru/users/kosalnik/ > described at http://habrahabr.ru/post/159079/


## Installation

Extract the [yii-debug-toolbar](/malyshev/yii-debug-toolbar/) https://github.com/pvolyntsev/yii-cache-tag-dependency/archive/master.zip from archive under protected/extensions

## Configuration

1. Setup alias to extension
```php
Yii::setPathOfAlias('TaggedCache', $basepath . DIRECTORY_SEPARATOR . 'extensions/yii-cache-tag-dependency');
```

2. This extension require configured cache


## Base Usage

```php
<?php
$cache = \Yii::app()->cache;

// create new cache dependency with set of three tags
$dependency = new \TaggedCache\Dependency(array('C', 'D', 'E'));

// save any value into cache with this dependency
$cache->set('cacheKey', 'cacheValue', 0, $dependency);

// check if there is a value in cache
var_dump($cache->get('cacheKey'));

// remove (invalidate) one or several tags
$tag = new \TaggedCache\Tag('D'); $tag->delete();

// check if cached value is absent in cache
var_dump($cache->get('cacheKey'));
```


## Success stories

### Situation One:
1. user has license and can upgrade or prolong it
1. available application featured depends on license

So if one license is upgraded we need to clear data associated with license owner but not all users.

Cache data from database: CActiveRecord and CDbConnection cache usage

```php
<?php
/**
 * The followings are the available columns in table 'license':
 * ...
 * @property string $owner_id Owner of license
 * @property int $expired Date of expiration
 * @property boolean $premium True if owner has super power
 */
class License extends CActiveRecord
{
    /**
     * Before save (prolong or upgrade) of license invalidate any associated data in cache
     */
    public function beforeSave()
    {
        $tag = new \TaggedCache\Tag('user-license:' . $this->user_id);
        $tag->delete(); // invalidate licence cache data associated with one exact user
    }

    public function upgradeToPremium()
    {
        $this->premium = true;
        if ($this->save())
        {
            $tag = new \TaggedCache\Tag('premium-users');
            $tag->delete(); // invalidate licence cache data associated with super users

            return true;
        }
        return false;
    }

    public function notExpiredToday()
    {
        $this->dbCriteria->addCondition('expiry_date >= :today');
        $this->dbCriteria->params[':today'] = time();
        return $this;
    }

    public function ownedByUser($ownerId)
    {
        $this->dbCriteria->addCondition('owner_id >= :owner_id');
        $this->dbCriteria->params[':owner_id'] = $ownerId;
        return $this;
    }

    public function isPremium()
    {
        $this->dbCriteria->addCondition('premium = 1');
        return $this;
    }

    public function findAllLicensesByOwner($user_id)
    {
        $licenseTag = new \TaggedCache\Tag('user-license:' . $this->user_id); // tag for licenses of one exact user
        $dependency = new \TaggedCache\Dependency(array($licenseTag));
        return License::model()
            ->cache(24*60*60, $dependency) // use cache to store data, max 1 day
            ->notExpiredToday()
            ->ownedByUser($user_id)
            ->findAll();
    }

    public function findAllPremiumUsers()
    {
        $premiumTag = new \TaggedCache\Tag('premium-users');
        $dependency = new \TaggedCache\Dependency(array($premiumTag));
        return License::model()
            ->cache(24*60*60, $dependency) // use cache to store data, max 1 day
            ->notExpiredToday()
            ->isPremium()
            ->findAll();
    }
}
```


* example: see License::beforeSave() for invalidation of cache for one user
```php
$userLicenses = License::model()->findAllLicensesByOwner(\Yii::app()->user->id);
```

* example: see License::upgradeToPremium() for invalidation of cache for all premium users
```php
$userLicense = new License;
$userLicense->owner_id = \Yii::app()->user->id;
$userLicense->upgradeToPremium();
```

### Situation Two
An application administrator can turn on and off some application features
across the entire application at once

And if applications settings are changed we need to invalidate cached info
   about all users rights but not all the data in cache.

Cache fragments: widget or controller usage with COutputCache

```php
<?php
$userId = \Yii::app()->user->id;
$userRightsTag = new \TaggedCache\Tag('user-rights:'.$userId); // tag for one user rigths
$rbacTag = new \TaggedCache\Tag('rbac'); // tag for system-wide users rigths
$dependency = new \TaggedCache\Dependency(array($rbacTag, $userRightsTag));

if($this->beginCache('my-features-'.$userId, array('duration'=>3600*24, 'dependency' => $dependency))) { ?>
<?php $someStuff = new \SomeStuff; ?>
    <ul>
        <?php if ($someStuff->hasFeature($userId, 'can-start-moon-shuttle')) { ?>
            <li><a href="#">Start the shuttle to the Moon</a><li>
        <?php } ?>
        <?php if ($someStuff->hasFeature($userId, 'can-water-walk')) { ?>
            <li><a href="#">Walk by water</a><li><tr>
        <?php } ?>
        ...
    </ul>
<?php $this->endCache(); } ?>
```


* example: clear all data associated with rights of one user
```php
$userRightsTag = new \TaggedCache\Tag('user-rights:'.$userId); $rbacTag->delete();
```

* example: clear all data associated with rights of all users
```php
$rbacTag = new \TaggedCache\Tag('rbac'); $rbacTag->delete();
```




## Bugs

Please use [issues](https://github.com/pvolyntsev/yii-cache-tag-dependency/issues) to report bugs
