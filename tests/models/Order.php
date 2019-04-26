<?php

namespace yiiunit\collection\models;

use yii\collection\CollectionBehavior;
use yii\db\ActiveRecord;

/**
 * @property int                                    $id
 * @property int                                    $customerId
 * @property string                                 $state
 * @property \yiiunit\collection\models\Customer    $customer
 * @property \yiiunit\collection\models\OrderItem[] $items
 * @property \yiiunit\collection\models\Product[]   $products
 */
class Order extends ActiveRecord
{
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCustomer()
    {
        return $this->hasOne(Customer::class, ['id' => 'customerId']);
    }
    
    public function getItems()
    {
        return $this->hasMany(OrderItem::class, ['orderId' => 'id']);
    }
    
    public function getProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'productId'])->via('items');
    }
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'orders';
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
}
