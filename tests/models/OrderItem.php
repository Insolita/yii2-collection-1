<?php

namespace yiiunit\collection\models;

use yii\collection\CollectionBehavior;
use yii\db\ActiveRecord;

/**
 * @property int                                $id
 * @property int                                $orderId
 * @property int                                $productId
 * @property int                                $qty
 * @property int|null                           $price
 * @property \yiiunit\collection\models\Order   $order
 * @property \yiiunit\collection\models\Product $product
 */
class OrderItem extends ActiveRecord
{
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::class, ['id' => 'productId']);
    }
    
    public function getPrice()
    {
        return $this->product ? $this->qty * $this->product->cost : null;
    }
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order_items';
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
