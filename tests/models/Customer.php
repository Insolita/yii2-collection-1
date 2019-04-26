<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yiiunit\collection\models;

use yii\db\ActiveRecord;
use yii\collection\CollectionBehavior;

/**
 * Customer Model
 *
 * @property int $id
 * @property string $name
 * @property int $age
 * @property-read \yiiunit\collection\models\Order[]|array $orders
 */
class Customer extends ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'customers';
    }

    public function rules()
    {
        return [
            [['age'], 'integer', 'min'=>0],
            [['name'], 'string', 'min'=>3,'max' => 15]
        ];
    }

    /**
     * {@inheritdoc}
     * @return \yii\db\ActiveQuery|CollectionBehavior
     */
    public static function find()
    {
        $query = parent::find();
        $query->attachBehavior('collection', CollectionBehavior::class);
        return $query;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrders()
    {
        return $this->hasMany(Order::class, ['customerId' => 'id'])->inverseOf('customer');
    }

    /**
     * @return \yii\collection\ModelCollection
     */
    public function relatedOrders()
    {
        $items = $this->isRelationPopulated('orders') ? $this->orders : null;
        return new \yii\collection\ModelCollection($items, $this->getOrders());
    }

    /**
     * @return \yii\collection\ModelCollection
     */
    public function relation($name)
    {
        $relation = $this->getRelation($name);
        $items = $this->isRelationPopulated($name) ? $this->$name : null;
        return new \yii\collection\ModelCollection($items, $relation);
    }

    public function fields()
    {
        $fields = parent::fields();
        unset($fields['age']);
        $fields['foo'] = function(){
            return 'foo';
        };
        return $fields;
    }
}
