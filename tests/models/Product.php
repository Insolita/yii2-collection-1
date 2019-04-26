<?php

namespace yiiunit\collection\models;

use yii\collection\CollectionBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property string $cost
 */
class Product extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'products';
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
