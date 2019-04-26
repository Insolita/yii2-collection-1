<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\collection;

use yii\base\InvalidCallException;
use yii\collection\Collection;
use yii\collection\ModelCollection;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yiiunit\collection\models\Customer;
use yiiunit\collection\models\CustomerCollection;
use yiiunit\collection\models\Order;
use function is_array;
use function strrev;

class ModelCollectionTest extends CollectionTest
{
    protected function setUp()
    {
        parent::setUp();
        $this->fillDbFixtures();
    }

    protected function collect($data)
    {
        return new ModelCollection($data);
    }

    public function testCollect()
    {
        $this->assertInstanceOf(Collection::class, Customer::find()->collect());
        $this->assertInstanceOf(ModelCollection::class, Customer::find()->collect());
        $this->assertInstanceOf(CustomerCollection::class, Customer::find()->collect(CustomerCollection::class));
        $this->assertInstanceOf(ActiveQuery::class, Customer::find()->collect()->query);
        $this->assertEquals(3, Customer::find()->collect()->count());
    }

    public function testScenarios()
    {
        $customers = Customer::find()->collect()->scenario('foo');
        $customers->each(function(Customer $customer){
            $this->assertEquals('foo', $customer->scenario);
        });
    }


    public function testFindWith()
    {
        $customers = Customer::find()->collect()->findWith(['orders']);
        $customers->each(function(Customer $customer){
            $this->assertTrue($customer->isRelationPopulated('orders'));
            $this->assertTrue(is_array($customer->orders));
        });
        $customers = Customer::find()->collect()->findWith(['orders', 'orders.products']);
        $customers->each(function(Customer $customer){
            $this->assertTrue($customer->isRelationPopulated('orders'));
            $this->assertTrue(is_array($customer->orders));
            $orders = new ModelCollection($customer->orders, ['query'=>$customer->getOrders()]);
            $this->assertEquals(ArrayHelper::getColumn($customer->orders, 'id'), $orders->column('id')->getData());
            $orders->each(function(Order $order){
                $this->assertTrue($order->isRelationPopulated('products'));
                $this->assertTrue($order->isRelationPopulated('items'));
                $this->assertTrue($order->isRelationPopulated('customer'));
            });
        });
    }

    public function testSaveAll()
    {
        $collection = Customer::find()->collect()->indexBy('id');
        Customer::find()->collect()->each(function(Customer $model){
             $model->setAttribute('name', strrev($model->name));
        })->saveAll(false)->each(function(Customer $model) use($collection){
            $model->refresh();
            $this->assertEquals($model->name, strrev($collection[$model->id]->name));
        });
    }

    public function testDeleteAll()
    {
        $collection = Customer::find()->collect();
        $ids = $collection->column('id')->getData();
        $collection->deleteAll();
        $this->assertFalse(Customer::find()->where(['id'=>$ids])->exists());
    }

    public function testUpdateAll()
    {
        Customer::find()
                ->collect()
                ->updateAll(['name' => 'Bob'])
                ->each(function(Customer $model) {
                    $model->refresh();
                    $this->assertEquals($model->name, 'Bob');
                });
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
