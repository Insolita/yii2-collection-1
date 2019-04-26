<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\collection;

use yii\base\InvalidCallException;
use yii\collection\ModelCollection;
use yii\db\ActiveQuery;
use yii\collection\Collection;
use yiiunit\collection\models\Customer;
use yiiunit\collection\models\CustomerCollection;

class ModelCollectionTest extends CollectionTest
{
    protected function setUp()
    {
        $this->mockApplication();

        \Yii::$app->db->createCommand()->createTable('customers', [
            'id' => 'pk',
            'name' => 'string NOT NULL',
            'age' => 'integer NOT NULL',
        ])->execute();

        parent::setUp();
    }

    protected function collect($data)
    {
        return new ModelCollection($data);
    }

    public function testCollect()
    {
        $this->assertInstanceOf(Collection::class, Customer::find()->collect());
        $this->assertInstanceOf(ActiveQuery::class, Customer::find()->collect()->query);
    }

    /**
     * @depends testCollect
     */
    public function testCollectCustomClass()
    {
        $this->assertInstanceOf(CustomerCollection::class, Customer::find()->collect(CustomerCollection::class));
    }


    public function testConstructWithNull()
    {
        $this->expectException(InvalidCallException::class);
        $collection = $this->collect(null);
        $this->assertEquals([], $collection->getData());
    }
}
