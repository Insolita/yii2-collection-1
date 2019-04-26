<?php

namespace yiiunit\collection;

use function count;
use yii\collection\BaseGeneratorCollection;
use yiiunit\collection\models\Customer;

class BaseCollectionTest extends CollectionTest
{
    /**
     * @param $data
     * @return \yii\collection\BaseGeneratorCollection
     */
    protected function collect($data)
    {
        return new BaseGeneratorCollection($data);
    }

    public function testSort()
    {
       $this->markTestSkipped('Not implemented');
    }

    public function testSortBy()
    {
        $this->markTestSkipped('Not implemented');
    }

    public function testSortByKey()
    {
        $this->markTestSkipped('Not implemented');
    }

    public function testSortNatural()
    {
        $this->markTestSkipped('Not implemented');
    }

    public function testGroupBy()
    {
        $this->markTestSkipped('Not implemented');
    }
    public function testReverse()
    {
        $this->markTestSkipped('Not implemented');
    }
    public function testSlice()
    {
        $this->markTestSkipped('Not implemented');
    }
    public function testPaginate()
    {
        $this->markTestSkipped('Not implemented');
    }

    public function testArrayAccessRead()
    {
        $this->markTestSkipped('Not implemented');
    }


    public function testArrayAccessWrite()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * @expectedException \yii\base\InvalidCallException
     */
    public function testArrayAccessWrite2()
    {
        $this->markTestSkipped('Not implemented');
    }

    /**
     * @expectedException \yii\base\InvalidCallException
     */
    public function testArrayAccessUnset()
    {
        $this->markTestSkipped('Not implemented');
    }

    public function testEach()
    {
        $this->markTestIncomplete();
    }

    public function testCountable()
    {
        $collection = $this->collect([]);
        $this->assertEquals(0, $collection->count());

        $models = [
            new Customer(['id' => 1]),
            new Customer(['id' => 2]),
            new Customer(['id' => 3]),
        ];
        $collection = $this->collect($models);
        $this->assertEquals(3, $collection->count());
    }

    public function testMerge()
    {
        $models = [
            new Customer(['id' => 1]),
            new Customer(['id' => 2]),
            new Customer(['id' => 3]),
        ];
        $collection = $this->collect($models);
        $merge = $collection->merge($this->collect([new Customer(['id'=>2, 'name' => 'Bob'])]))->getData();
        $this->assertEquals(3, count($merge));
    }


}
