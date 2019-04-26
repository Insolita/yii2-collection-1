<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\collection;

use yii\base\InvalidCallException;
use yii\base\InvalidValueException;
use yii\collection\Collection;
use yii\collection\ModelCollection;
use yii\db\ActiveQuery;
use yii\db\IntegrityException;
use yii\helpers\ArrayHelper;
use yiiunit\collection\models\Customer;
use yiiunit\collection\models\CustomerCollection;
use yiiunit\collection\models\Order;
use yiiunit\collection\models\Product;
use function count;
use function is_array;

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
        $this->assertEquals(2, Customer::find()->where(['!=', 'name', 'Bob'])->collect()->count());
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

    public function testValidateAll()
    {
        $models = [
            new Customer(['name' => 'Bob', 'age' => 22]),
            new Customer(['name' => 'Alice', 'age'=>21])
        ];
        $collection = new ModelCollection($models);
        $isValid = $collection->validateAll();
        $this->assertTrue($isValid);
        $modified = $collection->each(function(Customer $model) {
            $model->setAttribute('name', 'Li');
        });
        $this->assertFalse($modified->validateAll());
        $modified->each(function(Customer $customer){
            $this->assertTrue($customer->hasErrors('name'));
        });
    }

    public function testValidateAllWithWrongItems()
    {
        $this->expectException(InvalidValueException::class);
        $collection = new ModelCollection([new Customer(['id'=>1]), 'foo']);
        $collection->validateAll();
    }

    public function testSaveAll()
    {
        $collection = new ModelCollection([
            new Customer(['name' => 'Bob', 'age' => 22]),
            new Customer(['name' => 'Alice', 'age'=>21])
        ]);
        $notNull = function($v){return $v !== null;};
        $savedIds = $collection->saveAll()->column('id')->filter($notNull);
        $this->assertEquals(2, $savedIds->count());
        $collection = new ModelCollection([
            new Customer(['name' => 'Li', 'age' => 22]),
        ]);
        $savedIds = $collection->saveAll()->column('id')->filter($notNull);
        $this->assertEquals(0, $savedIds->count());
        $this->assertTrue($collection[0]->hasErrors('name'));
    }

    public function testSaveAllTransaction()
    {
        $collection = new ModelCollection([
            new Product(['name' => 'Foo', 'cost' => 22]),
            new Product(['name' => 'Bar'])
        ]);
        $throw = false;
        try{
            $collection->saveAll(false, null, true);
        }catch (IntegrityException $e){
           $this->assertFalse(Product::find()->where(['name'=>'Foo'])->exists());
           $throw = true;
        }
        $this->assertTrue($throw);
    }

    public function testSaveAllWithWrongItems()
    {
        $this->expectException(InvalidValueException::class);
        $collection = new ModelCollection([new Customer(['id'=>1]), 'foo']);
        $collection->saveAll();
    }

    public function testDeleteAll()
    {
        $collection = Customer::find()->collect();
        $ids = $collection->column('id')->getData();
        $collection->deleteAll();
        $this->assertFalse(Customer::find()->where(['id'=>$ids])->exists());
    }

    public function testDeleteAllWithWrongItems()
    {
        $this->expectException(InvalidValueException::class);
        $collection = new ModelCollection([new Customer(['id'=>1]), 'foo']);
        $collection->deleteAll();
    }

    public function testUpdateAll()
    {
        Customer::find()->where(['name' => 'Bob'])
                ->collect()
                ->fillAll(['name' => 'Alice'])
                ->updateAll()
                ->each(function(Customer $model) {
                    $this->assertFalse($model->hasErrors());
                    $this->assertEquals('Alice', $model->name);
                })
                ->fillAll(['name' => 'Li'])
                ->updateAll()
                ->each(function(Customer $model) {
                    $this->assertTrue($model->hasErrors('name'));
                    $model->refresh();
                    $model->clearErrors();
                    $this->assertEquals('Alice', $model->name);
                })
                ->fillAll(['name' => 'Li'])
                ->updateAll(false)
                ->each(function(Customer $model) {
                    $this->assertFalse($model->hasErrors('name'));
                    $model->refresh();
                    $this->assertEquals('Li', $model->name);
                });
    }

    public function testUpdateAllTransaction()
    {
        $collection = Product::find()->where(['>', 'id', 3])->collect();
        $collection = $collection->fillAll(['cost'=>20], false)
                                 ->merge([new Product(['name' => 'Foo', 'cost' => null])]);
        $throwed = false;
        try{
            $collection->updateAll(false, null, true);
        }catch (IntegrityException $e){
            $throwed = true;
        }
        Product::find()->where(['>', 'id', 3])->collect()->each(function(Product $model){
            $this->assertNotEquals(20, $model->cost);
        });
        $this->assertTrue($throwed);
    }

    public function testUpdateAllWithWrongItems()
    {
        $this->expectException(InvalidValueException::class);
        $collection = new ModelCollection([new Customer(['id'=>1]), 'foo']);
        $collection->updateAll();
    }

    public function testInsertAll()
    {
        $collection = new ModelCollection([
            new Customer(['name' => 'Bob1', 'age' => 22]),
            new Customer(['name' => 'Alice1', 'age'=>21])
        ]);
        $collection->insertAll();
        $collection->each(function(Customer $model){
            $this->assertTrue(Customer::find()->where($model->getAttributes(['name','age']))->exists());
        });
    }

    public function testInsertAllWithWrongItems()
    {
        $this->expectException(InvalidValueException::class);
        $collection = new ModelCollection([new Customer(['name'=>'Foo']), new Product(['name'=>'Bar'])]);
        $collection->insertAll();
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

    public function testToArray()
    {
        $collection = Customer::find()->with(['orders'])->collect();
        $defaultArray = $collection->toArray()->getData();
        $this->assertTrue(is_array($defaultArray));

        foreach ($defaultArray as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('foo', $item);
            $this->assertArrayNotHasKey('age', $item);
            $this->assertArrayNotHasKey('orders', $item);
        }

        $expandedArray = $collection->toArray([], ['orders'])->getData();
        $this->assertTrue(is_array($expandedArray));

        foreach ($expandedArray as $item) {
            $this->assertArrayHasKey('orders', $item);
            $this->assertGreaterThan(0, count($item['orders']));
        }

        $customFieldsArray = $collection->toArray(['name'])->getData();;
        $this->assertTrue(is_array($customFieldsArray));

        foreach ($customFieldsArray as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayNotHasKey('foo', $item);
            $this->assertArrayNotHasKey('id', $item);
            $this->assertArrayNotHasKey('orders', $item);
        }
    }
}
