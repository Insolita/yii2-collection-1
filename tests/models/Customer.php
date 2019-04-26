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
}
